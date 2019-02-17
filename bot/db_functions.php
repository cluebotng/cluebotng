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

class Db
{
    public static $coreNodeCache = 0;
    public static $relayNodeCache = 0;
    public static $coreNode = null;
    public static $relayNode = null;

    // Returns the edit it for the vandalism
    public static function detectedVandalism($user, $title, $heuristic, $reason, $url, $old_rev_id, $rev_id)
    {
        checkMySQL();
        $query = 'INSERT INTO `vandalism` ' .
            '(`id`,`user`,`article`,`heuristic`,`reason`,`diff`,`old_id`,`new_id`,`reverted`) ' .
            'VALUES ' .
            '(NULL,\'' . mysqli_real_escape_string(Globals::$cb_mysql, $user) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$cb_mysql, $title) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$cb_mysql, $heuristic) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$cb_mysql, $reason) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$cb_mysql, $url) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$cb_mysql, $old_rev_id) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$cb_mysql, $rev_id) . '\',0)';
        mysqli_query(Globals::$cb_mysql, $query);

        return mysqli_insert_id(Globals::$cb_mysql);
    }

    // Returns nothing
    public static function vandalismReverted($edit_id)
    {
        checkMySQL();
        mysqli_query(
            Globals::$cb_mysql,
            'UPDATE `vandalism` SET `reverted` = 1 WHERE `id` = \'' . mysqli_real_escape_string(Globals::$cb_mysql, $edit_id) . '\''
        );
    }

    // Returns nothing
    public static function vandalismRevertBeaten($edit_id, $title, $user, $diff)
    {
        checkMySQL();
        mysqli_query(
            Globals::$cb_mysql,
            'UPDATE `vandalism` SET `reverted` = 0 WHERE `id` = \'' .
            mysqli_real_escape_string(Globals::$cb_mysql, $edit_id) . '\''
        );
        mysqli_query(
            Globals::$cb_mysql,
            'INSERT INTO `beaten` (`id`,`article`,`diff`,`user`) VALUES (NULL,\'' .
            mysqli_real_escape_string(Globals::$cb_mysql, $title) . '\',\'' .
            mysqli_real_escape_string(Globals::$cb_mysql, $diff) . '\',\'' .
            mysqli_real_escape_string(Globals::$cb_mysql, $user) . '\')'
        );
    }

    // Returns the hostname of the current core node
    public static function getCurrentCoreNode()
    {
        if (self::$coreNodeCache > time() - 10 && self::$coreNode != null) {
            return self::$coreNode;
        }

        checkMySQL();
        self::$coreNodeCache = time();
        $res = mysqli_query(Globals::$cb_mysql, 'SELECT `node` from `cluster_node` where type="core"');
        if ($res !== false) {
            $d = mysqli_fetch_assoc($res);
            self::$coreNode = $d['node'];
            return $d['node'];
        }
        self::$coreNode = null;
        return null;
    }

    // Returns the hostname of the current relay node
    public static function getCurrentRelayNode()
    {
        if (self::$relayNodeCache > time() - 10 && self::$relayNode != null) {
            return self::$relayNode;
        }

        checkMySQL();
        self::$relayNodeCache = time();
        $res = mysqli_query(Globals::$cb_mysql, 'SELECT `node` from `cluster_node` where type="relay"');
        if ($res !== false) {
            $d = mysqli_fetch_assoc($res);
            self::$relayNode = $d['node'];
            return $d['node'];
        }
        self::$relayNode = null;
        return null;
    }
}
