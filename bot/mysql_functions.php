<?php

/*
 * Copyright (C) 2015 Jacobi Carter and Chris Breneman
 *
 * This file is part of ClueBot NG.
 *
 * ClueBot NG is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * ClueBot NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ClueBot NG.  If not, see <http://www.gnu.org/licenses/>.
 */
    function checkLegacyMySQL()
    {
        if (!Globals::$legacy_mysql or !mysql_ping(Globals::$legacy_mysql)) {
            Globals::$legacy_mysql = mysql_pconnect(Config::$legacy_mysql_host.':'.Config::$legacy_mysql_port, Config::$legacy_mysql_user, Config::$legacy_mysql_pass);
            mysql_select_db(Config::$legacy_mysql_db, Globals::$legacy_mysql);
        }
    }
    function checkMySQL()
    {
        if (!Globals::$cb_mysql or !mysql_ping(Globals::$cb_mysql)) {
            Globals::$cb_mysql = mysql_pconnect(Config::$cb_mysql_host.':'.Config::$cbmysql_port, Config::$cb_mysql_user, Config::$cb_mysql_pass);
            mysql_select_db(Config::$cb_mysql_db, Globals::$cb_mysql);
        }
    }
    function checkRepMySQL()
    {
        if (!Globals::$mw_mysql or !mysql_ping(Globals::$mw_mysql)) {
            Globals::$mw_mysql = mysql_pconnect(Config::$mw_mysql_host.':'.Config::$mw_mysql_port, Config::$mw_mysql_user, Config::$mw_mysqlpass);
            mysql_select_db(Config::$mw_mysql_db, Globals::$mw_mysql);
        }
    }
    function getCbData($user = '', $nsid = '', $title = '', $timestamp = '')
    {
        checkRepMySQL();
        $userPage = str_replace(' ', '_', $user);
        $title = str_replace(' ', '_', $title);
        $recent = gmdate('YmdHis', time() - 14 * 86400);
        $data = array(
                'common' => array(
            'creator' => false,
            'page_made_time' => false,
            'num_recent_edits' => false,
            'num_recent_reversions' => false,
                ),
                'user_reg_time' => false,
                'user_warns' => false,
                'user_edit_count' => false,
                'user_distinct_pages' => false,
        );
        $res = mysql_query('SELECT `rev_timestamp`, `rev_user_text` FROM `page` JOIN `revision` ON `rev_page` = `page_id`'.
                ' WHERE `page_namespace` = "'.
                mysql_real_escape_string($nsid).
                '" AND `page_title` = "'.
                mysql_real_escape_string($title).
                '" ORDER BY `rev_id` LIMIT 1', Globals::$mw_mysql);
        $d = mysql_fetch_assoc($res);
        $data['common']['page_made_time'] = $d['rev_timestamp'];
        $data['common']['creator'] = $d['rev_user_text'];
        $res = mysql_query('SELECT COUNT(*) as count FROM `page` JOIN `revision` ON '.
                    '`rev_page` = `page_id` WHERE `page_namespace` = "'.
                    mysql_real_escape_string($nsid).
                    '" AND `page_title` = "'.
                    mysql_real_escape_string($title).
                    '" AND `rev_timestamp` > "'.
                    mysql_real_escape_string($timestamp).'"', Globals::$mw_mysql);
        $d = mysql_fetch_assoc($res);
        $data['common']['num_recent_edits'] = $d['count'];
        $res = mysql_query('SELECT COUNT(*) as count FROM `page` JOIN `revision` ON `rev_page` '.
                    "= `page_id` WHERE `page_namespace` = '".
                    mysql_real_escape_string($nsid).
                    "' AND `page_title` = '".
                    mysql_real_escape_string($title).
                    "' AND `rev_timestamp` > '".
                    mysql_real_escape_string($timestamp).
                    "' AND `rev_comment` LIKE 'Revert%'", Globals::$mw_mysql);
        $d = mysql_fetch_assoc($res);
        $data['common']['num_recent_reversions'] = $d['count'];
        if (filter_var($user, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) || filter_var($user, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $res = mysql_query('SELECT UNIX_TIMESTAMP() AS `user_regtime`', Globals::$mw_mysql);
            $d = mysql_fetch_assoc($res);
            $data['user_reg_time'] = $d['user_regtime'];
            $res = mysql_query('SELECT COUNT(*) AS `user_editcount` FROM `revision_userindex` '.
                        ' WHERE `rev_user_text` = "'.
                        mysql_real_escape_string($user).'"', Globals::$mw_mysql);
            $d = mysql_fetch_assoc($res);
            $data['user_edit_count'] = $d['user_editcount'];
        } else {
            $res = mysql_query('SELECT `user_registration` FROM `user` WHERE `user_name` = "'.
                        mysql_real_escape_string($user).'"', Globals::$mw_mysql);
            $d = mysql_fetch_assoc($res);
            $data['user_reg_time'] = $d['user_registration'];
            if (!$data['user_reg_time']) {
                $res = mysql_query('SELECT `rev_timestamp` FROM `revision_userindex` WHERE `rev_user` = "'.
                            mysql_real_escape_string($user).'" ORDER BY `rev_timestamp` LIMIT 0,1', Globals::$mw_mysql);
                $d = mysql_fetch_assoc($res);
                $data['user_reg_time'] = $d['rev_timestamp'];
            }
            $res = mysql_query('SELECT `user_editcount` FROM `user` WHERE `user_name` =  "'.
                        mysql_real_escape_string($user).'"', Globals::$mw_mysql);
            $d = mysql_fetch_assoc($res);
            $data['user_edit_count'] = $d['user_editcount'];
        }
        $res = mysql_query('SELECT COUNT(*) as count FROM `page` JOIN `revision` ON `rev_page` = `page_id`'.
                            " WHERE `page_namespace` = 3 AND `page_title` = '".
                    mysql_real_escape_string($userPage).
                    "' AND (`rev_comment` LIKE '%warning%' OR `rev_comment`".
                    " LIKE 'General note: Nonconstructive%')", Globals::$mw_mysql);
        $d = mysql_fetch_assoc($res);
        $data['user_warns'] = $d['count'];
        $res = mysql_query("select count(distinct rev_page) as count from revision_userindex where `rev_user_text` = '".
                    mysql_real_escape_string($userPage)."'",  Globals::$mw_mysql);
        $d = mysql_fetch_assoc($res);
        $data['user_distinct_pages'] = $d['count'];
        if ($data['common']['page_made_time']) {
            $data['common']['page_made_time'] = gmmktime(substr($data['common']['page_made_time'], 8, 2),
                                        substr($data['common']['page_made_time'], 10, 2),
                                        substr($data['common']['page_made_time'], 12, 2),
                                        substr($data['common']['page_made_time'], 4, 2),
                                        substr($data['common']['page_made_time'], 6, 2),
                                        substr($data['common']['page_made_time'], 0, 4));
        }
        if ($data['user_reg_time']) {
            $data['user_reg_time'] = gmmktime(substr($data['user_reg_time'], 8, 2),
                                substr($data['user_reg_time'], 10, 2),
                                substr($data['user_reg_time'], 12, 2),
                                substr($data['user_reg_time'], 4, 2),
                                substr($data['user_reg_time'], 6, 2),
                                substr($data['user_reg_time'], 0, 4));
        }

        return $data;
    }
