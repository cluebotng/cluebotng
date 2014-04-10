<?PHP
	function checkMySQL() {
		if( !Globals::$mysql or !mysql_ping( Globals::$mysql ) ) {
			Globals::$mysql = mysql_pconnect( Config::$mysqlhost . ':' . Config::$mysqlport, Config::$mysqluser, Config::$mysqlpass );
			mysql_select_db( Config::$mysqldb, Globals::$mysql );
		}
	}

	function checkRepMySQL() {
		if( !Globals::$rep_mysql or !mysql_ping( Globals::$rep_mysql ) ) {
			Globals::$rep_mysql = mysql_pconnect( Config::$rep_mysqlhost . ':' . Config::$rep_mysqlport, Config::$rep_mysqluser, Config::$rep_mysqlpass );
			mysql_select_db( Config::$rep_mysqldb, Globals::$rep_mysql );
		}
	}

	function getCbData($user='', $nsid='', $title='', $timestamp='') {
		checkRepMySQL();
		$userPage = str_replace(' ', '_', $user);
		$title = str_replace(' ', '_', $title);
		$recent = gmdate('YmdHis', time() - 14*86400);

		$data = array(
				'common' => Array(
			'creator' => False,
			'page_made_time' => False,
			'num_recent_edits' => False,
			'num_recent_reversions' => False,
				),
				'user_reg_time' => False,
				'user_warns' => False,
				'user_edit_count' => False,
				'user_distinct_pages' => False,
		);

		$res = mysql_query( 'SELECT `rev_timestamp`, `rev_user_text` FROM `page` JOIN `revision` ON `rev_page` = `page_id`' .
				' WHERE `page_namespace` = "' .
				mysql_real_escape_string($nsid) .
				'" AND `page_title` = "' .
				mysql_real_escape_string($title) .
				'" ORDER BY `rev_id` LIMIT 1', Globals::$rep_mysql );
		$d = mysql_fetch_assoc( $res );
		$data['common']['page_made_time'] = $d['rev_timestamp'];
		$data['common']['creator'] = $d['rev_user_text'];

		$res = mysql_query( 'SELECT COUNT(*) as count FROM `page` JOIN `revision` ON ' .
					'`rev_page` = `page_id` WHERE `page_namespace` = "' .
					mysql_real_escape_string($nsid) .
					'" AND `page_title` = "' .
					mysql_real_escape_string($title) .
					'" AND `rev_timestamp` > "' .
					mysql_real_escape_string($timestamp) . '"', Globals::$rep_mysql );
		$d = mysql_fetch_assoc( $res );
		$data['common']['num_recent_edits'] = $d['count'];

		$res = mysql_query( "SELECT COUNT(*) as count FROM `page` JOIN `revision` ON `rev_page` " .
					"= `page_id` WHERE `page_namespace` = '" .
					mysql_real_escape_string($nsid) .
					"' AND `page_title` = '" .
					mysql_real_escape_string($title) .
					"' AND `rev_timestamp` > '" .
					mysql_real_escape_string($timestamp) .
					"' AND `rev_comment` LIKE 'Revert%'", Globals::$rep_mysql );
		$d = mysql_fetch_assoc( $res );
		$data['common']['num_recent_reversions'] = $d['count'];

		// If anon
		if( filter_var( $user, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) || filter_var( $user, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$res = mysql_query( 'SELECT UNIX_TIMESTAMP() AS `user_regtime`', Globals::$rep_mysql );
			$d = mysql_fetch_assoc( $res );
			$data['user_reg_time'] = $d['user_regtime'];

			$res = mysql_query( 'SELECT COUNT(*) AS `user_editcount` FROM `revision_userindex` ' .
						' WHERE `rev_user_text` = "' .
						mysql_real_escape_string($user) . '"', Globals::$rep_mysql );
			$d = mysql_fetch_assoc( $res );
			$data['user_edit_count'] = $d['user_editcount'];

		} else {
			$res = mysql_query( 'SELECT `user_registration` FROM `user` WHERE `user_name` = "' .
						mysql_real_escape_string($user) . '"', Globals::$rep_mysql );
			$d = mysql_fetch_assoc( $res );
			$data['user_reg_time'] = $d['user_registration'];

			if( ! $data['user_reg_time'] ) {
				$res = mysql_query( 'SELECT `rev_timestamp` FROM `revision_userindex` WHERE `rev_user` = "' .
							mysql_real_escape_string($user) . '" ORDER BY `rev_timestamp` LIMIT 0,1', Globals::$rep_mysql );
				$d = mysql_fetch_assoc( $res );
				$data['user_reg_time'] = $d['rev_timestamp'];
			}

			$res = mysql_query( 'SELECT `user_editcount` FROM `user` WHERE `user_name` =  "' .
						mysql_real_escape_string($user) . '"', Globals::$rep_mysql );
			$d = mysql_fetch_assoc( $res );
			$data['user_edit_count'] = $d['user_editcount'];
		}


		$res = mysql_query( "SELECT COUNT(*) as count FROM `page` JOIN `revision` ON `rev_page` = `page_id`" .
				   			" WHERE `page_namespace` = 3 AND `page_title` = '" .
					mysql_real_escape_string($userPage) .
					"' AND (`rev_comment` LIKE '%warning%' OR `rev_comment`" .
					" LIKE 'General note: Nonconstructive%')", Globals::$rep_mysql );
		$d = mysql_fetch_assoc( $res );
		$data['user_warns'] = $d['count'];

		$res = mysql_query( "select count(distinct rev_page) as count from revision_userindex where `rev_user_text` = '" .
					mysql_real_escape_string($userPage) . "'",  Globals::$rep_mysql );
		$d = mysql_fetch_assoc( $res );
		$data['user_distinct_pages'] = $d['count'];

		if($data['common']['page_made_time']) {
				$data['common']['page_made_time'] = gmmktime(substr($data['common']['page_made_time'], 8, 2),
										substr($data['common']['page_made_time'], 10, 2),
										substr($data['common']['page_made_time'], 12, 2),
										substr($data['common']['page_made_time'], 4, 2),
										substr($data['common']['page_made_time'], 6, 2),
										substr($data['common']['page_made_time'], 0, 4));
		}

		if($data['user_reg_time']) {
			$data['user_reg_time'] = gmmktime(substr($data['user_reg_time'], 8, 2),
								substr($data['user_reg_time'], 10, 2),
								substr($data['user_reg_time'], 12, 2),
								substr($data['user_reg_time'], 4, 2),
								substr($data['user_reg_time'], 6, 2),
								substr($data['user_reg_time'], 0, 4));
		}

		return $data;
	}
