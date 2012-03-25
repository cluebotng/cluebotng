<?PHP
	include 'editdbMasterCredentials.php';
	include '../bot/cbng.php';

	function error( $str ) {
		file_put_contents( 'php://stderr', $str . "\n" );
		die();
	}

	function getMasterMySQL() {
		global $mysqlmasteruser, $mysqlmasterpass, $mysqlmasterhost;
		static $mysql = null;
		
		if( $mysql !== null )
			return $mysql;
		
		$mysql = mysql_connect( $mysqlmasterhost, $mysqlmasteruser, $mysqlmasterpass );
		if( !$mysql )
			error( 'MySQL Connection Error: ' . mysql_error() );
		
		if( !mysql_select_db( 'cbng_editdb_master', $mysql ) )
			error( 'MySQL DB Error: ' . mysql_error() );
		
		return $mysql;
	}
	
	function getMasterData( $id, $misc = false ) {
		$mysql = getMasterMySQL();
		
		$ret = mysql_fetch_assoc(
			mysql_query(
				'SELECT `edittype`,
				`editid`,
				`comment`,
				`user`,
				`user_edit_count`,
				`user_distinct_pages`,
				`user_warns`,
				`prev_user`,
				UNIX_TIMESTAMP( `user_reg_time` ) AS `user_reg_time_unix`,
				UNIX_TIMESTAMP( `common_page_made_time` ) AS `common_page_made_time_unix`,
				`common_title`,
				`common_namespace`,
				`common_creator`,
				`common_num_recent_edits`,
				`common_num_recent_reversions`,
				`current_minor`,
				UNIX_TIMESTAMP( `current_timestamp` ) AS `current_timestamp_unix`,
				`current_text`,
				UNIX_TIMESTAMP( `previous_timestamp` ) AS `previous_timestamp_unix`,
				`previous_text`,
				`isvandalism`,
				`isactive`,
				`source`,
				UNIX_TIMESTAMP( `updated` ) AS `updated_unix`,
				`reviewers`,
				`reviewers_agreeing` FROM `editset` WHERE `editid` = \'' . mysql_real_escape_string( $id ) . '\''
			)
		);
		
		$data = Array(
			'EditType' => $ret[ 'edittype' ],
			'EditID' => $ret[ 'editid' ],
			'comment' => $ret[ 'comment' ],
			'user' => $ret[ 'user' ],
			'user_edit_count' => $ret[ 'user_edit_count' ],
			'user_distinct_pages' => $ret[ 'user_distinct_pages' ],
			'user_warns' => $ret[ 'user_warns' ],
			'prev_user' => $ret[ 'prev_user' ],
			'user_reg_time' => $ret[ 'user_reg_time_unix' ],
			'common' => Array(
				'page_made_time' => $ret[ 'common_page_made_time_unix' ],
				'title' => $ret[ 'common_title' ],
				'namespace' => $ret[ 'common_namespace' ],
				'creator' => $ret[ 'common_creator' ],
				'num_recent_edits' => $ret[ 'common_num_recent_edits' ],
				'num_recent_reversions' => $ret[ 'common_num_recent_reversions' ]
			),
			'current' => Array(
				'minor' => $ret[ 'current_minor' ] ? 'true' : 'false',
				'timestamp' => $ret[ 'current_timestamp_unix' ],
				'text' => $ret[ 'current_text' ]
			),
			'previous' => Array(
				'timestamp' => $ret[ 'previous_timestamp_unix' ],
				'text' => $ret[ 'previous_text' ]
			)
		);
		
		if( $misc ) {
			$data[ 'EditDB' ] = Array(
				'isActive' => $ret[ 'isactive' ] ? 'true' : 'false',
				'source' => $ret[ 'source' ],
				'lastUpdated' => $ret[ 'updated_unix' ]
			);
			
			$data[ 'isVandalism' ] = $ret[ 'isvandalism' ] ? 'true' : 'false';
			
			$data[ 'ReviewInterface' ] = Array(
				'reviewers' => $ret[ 'reviewers' ],
				'reviewers_agreeing' => $ret[ 'reviewers_agreeing' ]
			);
		}
		
		return $data;
	}

	function insertEdit( $id, $isVand, $isActive, $reviewers, $reviewers_agreeing, $source, $data = null ) {
		$mysql = getMasterMySQL();
		
		if( $data === null )
			$data = oldData( $id );
		if( $data === false ) {
			echo 'Bad revid.  Not inserted.';
			return false;
		}
		
		$query = 'REPLACE INTO `editset` (
				`edittype`,
				`editid`,
				`comment`,
				`user`,
				`user_edit_count`,
				`user_distinct_pages`,
				`user_warns`,
				`prev_user`,
				`user_reg_time`,
				`common_page_made_time`,
				`common_title`,
				`common_namespace`,
				`common_creator`,
				`common_num_recent_edits`,
				`common_num_recent_reversions`,
				`current_minor`,
				`current_timestamp`,
				`current_text`,
				`previous_timestamp`,
				`previous_text`,
				`isvandalism`,
				`isactive`,
				`source`,
				`reviewers`,
				`reviewers_agreeing`
			) VALUES (';
		
		$escaped = Array(
			$data[ 'EditType' ],
			$data[ 'EditID' ],
			$data[ 'comment' ],
			$data[ 'user' ],
			$data[ 'user_edit_count' ],
			$data[ 'user_distinct_pages' ],
			$data[ 'user_warns' ],
			$data[ 'prev_user' ],
			$data[ 'user_reg_time' ],
			$data[ 'common' ][ 'page_made_time' ],
			$data[ 'common' ][ 'title' ],
			$data[ 'common' ][ 'namespace' ],
			$data[ 'common' ][ 'creator' ],
			$data[ 'common' ][ 'num_recent_edits' ],
			$data[ 'common' ][ 'num_recent_reversions' ],
			$data[ 'current' ][ 'minor' ] == 'true' ? 1 : 0,
			$data[ 'current' ][ 'timestamp' ],
			$data[ 'current' ][ 'text' ],
			$data[ 'previous' ][ 'timestamp' ],
			$data[ 'previous' ][ 'text' ],
			$isVand ? 1 : 0,
			$isActive ? 1 : 0,
			$source,
			$reviewers,
			$reviewers_agreeing
		);
		
		foreach( $escaped as $key => &$value )
			if( $key == 8 or $key == 9 or $key == 16 or $key == 18 )
				$value = 'FROM_UNIXTIME( \'' . mysql_real_escape_string( $value ) . '\' )';
			else
				$value = '\'' . mysql_real_escape_string( $value ) . '\'';
		
		$query .= implode( ',', $escaped );
		$query .= ' )';
		
		$ret = mysql_query( $query );
		if( !$ret ) {
			echo 'Error inserting ' . $id . ': ' . mysql_error();
			return false;
		}
		return true;
	}
?>