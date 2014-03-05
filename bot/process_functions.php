<?PHP
	class Process {
		public static function processEditThread( $change ) {
			$change[ 'edit_status' ] = 'not_reverted';
			if( !isVandalism( $change[ 'all' ], $s ) ) {
				Feed::bail( $change, 'Below threshold', $s );
				return;
			}
			
			echo 'Is ' . $change[ 'user' ] . ' whitelisted ?' . "\n";
			if( Action::isWhitelisted( $change[ 'user' ] ) ) {
				Feed::bail( $change, 'Whitelisted', $s );
				return;
			}
			
			echo 'No.' . "\n";
			
			$reason = 'ANN scored at ' . $s;
			
			$heuristic = '';
			$log = null;
			
			$diff = 'http://en.wikipedia.org/w/index.php' .
				'?title=' . urlencode( $change[ 'title' ] ) .
				'&diff=' . urlencode( $change[ 'revid' ] ) .
				'&oldid=' . urlencode( $change[ 'old_revid' ] );

 			$report = '[[' . str_replace( 'File:', ':File:', $change[ 'title' ] ) . ']] was '
				. '[' . $diff . ' changed] by '
				. '[[Special:Contributions/' . $change[ 'user' ] . '|' . $change[ 'user' ] . ']] '
				. '[[User:' . $change[ 'user' ] . '|(u)]] '
				. '[[User talk:' . $change[ 'user' ] . '|(t)]] '
				. $reason . ' on ' . gmdate( 'c' );

			$oftVand = unserialize( file_get_contents( 'oftenvandalized.txt' ) );
			if( rand( 1, 50 ) == 2 )
				foreach( $oftVand as $art => $artVands )
					foreach( $artVands as $key => $time )
						if( ( time() - $time ) > 2 * 24 * 60 * 60 )
							unset( $oftVand[ $art ][ $key ] );
			$oftVand[ $change[ 'title' ] ][] = time();
			if( count( $oftVand[ $change[ 'title' ] ] ) >= 30 )
				IRC::say( 'reportchannel', '!admin [['.$change['title'].']] has been vandalized '.( count( $oftVand[ $change[ 'title' ] ] ) ).' times in the last 2 days.' );
			file_put_contents( 'oftenvandalized.txt', serialize( $oftVand ) );

			//IRC::say( 'debugchannel', 'Possible vandalism: ' . $change[ 'title' ] . ' changed by ' . $change[ 'user' ] . ' ' . $reason . '(' . $s . ')' );
			//IRC::say( 'debugchannel', '( http://en.wikipedia.org/w/index.php?title=' . urlencode( $change[ 'title' ] ) . '&action=history | ' . $change[ 'url' ] . ' )' );
			$ircreport = "\x0315[[\x0307" . $change[ 'title' ] . "\x0315]] by \"\x0303" . $change[ 'user' ] . "\x0315\" (\x0312 " . $change[ 'url' ] . " \x0315) \x0306" . $s . "\x0315 (";

			checkMySQL();
			$query = 'INSERT INTO `vandalism` ' .
				'(`id`,`user`,`article`,`heuristic`' . ( ( is_array( $log ) ) ? ',`regex`' : '' ) . ',`reason`,`diff`,`old_id`,`new_id`,`reverted`) ' .
				'VALUES ' .
				'(NULL,\'' . mysql_real_escape_string( $change[ 'user' ] ) . '\',' .
				'\'' . mysql_real_escape_string( $change[ 'title' ] ) . '\',' .
				'\'' . mysql_real_escape_string( $heuristic ) . '\',' .
				( ( is_array( $log ) ) ? '\'' . mysql_real_escape_string( $logt ) . '\',' : '' ) .
				'\'' . mysql_real_escape_string( $reason ) . '\',' .
				'\'' . mysql_real_escape_string( $change[ 'url' ] ) . '\',' .
				'\'' . mysql_real_escape_string( $change[ 'old_revid' ] ) . '\',' .
				'\'' . mysql_real_escape_string( $change[ 'revid' ] ) . '\',0)';

			mysql_query( $query, Globals::$mysql );
			$change[ 'mysqlid' ] = mysql_insert_id();
			
			echo 'Should revert?' . "\n";

			list( $shouldRevert, $revertReason ) = Action::shouldRevert( $change );
			
			if( $shouldRevert ) {
				echo 'Yes.' . "\n";
				$rbret = Action::doRevert( $change );
				if ($rbret !== false) {
					$change[ 'edit_status' ] = 'reverted';
					RedisProxy::send( $change );
					//IRC::say( 'debugchannel', 'Reverted. (' . ( microtime( true ) - $change[ 'startTime' ] ) . ' s)' );
					IRC::say( 'debugchannel', $ircreport . "\x0304Reverted\x0315) (\x0313" . $revertReason . "\x0315) (\x0302" . ( microtime( true ) - $change[ 'startTime' ] ) . " \x0315s)" );
					Action::doWarn( $change, $report );
					checkMySQL();
					mysql_query( 'UPDATE `vandalism` SET `reverted` = 1 WHERE `id` = \'' . mysql_real_escape_string( $change[ 'mysqlid' ] ) . '\'', Globals::$mysql );
					Feed::bail( $change, $revertReason, $s, true );
				} else {
					$change[ 'edit_status' ] = 'beaten';
					$rv2 = API::$a->revisions( $change[ 'title' ], 1 );
					if( $change[ 'user' ] != $rv2[ 0 ][ 'user' ] ) {
						//IRC::say( 'debugchannel', 'Grr! Beaten by ' . $rv2[ 0 ][ 'user' ] );
						RedisProxy::send( $change );
						IRC::say( 'debugchannel', $ircreport . "\x0303Not Reverted\x0315) (\x0313Beaten by " . $rv2[ 0 ][ 'user' ] . "\x0315) (\x0302" . ( microtime( true ) - $change[ 'startTime' ] ) . " \x0315s)" );
						checkMySQL();

						mysql_query( 'INSERT INTO `beaten` (`id`,`article`,`diff`,`user`) VALUES (NULL,\'' . mysql_real_escape_string( $change['title'] ) . '\',\'' . mysql_real_escape_string( $change[ 'url' ] ) . '\',\'' . mysql_real_escape_string( $rv2[ 0 ][ 'user' ] ) . '\')', Globals::$mysql );
						Feed::bail( $change, 'Beaten by ' . $rv2[ 0 ][ 'user' ], $s );
					}
				}
			} else {
				RedisProxy::send( $change );
				IRC::say( 'debugchannel', $ircreport . "\x0303Not Reverted\x0315) (\x0313" . $revertReason . "\x0315) (\x0302" . ( microtime( true ) - $change[ 'startTime' ] ) . " \x0315s)" );
				Feed::bail( $change, $revertReason, $s );
			}
		}
		
		public static function processEdit( $change ) {
			if (
				( time() - Globals::$tfas ) >= 1800
				and ( preg_match( '/\(\'\'\'\[\[([^|]*)\|more...\]\]\'\'\'\)/iU', API::$q->getpage( 'Wikipedia:Today\'s featured article/' . date( 'F j, Y' ) ), $tfam ) )
			) {
				Globals::$tfas = time();
				Globals::$tfa = $tfam[ 1 ];
			}
			if( Config::$fork ) {
				$pid = pcntl_fork();
				if( $pid != 0 ) {
					echo 'Forked - ' . $pid . "\n";
					return;
				}
				
			}
			$change = parseFeedData( $change );
			$change[ 'justtitle' ] = $change[ 'title' ];
			if( $change[ 'namespace' ] != 'Main:' )
				$change[ 'title' ] = $change[ 'namespace' ] . $change[ 'title' ];
			self::processEditThread( $change );
			if( Config::$fork )
				die();
		}
	}
