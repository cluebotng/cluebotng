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

class NgDb
{
    public static $coreNodeCache = 0;
    public static $relayNodeCache = 0;
    public static $redisNodeCache = 0;
    public static $coreNode = null;
    public static $relayNode = null;
    public static $redisNode = null;

    // Returns the edit it for the vandalism
    public static function detectedVandalism($user, $title, $heuristic, $reason, $url, $old_rev_id, $rev_id)
    {
        checkNgMySQL();
        $query = 'INSERT INTO `cbng_backend_vandalism` ' .
            '(`id`,`user`,`article`,`heuristic`,`reason`,`old_id`,`new_id`,`reverted`) ' .
            'VALUES ' .
            '(NULL,\'' . mysqli_real_escape_string(Globals::$ng_mysql, $user) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$ng_mysql, $title) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$ng_mysql, $heuristic) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$ng_mysql, $reason) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$ng_mysql, $old_rev_id) . '\',' .
            '\'' . mysqli_real_escape_string(Globals::$ng_mysql, $rev_id) . '\',0)';
        mysqli_query(Globals::$ng_mysql, $query);

        return mysqli_insert_id(Globals::$ng_mysql);
    }

    // Returns nothing
    public static function vandalismReverted($edit_id)
    {
        checkNgMySQL();
        mysqli_query(
            Globals::$ng_mysql,
            'UPDATE `cbng_backend_vandalism` SET `reverted` = 1 WHERE `id` = \'' .
            mysqli_real_escape_string(Globals::$ng_mysql, $edit_id) . '\''
        );
    }

    // Returns nothing
    public static function vandalismRevertBeaten($edit_id, $title, $user, $diff)
    {
        checkNgMySQL();
        mysqli_query(
            Globals::$ng_mysql,
            'UPDATE `cbng_backend_vandalism` SET `reverted` = 0 WHERE `id` = \'' .
            mysqli_real_escape_string(Globals::$ng_mysql, $edit_id) . '\''
        );
        mysqli_query(
            Globals::$ng_mysql,
            'INSERT INTO `cbng_backend_beaten` (`id`,`article`,`diff`,`user`) VALUES (NULL,\'' .
            mysqli_real_escape_string(Globals::$ng_mysql, $title) . '\',\'' .
            mysqli_real_escape_string(Globals::$ng_mysql, $diff) . '\',\'' .
            mysqli_real_escape_string(Globals::$ng_mysql, $user) . '\')'
        );
    }

    // Returns the hostname of the current core node
    public static function getCurrentCoreNode()
    {
        if(NgDb::$coreNodeCache > time()-5 && NgDb::$coreNode != null) {
            return NgDb::$coreNode;
        }

        checkNgMySQL();
        NgDb::$coreNodeCache = time();
        $res = mysqli_query(Globals::$ng_mysql, 'SELECT `node` from `cbng_backend_clusternode` where type="core"');
        if($res !== false) {
            $d = mysqli_fetch_assoc($res);
            NgDb::$coreNode = $d['node'];
            return $d['node'];
        }
        NgDb::$coreNode = null;
        return null;
    }

    // Returns the hostname of the current relay node
    public static function getCurrentRelayNode()
    {
        if(NgDb::$relayNodeCache > time()-5 && NgDb::$relayNode != null) {
            return NgDb::$relayNode;
        }

        checkNgMySQL();
        NgDb::$relayNodeCache = time();
        $res = mysqli_query(Globals::$ng_mysql, 'SELECT `node` from `cbng_backend_clusternode` where type="relay"');
        if($res !== false) {
            $d = mysqli_fetch_assoc($res);
            NgDb::$relayNode = $d['node'];
            return $d['node'];
        }
        NgDb::$relayNode = null;
        return null;
    }

    // Returns the hostname of the current redis node
    public static function getCurrentRedisNode()
    {
        if(NgDb::$redisNodeCache > time()-5 && NgDb::$redisNode != null) {
            return NgDb::$redisNode;
        }

        checkNgMySQL();
        NgDb::$redisNodeCache = time();
        $res = mysqli_query(Globals::$ng_mysql, 'SELECT `node` from `cbng_backend_clusternode` where type="redis"');
        if($res !== false) {
            $d = mysqli_fetch_assoc($res);
            NgDb::$redisNode = $d['node'];
            return $d['node'];
        }
        NgDb::$redisNode = null;
        return null;
    }
}
