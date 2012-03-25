<?PHP
	class IRC {
		private static $chans = Array();
		
		public static function split( $message ) {
			if( !$message )
				return null;
			
			$return = Array();
			$i = 0;
			$quotes = false;
			
			if( $message[ $i ] == ':' ) {
				$return[ 'type' ] = 'relayed';
				$i++;
			} else
				$return[ 'type' ] = 'direct';
			
			$return[ 'rawpieces' ] = Array();
			$temp = '';
			for( ; $i < strlen( $message ) ; $i++ ) {
				if( $quotes and $message[ $i ] != '"' )
					$temp .= $message[ $i ];
				else 
					switch( $message[ $i ] ) {
						case ' ':
							$return[ 'rawpieces' ][] = $temp;
							$temp = '';
							break;
						case '"':
							if( $quotes or $temp == '' ) {
								$quotes = !$quotes;
								break;
							}
						case ':':
							if( $temp == '' ) {
								$i++;
								$return[ 'rawpieces' ][] = substr( $message, $i );
								$i = strlen( $message );
								break;
							}
						default:
							$temp .= $message[ $i ];
					}
			}
			if( $temp != '' )
				$return[ 'rawpieces' ][] = $temp;
			
			if( $return[ 'type' ] == 'relayed' ) {
				$return[ 'source' ] = $return[ 'rawpieces' ][ 0 ];
				$return[ 'command' ] = strtolower( $return[ 'rawpieces' ][ 1 ] );
				$return[ 'target' ] = $return[ 'rawpieces' ][ 2 ];
				$return[ 'pieces' ] = array_slice( $return[ 'rawpieces' ], 3 );
			} else {
				$return[ 'source' ] = 'Server';
				$return[ 'command' ] = strtolower( $return[ 'rawpieces' ][ 0 ] );
				$return[ 'target' ] = 'You';
				$return[ 'pieces' ] = array_slice( $return[ 'rawpieces' ], 1 );
			}
			$return[ 'raw' ] = $message;
			return $return;
		}

		public static function say( $chans, $message ) {
			if( array_key_exists( 'irc' . $chans, self::$chans) ) {
				$chans = 'irc' . $chans;
				echo 'Saying to ' . $chans . ' (' . self::$chans[ $chans ] . '): ' . $message . "\n";
				foreach( explode( ',', self::$chans[ $chans ] ) as $chan ) {
					$udp = fsockopen( 'udp://' . 'localhost', 1337);
					fwrite( $udp, $chan . ' :' . $message );
					fclose( $udp );
				}
			} else {
				echo 'Saying to ' . $chans . ': ' . $message . "\n";
				$udp = fsockopen( 'udp://' . 'localhost', 1337);
				fwrite( $udp, $chans . ' :' . $message );
				fclose( $udp );
			}
		}

		public static function init() {
			$ircconfig = explode( "\n", API::$q->getpage( 'User:' . Config::$owner . '/CBChannels.js' ) );
			$tmp = array();
			foreach( $ircconfig as $tmpline ) {
				if( $tmpline[ 0 ] != '#') {
					$tmpline = explode( '=', $tmpline, 2);
					$tmp[ trim( $tmpline[ 0 ] ) ] = trim( $tmpline[ 1 ] );
				}
			}

			self::$chans = $tmp;
		}
	}
?>	
