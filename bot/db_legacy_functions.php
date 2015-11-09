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
class LegacyDb
{
    // Returns the edit it for the vandalism
    public static function detectedVandalism($user, $title, $heuristic, $reason, $url, $old_rev_id, $rev_id)
    {
        checkLegacyMySQL();
        $query = 'INSERT INTO `vandalism` '.
            '(`id`,`user`,`article`,`heuristic`,`reason`,`diff`,`old_id`,`new_id`,`reverted`) '.
            'VALUES '.
            '(NULL,\''.mysqli_real_escape_string($user).'\','.
            '\''.mysqli_real_escape_string(globals::$legacy_mysql, $title).'\','.
            '\''.mysqli_real_escape_string(globals::$legacy_mysql, $heuristic).'\','.
            '\''.mysqli_real_escape_string(globals::$legacy_mysql, $reason).'\','.
            '\''.mysqli_real_escape_string(globals::$legacy_mysql, $url).'\','.
            '\''.mysqli_real_escape_string(globals::$legacy_mysql, $old_rev_id).'\','.
            '\''.mysqli_real_escape_string(globals::$legacy_mysql, $rev_id).'\',0)';
        mysqli_query(globals::$legacy_mysql, $query);

        return mysqli_insert_id(globals::$legacy_mysql);
    }
    // Returns nothing
    public static function vandalismReverted($edit_id)
    {
        checkLegacyMySQL();
        mysqli_query(globals::$legacy_mysql, 'UPDATE `vandalism` SET `reverted` = 1 WHERE `id` = \''.mysqli_real_escape_string($edit_id).'\'');
    }
    // Returns nothing
    public static function vandalismRevertBeaten($edit_id, $title, $user, $diff)
    {
        checkLegacyMySQL();
        mysqli_query(globals::$legacy_mysql, 'UPDATE `vandalism` SET `reverted` = 0 WHERE `id` = \''.
                        mysqli_real_escape_string(globals::$legacy_mysql, $edit_id).
                    '\'');
        mysqli_query(globals::$legacy_mysql, 'INSERT INTO `beaten` (`id`,`article`,`diff`,`user`) VALUES (NULL,\''.
                        mysqli_real_escape_string(globals::$legacy_mysql, $title).'\',\''.
                        mysqli_real_escape_string(globals::$legacy_mysql, $diff).'\',\''.
                        mysqli_real_escape_string(globals::$legacy_mysql, $user). '\')');
    }
    // Returns the hostname of the current core node
    public static function getCurrentCoreNode()
    {
        checkLegacyMySQL();
        $res = mysqli_query(globals::$legacy_mysql, 'SELECT `node` from `cluster_node` where type="core"');
        $d = mysqli_fetch_assoc($res);

        return $d['node'];
    }
    // Returns the hostname of the current relay node
    public static function getCurrentRelayNode()
    {
        checkLegacyMySQL();
        $res = mysqli_query(globals::$legacy_mysql, 'SELECT `node` from `cluster_node` where type="relay"');
        $d = mysqli_fetch_assoc($res);

        return $d['node'];
    }
    // Returns the hostname of the current redis node
    public static function getCurrentRedisNode()
    {
        checkLegacyMySQL();
        $res = mysqli_query(globals::$legacy_mysql, 'SELECT `node` from `cluster_node` where type="redis"');
        $d = mysqli_fetch_assoc($res);

        return $d['node'];
    }
}
