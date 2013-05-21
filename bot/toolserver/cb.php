<?php
    set_time_limit(10);

    $cfg = Array();
    $cnf = explode("\n", file_get_contents( '/data/project/cluebot/replica.my.cnf' ));
    foreach($cnf as $line) {
        if($line && $line[0] == '[') {
            $section = &$cfg[substr($line, 1, -1)];
        } else if($line && $line[0] != ';') {
            $data = explode('=', $line, 2);
            $section[trim($data[0] )] = trim( str_replace( "'", '', $data[1] ));
        }
    }

    // Arguments
    $namespace = (array_key_exists('ns', $_GET)) ? $_GET['ns'] : '';
    $title = str_replace(' ', '_', (array_key_exists('title', $_GET)) ? $_GET['title'] : '');
    $recent = gmdate('YmdHis', time() - 14*86400);
    $user = (array_key_exists('user', $_GET)) ? $_GET['user'] : '';
    $userPage = str_replace(' ', '_', $user);

    // Data
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

    $db = new mysqli('enwiki.labsdb', $cfg['client']['user'], $cfg['client']['password'], 'enwiki_p');

    // Revision create time/creator
    $q1 = $db->prepare('SELECT `rev_timestamp`, `rev_user_text` FROM `page` JOIN `revision` ON `rev_page` = `page_id` WHERE `page_namespace` = ? AND `page_title` = ? ORDER BY `rev_id` LIMIT 1');
    $q1->bind_param('ss', $namespace, $title);
    $q1->execute();
    $q1->bind_result($data['common']['page_made_time'], $data['common']['creator']);
    $q1->fetch();
    $q1->close();

    // Recent page edits
    $q1 = $db->prepare('SELECT COUNT(*) FROM `page` JOIN `revision` ON `rev_page` = `page_id` WHERE `page_namespace` = ? AND `page_title` = ? AND `rev_timestamp` > ?');
    $q1->bind_param('sss', $namespace, $title, $recent);
    $q1->execute();
    $q1->bind_result($data['common']['num_recent_edits']);
    $q1->fetch();
    $q1->close();

    // Recent page reverts
    $q1 = $db->prepare("SELECT COUNT(*) FROM `page` JOIN `revision` ON `rev_page` = `page_id` WHERE `page_namespace` = ? AND `page_title` = ? AND `rev_timestamp` > ? AND `rev_comment` LIKE 'Revert%'");
    $q1->bind_param('sss', $namespace, $title, $recent);
    $q1->execute();
    $q1->bind_result($data['common']['num_recent_reversions']);
    $q1->fetch();
    $q1->close();

    // If anon (ip)
    if(long2ip(ip2long($user)) == $user) {
        // User registration time
        $q1 = $db->prepare('SELECT UNIX_TIMESTAMP() AS `user_editcount`');
        $q1->execute();
        $q1->bind_result($data['user_reg_time']);
        $q1->fetch();
        $q1->close();

        // User edit count
        $q1 = $db->prepare('SELECT COUNT(*) AS `user_editcount` FROM `revision_userindex` WHERE `rev_user_text` = ?');
        $q1->bind_param('s', $user);
        $q1->execute();
        $q1->bind_result($data['user_edit_count']);
        $q1->fetch();
        $q1->close();

    // User
    } else {
        // User registration time
        $q1 = $db->prepare('SELECT `user_registration` FROM `user` WHERE `user_name` = ?');
        $q1->bind_param('s', $user);
        $q1->execute();
        $q1->bind_result($data['user_reg_time']);
        $q1->fetch();
        $q1->close();

        // User edit count
        $q1 = $db->prepare('SELECT `user_editcount` FROM `user` WHERE `user_name` = ?');
        $q1->bind_param('s', $user);
        $q1->execute();
        $q1->bind_result($data['user_edit_count']);
        $q1->fetch();
        $q1->close();
    }

    // User warnings
    $q1 = $db->prepare("SELECT COUNT(*) FROM `page` JOIN `revision` ON `rev_page` = `page_id` WHERE `page_namespace` = 3 AND `page_title` = ? AND (`rev_comment` LIKE '%warning%' OR `rev_comment` LIKE 'General note: Nonconstructive%')");
    $q1->bind_param('s', $userPage);
    $q1->execute();
    $q1->bind_result($data['user_warns']);
    $q1->fetch();
    $q1->close();

    // User distinct pages
    $q1 = $db->prepare('select count(*) from (select distinct rev_page from revision_userindex where `rev_user_text` = ?) as a');
    $q1->bind_param('s', $user);
    $q1->execute();
    $q1->bind_result($data['user_distinct_pages']);
    $q1->fetch();
    $q1->close();

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

    $db->close();
    die(serialize($data));
?>
