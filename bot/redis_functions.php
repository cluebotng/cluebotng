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
class RedisProxy
{
    public static function send($change)
    {
        $data = json_encode(self::sanitise($change));
        $udp = fsockopen('udp://' . Db::getCurrentRedisNode(), 1345);
        if($udp !== false) {
            fwrite($udp, $data);
            fclose($udp);
        }
    }

    public static function sanitise($change)
    {
        $sanitised_change = $change;

        // Get rid of the page text
        unset($sanitised_change['all']['current']['text']);
        unset($sanitised_change['all']['previous']['text']);

        // Get rid of misc stuff
        unset($sanitised_change['rawline']);
        unset($sanitised_change['namespaceid']);

        return $sanitised_change;
    }
}
