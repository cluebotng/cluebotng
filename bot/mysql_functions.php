<?php
namespace CluebotNG;

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
    if (!Globals::$legacy_mysql or !mysqli_ping(Globals::$legacy_mysql)) {
        Globals::$legacy_mysql = mysqli_connect(
            'p:' . Config::$legacy_mysql_host,
            Config::$legacy_mysql_user,
            Config::$legacy_mysql_pass,
            Config::$legacy_mysql_db,
            Config::$legacy_mysql_port
        );
        mysqli_select_db(Globals::$legacy_mysql, Config::$legacy_mysql_db);
    }
}

function checkMySQL()
{
    if (!Globals::$cb_mysql or !mysqli_ping(Globals::$cb_mysql)) {
        Globals::$cb_mysql = mysqli_connect(
            'p:' . Config::$cb_mysql_host,
            Config::$cb_mysql_user,
            Config::$cb_mysql_pass,
            Config::$cb_mysql_db,
            Config::$cbmysql_port
        );
        mysqli_select_db(Globals::$cb_mysql, Config::$cb_mysql_db);
    }
}

function checkRepMySQL()
{
    if (!Globals::$mw_mysql or !mysqli_ping(Globals::$mw_mysql)) {
        Globals::$mw_mysql = mysqli_connect(
            'p:' . Config::$mw_mysql_host,
            Config::$mw_mysql_user,
            Config::$mw_mysql_pass,
            Config::$mw_mysql_db,
            Config::$mw_mysql_port

        );
        mysqli_select_db(Globals::$mw_mysql, Config::$mw_mysql_db);
    }
}

function getCbData($user = '', $nsid = '', $title = '', $timestamp = '')
{
    checkRepMySQL();
    $userPage = str_replace(' ', '_', $user);
    $title = str_replace(' ', '_', $title);
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
    $res = mysqli_query(
        Globals::$mw_mysql,
        'SELECT `rev_timestamp`, `rev_user_text` ' .
        'FROM `page` JOIN `revision` ON `rev_page` = `page_id`' .
        ' WHERE `page_namespace` = "' .
        mysqli_real_escape_string(Globals::$mw_mysql, $nsid) .
        '" AND `page_title` = "' .
        mysqli_real_escape_string(Globals::$mw_mysql, $title) .
        '" ORDER BY `rev_id` LIMIT 1'
    );
    if($res !== false) {
        $d = mysqli_fetch_assoc($res);
        $data['common']['page_made_time'] = $d['rev_timestamp'];
        $data['common']['creator'] = $d['rev_user_text'];
    }
    $res = mysqli_query(
        Globals::$mw_mysql,
        'SELECT COUNT(*) as count FROM `page` JOIN `revision` ON ' .
        '`rev_page` = `page_id` WHERE `page_namespace` = "' .
        mysqli_real_escape_string(Globals::$mw_mysql, $nsid) .
        '" AND `page_title` = "' .
        mysqli_real_escape_string(Globals::$mw_mysql, $title) .
        '" AND `rev_timestamp` > "' .
        mysqli_real_escape_string(Globals::$mw_mysql, $timestamp) . '"'
    );
    if($res !== false) {
        $d = mysqli_fetch_assoc($res);
        $data['common']['num_recent_edits'] = $d['count'];
    }
    $res = mysqli_query(
        Globals::$mw_mysql,
        'SELECT COUNT(*) as count FROM `page` JOIN `revision` ON `rev_page` ' .
        "= `page_id` WHERE `page_namespace` = '" .
        mysqli_real_escape_string(Globals::$mw_mysql, $nsid) .
        "' AND `page_title` = '" .
        mysqli_real_escape_string(Globals::$mw_mysql, $title) .
        "' AND `rev_timestamp` > '" .
        mysqli_real_escape_string(Globals::$mw_mysql, $timestamp) .
        "' AND `rev_comment` LIKE 'Revert%'"
    );
    if($res !== false) {
        $d = mysqli_fetch_assoc($res);
        $data['common']['num_recent_reversions'] = $d['count'];
    }
    if (filter_var($user, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ||
        filter_var($user, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
    ) {
        $res = mysqli_query(Globals::$mw_mysql, 'SELECT UNIX_TIMESTAMP() AS `user_regtime`');
        if($res !== false) {
            $d = mysqli_fetch_assoc($res);
            $data['user_reg_time'] = $d['user_regtime'];
        }
        $res = mysqli_query(
            Globals::$mw_mysql,
            'SELECT COUNT(*) AS `user_editcount` FROM `revision_userindex` ' .
            ' WHERE `rev_user_text` = "' .
            mysqli_real_escape_string(Globals::$mw_mysql, $user) . '"'
        );
        if($res !== false) {
            $d = mysqli_fetch_assoc($res);
            $data['user_edit_count'] = $d['user_editcount'];
        }
    } else {
        $res = mysqli_query(
            Globals::$mw_mysql,
            'SELECT `user_registration` FROM `user` WHERE `user_name` = "' .
            mysqli_real_escape_string(Globals::$mw_mysql, $user) . '"'
        );
        $d = mysqli_fetch_assoc($res);
        if($res !== false) {
            $data['user_reg_time'] = $d['user_registration'];
        }
        if (!$data['user_reg_time']) {
            $res = mysqli_query(
                Globals::$mw_mysql,
                'SELECT `rev_timestamp` FROM `revision_userindex` WHERE `rev_user` = "' .
                mysqli_real_escape_string(Globals::$mw_mysql, $user) . '" ORDER BY `rev_timestamp` LIMIT 0,1'
            );
            if($res !== false) {
                $d = mysqli_fetch_assoc($res);
                $data['user_reg_time'] = $d['rev_timestamp'];
            }
        }
        $res = mysqli_query(
            Globals::$mw_mysql,
            'SELECT `user_editcount` FROM `user` WHERE `user_name` =  "' .
            mysqli_real_escape_string(Globals::$mw_mysql, $user) . '"'
        );
        if($res !== false) {
            $d = mysqli_fetch_assoc($res);
            $data['user_edit_count'] = $d['user_editcount'];
        }
    }
    $res = mysqli_query(
        Globals::$mw_mysql,
        'SELECT COUNT(*) as count FROM `page` JOIN `revision` ON `rev_page` = `page_id`' .
        " WHERE `page_namespace` = 3 AND `page_title` = '" .
        mysqli_real_escape_string(Globals::$mw_mysql, $userPage) .
        "' AND (`rev_comment` LIKE '%warning%' OR `rev_comment`" .
        " LIKE 'General note: Nonconstructive%')"
    );
    if($res !== false) {
        $d = mysqli_fetch_assoc($res);
        $data['user_warns'] = $d['count'];
    }
    $res = mysqli_query(
        Globals::$mw_mysql,
        "select count(distinct rev_page) as count from ' .
        'revision_userindex where `rev_user_text` = '" . mysqli_real_escape_string(Globals::$mw_mysql, $userPage) . "'"
    );
    if($res !== false) {
        $d = mysqli_fetch_assoc($res);
        $data['user_distinct_pages'] = $d['count'];
    }
    if ($data['common']['page_made_time']) {
        $data['common']['page_made_time'] = gmmktime(
            substr($data['common']['page_made_time'], 8, 2),
            substr($data['common']['page_made_time'], 10, 2),
            substr($data['common']['page_made_time'], 12, 2),
            substr($data['common']['page_made_time'], 4, 2),
            substr($data['common']['page_made_time'], 6, 2),
            substr($data['common']['page_made_time'], 0, 4)
        );
    }
    if ($data['user_reg_time']) {
        $data['user_reg_time'] = gmmktime(
            substr($data['user_reg_time'], 8, 2),
            substr($data['user_reg_time'], 10, 2),
            substr($data['user_reg_time'], 12, 2),
            substr($data['user_reg_time'], 4, 2),
            substr($data['user_reg_time'], 6, 2),
            substr($data['user_reg_time'], 0, 4)
        );
    }

    return $data;
}
