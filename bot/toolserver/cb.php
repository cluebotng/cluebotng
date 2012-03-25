<?PHP
	$cfg = Array();
	$cnf = explode( "\n", file_get_contents( '/home/cobi/.my.cnf' ) );
	foreach( $cnf as $line )
		if( $line[ 0 ] == '[' )
			$section = &$cfg[ substr( $line, 1, -1 ) ];
		else if( $line[ 0 ] != ';' ) {
			$data = explode( '=', $line, 2 );
			$section[ trim( $data[ 0 ] ) ] = trim( str_replace( '"', '', $data[ 1 ] ) );
		}
	$namespace = $_GET[ 'ns' ];
	$title = $_GET[ 'title' ];
	$title = str_replace( ' ', '_', $title );
	$recent = gmdate( 'YmdHis', time() - 14*86400 );
	$user = $_GET[ 'user' ];
	$userPage = str_replace( ' ', '_', $user );
	
	$db = new mysqli( 'sql-s1', $cfg[ 'client' ][ 'user' ], $cfg[ 'client' ][ 'password' ], 'enwiki_p' );
	
	$query = 'SELECT `rev_timestamp`, `rev_user_text`, (SELECT COUNT(*) FROM `page` JOIN `revision` ON `rev_page` = `page_id` WHERE `page_namespace` = ? AND `page_title` = ? AND `rev_timestamp` > ?), (SELECT COUNT(*) FROM `page` JOIN `revision` ON `rev_page` = `page_id` WHERE `page_namespace` = ? AND `page_title` = ? AND `rev_timestamp` > ? AND `rev_comment` LIKE \'Revert%\'), (SELECT COUNT(*) FROM `page` JOIN `revision` ON `rev_page` = `page_id` WHERE `page_namespace` = 3 AND `page_title` = ? AND (`rev_comment` LIKE \'%warning%\' OR `rev_comment` LIKE \'General note: Nonconstructive%\')), (SELECT COUNT(*) FROM (SELECT DISTINCT `rev_page` FROM `revision` WHERE `rev_user_text` = ?) AS `foo`), `user_editcount`, `user_registration` FROM (SELECT `rev_timestamp`, `rev_user_text` FROM `page` JOIN `revision` ON `rev_page` = `page_id` WHERE `page_namespace` = ? AND `page_title` = ? ORDER BY `rev_id` LIMIT 1) AS `a`, (';
	if( long2ip( ip2long( $user ) ) == $user )
		$query .= 'SELECT COUNT(*) AS `user_editcount`, UNIX_TIMESTAMP() AS `user_registration` FROM `revision` WHERE `rev_user_text` = ?';
	else
		$query .= 'SELECT `user_editcount`, `user_registration` FROM `user` WHERE `user_name` = ?';
	$query .= ') AS `b`';
	
	$stmt = $db->prepare( $query );
	$stmt->bind_param( 'sssssssssss', $namespace, $title, $recent, $namespace, $title, $recent, $userPage, $user, $namespace, $title, $user );
	$stmt->execute();
	$stmt->bind_result( $time, $creator, $recentEdits, $recentReverts, $warnings, $distinctPages, $editCount, $regTime );
	$stmt->fetch();
	$stmt->close();
	$db->close();
	
	if( long2ip( ip2long( $user ) ) != $user )
		$regTime = gmmktime( substr( $regTime, 8, 2 ), substr( $regTime, 10, 2 ), substr( $regTime, 12, 2 ), substr( $regTime, 4, 2 ), substr( $regTime, 6, 2 ), substr( $regTime, 0, 4 ) );
	
	$time = gmmktime( substr( $time, 8, 2 ), substr( $time, 10, 2 ), substr( $time, 12, 2 ), substr( $time, 4, 2 ), substr( $time, 6, 2 ), substr( $time, 0, 4 ) );
	
	echo serialize(
		Array(
			'common' => Array(
				'creator' => $creator,
				'page_made_time' => $time,
				'num_recent_edits' => $recentEdits,
				'num_recent_reversions' => $recentReverts
			),
			'user_reg_time' => $regTime,
			'user_warns' => $warnings,
			'user_edit_count' => $editCount,
			'user_distinct_pages' => $distinctPages
		)
	);

?>
