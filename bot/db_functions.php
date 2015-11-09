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
class Db
{
    public static function detectedVandalism($user, $title, $heuristic, $reason, $url, $old_rev_id, $rev_id)
    {
        return LegacyDb::detectedVandalism($user, $title, $heuristic, $reason, $url, $old_rev_id, $rev_id);
    }
    public static function vandalismReverted($edit_id)
    {
        LegacyDb::vandalismReverted($edit_id);
    }
    public static function vandalismRevertBeaten($edit_id, $title, $user, $diff)
    {
        LegacyDb::vandalismRevertBeaten($edit_id, $title, $user, $diff);
    }
    public static function getCurrentCoreNode()
    {
        return LegacyDb::getCurrentCoreNode();
    }
    public static function getCurrentRelayNode()
    {
        return LegacyDb::getCurrentRelayNode();
    }
    public static function getCurrentRedisNode()
    {
        return LegacyDb::getCurrentRedisNode();
    }
}
