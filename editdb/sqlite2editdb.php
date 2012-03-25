<?PHP
	function destringify( $string ) {
		return stripcslashes( substr( $string, 1, -1 ) );
	}
	
/*	function insertIntoDatabase( $source, $isVandalism, $data ) {
		global $mysql;
		$queryStr = <<<'EOQ'
			INSERT INTO `editset` (
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
			) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME( ? ), FROM_UNIXTIME( ? ), ?, ?, ?, ?, ?, ?, FROM_UNIXTIME( ? ), ?, FROM_UNIXTIME( ? ), ?, ?, 1, ?, NULL, NULL )
EOQ;
		$query = $mysql->prepare( $queryStr );
		if( !$query )
			echo 'Something went wrong in preperation:  ' . $mysql->error . "\n";
		$minor = $data->current->minor == 'true' ? 1 : 0;
		$vandalism = $isVandalism ? 1 : 0;
		$curText = null;
		$prevText = null;
		$query->bind_param(
			'sissiiisiisssiiiibibis',
			$data->EditType,
			$data->EditID,
			$data->comment,
			$data->user,
			$data->user_edit_count,
			$data->user_distinct_pages,
			$data->user_warns,
			$data->prev_user,
			$data->user_reg_time,
			$data->common->page_made_time,
			$data->common->title,
			$data->common->namespace,
			$data->common->creator,
			$data->common->num_recent_edits,
			$data->common->num_recent_reversions,
			$minor,
			$data->current->timestamp,
			$curText,
			$data->previous->timestamp,
			$prevText,
			$vandalism,
			$source
		);
		for( $i = 0 ; $i < strlen( $data->current->text ) ; $i += 8192 )
			if( !$query->send_long_data( 17, substr( $data->current->text, $i, 8192 ) ) )
				echo 'Something went wrong.  ' . $query->error . "\n";
			
		for( $i = 0 ; $i < strlen( $data->previous->text ) ; $i += 8192 )
			if( !$query->send_long_data( 19, substr( $data->previous->text, $i, 8192 ) ) )
				echo 'Something went wrong.  ' . $query->error . "\n";
		
		if( !$query->execute() )
			echo 'Something went wrong.  ' . $query->error . "\n";
	}*/

	function insertIntoDatabase( $source, $isVandalism, $data ) {
		$query = 'INSERT INTO `editset` (
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
			$data->EditType,
			$data->EditID,
			$data->comment,
			$data->user,
			$data->user_edit_count,
			$data->user_distinct_pages,
			$data->user_warns,
			$data->prev_user,
			$data->user_reg_time,
			$data->common->page_made_time,
			$data->common->title,
			$data->common->namespace,
			$data->common->creator,
			$data->common->num_recent_edits,
			$data->common->num_recent_reversions,
			$data->current->minor == 'true' ? 1 : 0,
			$data->current->timestamp,
			$data->current->text,
			$data->previous->timestamp,
			$data->previous->text,
			$isVandalism ? 1 : 0,
			1,
			$source
		);
		foreach( $escaped as $key => &$value )
			if( $key == 8 or $key == 9 or $key == 16 or $key == 18 )
				$value = 'FROM_UNIXTIME( \'' . mysql_real_escape_string( $value ) . '\' )';
			else
				$value = '\'' . mysql_real_escape_string( $value ) . '\'';
		
		$query .= implode( ',', $escaped );
		$query .= ', NULL, NULL )';
		if( !mysql_query( $query ) )
			echo 'An error occurred: ' . mysql_error() . "\n";
	}
	
	function parseEntry( $entry ) {
		$entry[ 'EditXML' ] = destringify( $entry[ 'EditXML' ] );
		$xml = gzuncompress( $entry[ 'EditXML' ] );
		$xml = new SimpleXMLElement( $xml );
		
		//print_r( $xml );
		insertIntoDatabase( $entry[ 'EditSource' ], $entry[ 'IsVandalism' ], $xml );
	}

	$sqlitedb = '/home/cobi/alldataset.sqlite';
	
	//$mysql = new MySQLi( '192.168.2.11', 'root', '********', 'cbng_editdb_master' );
	$mysql = mysql_connect( 'localhost', 'cbngdb', '' );
	if( !$mysql )
		die( 'Error: ' . mysql_error() );
	if( !mysql_select_db( 'cbng_editdb_master', $mysql ) )
		die( 'Error; ' . mysql_error() );
	$sqlite = new SQLite3( $sqlitedb );
	$query = $sqlite->query( 'SELECT EditID, EditXML, IsVandalism, EditSource FROM dataset' );
	
	$count = 0;
	
	while( $entry = $query->fetchArray( SQLITE3_ASSOC ) ) {
		parseEntry( $entry );
		$count++;
		if( $count % 100 == 0 )
			echo 'Processed ' . $count . '... ' . "\n";
	}
?>