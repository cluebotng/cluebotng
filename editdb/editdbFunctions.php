<?PHP
	include 'editdbCredentials.php';
	
	function error( $str ) {
		file_put_contents( 'php://stderr', $str . "\n" );
		die();
	}

	function connectToMySQL() {
		global $mysqluser, $mysqlpass, $mysqlhost;
		
		$mysql = mysql_connect( $mysqlhost, $mysqluser, $mysqlpass );
		if( !$mysql )
			error( 'MySQL Connection Error: ' . mysql_error() );
		
		if( !mysql_select_db( 'cbng_editdb', $mysql ) )
			error( 'MySQL DB Error: ' . mysql_error() );
		
		return $mysql;
	}
	
	function queryAndStreamXML( $conditions, $randomize = false, $limit = null ) {
		$beginTime = microtime( true );
		$mysql = connectToMySQL();
		
		$conditions[] = '`isactive` = 1';
		
		$query = 'SELECT `edittype`,
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
				`reviewers_agreeing` FROM `editset` WHERE ';
		$query.= implode( ' AND ', $conditions );
		
		if( $randomize )
			$query.= ' ORDER BY RAND()';
		else if( $randomize === false and ( ( $limit !== null and strpos( ',', $limit ) === false ) or $limit === null ) ) {
			$row = mysql_fetch_assoc( mysql_query( 'SELECT COUNT(*) as `count` FROM `editset` WHERE ' . implode( ' AND ', $conditions ) ) );
			$start = rand( 0, $row[ 'count' ] - ( $limit === null ? 0 : $limit ) );
			if( $limit !== null and strpos( ',', $limit ) === false )
				$limit = $start . ',' . $limit;
			else
				$limit = $start . ',18446744073709551615';
		}
		
		if( $limit !== null )
			$query .= ' LIMIT ' . $limit;
		
		$xml = new XMLWriter();
		$xml->openURI( 'php://output' );
		$xml->setIndent( true );
		$xml->startDocument( '1.0', 'UTF-8' );
		$xml->startElement( 'WPEditSet' );
		$xml->startComment();
		$xml->startElement( 'EditDB' );
		$xml->writeElement( 'query', $query );
		$xml->writeElement( 'time', time() );
		if( function_exists( 'posix_uname' ) ) {
			$uname = posix_uname();
			$xml->startElement( 'uname' );
			foreach( $uname as $key => $value )
				$xml->writeElement( $key, $value );
			$xml->endElement();
		}
		if( function_exists( 'posix_getlogin' ) )
			$xml->writeElement( 'username', posix_getlogin() );
		$xml->endElement();
		$xml->endComment();
		
		
		$result = mysql_unbuffered_query( $query );
		
		if( !$result )
			error( 'MySQL Query Error: ' . mysql_error() . "\n" . 'Query: ' . $query );
		
		$count = 0;
		
		while( $row = mysql_fetch_assoc( $result ) ) {
			$xml->startElement( 'WPEdit' );
			
			$xml->startElement( 'EditDB' );
			$xml->writeElement( 'isActive', $row[ 'isactive' ] ? 'true' : 'false' );
			$xml->writeElement( 'source', $row[ 'source' ] );
			$xml->writeElement( 'lastUpdated', $row[ 'updated_unix' ] );
			$xml->endElement();
			
			$xml->writeElement( 'EditType', $row[ 'edittype' ] );
			$xml->writeElement( 'EditID', $row[ 'editid' ] );
			$xml->writeElement( 'comment', $row[ 'comment' ] );
			$xml->writeElement( 'user', $row[ 'user' ] );
			$xml->writeElement( 'user_edit_count', $row[ 'user_edit_count' ] );
			$xml->writeElement( 'user_distinct_pages', $row[ 'user_distinct_pages' ] );
			$xml->writeElement( 'user_warns', $row[ 'user_warns' ] );
			$xml->writeElement( 'prev_user', $row[ 'prev_user' ] );
			$xml->writeElement( 'user_reg_time', $row[ 'user_reg_time_unix' ] );
			$xml->startElement( 'common' );
			$xml->writeElement( 'page_made_time', $row[ 'common_page_made_time_unix' ] );
			$xml->writeElement( 'title', $row[ 'common_title' ] );
			$xml->writeElement( 'namespace', $row[ 'common_namespace' ] );
			$xml->writeElement( 'creator', $row[ 'common_creator' ] );
			$xml->writeElement( 'num_recent_edits', $row[ 'common_num_recent_edits' ] );
			$xml->writeElement( 'num_recent_reversions', $row[ 'common_num_recent_reversions' ] );
			$xml->endElement();
			$xml->startElement( 'current' );
			$xml->writeElement( 'minor', $row[ 'current_minor' ] ? 'true' : 'false' );
			$xml->writeElement( 'timestamp', $row[ 'current_timestamp_unix' ] );
			$xml->writeElement( 'text', $row[ 'current_text' ] );
			$xml->endElement();
			$xml->startElement( 'previous' );
			$xml->writeElement( 'timestamp', $row[ 'previous_timestamp_unix' ] );
			$xml->writeElement( 'text', $row[ 'previous_text' ] );
			$xml->endElement();
			$xml->writeElement( 'isVandalism', $row[ 'isvandalism' ] ? 'true' : 'false' );
			
			$xml->startElement( 'ReviewInterface' );
			$xml->writeElement( 'reviewers', $row[ 'reviewers' ] );
			$xml->writeElement( 'reviewers_agreeing', $row[ 'reviewers_agreeing' ] );
			$xml->endElement();
			
			$xml->endElement();
			$count++;
		}
		$xml->writeComment( 'Generated in ' . ( microtime( true ) - $beginTime ) . ' seconds.  ' . $count . ' entries returned.' );
		$xml->endElement();
		$xml->endDocument();
		$xml->flush();
	}
?>