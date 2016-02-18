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

class IRC
{
    private static $chans = array();

    public static function split($message)
    {
        if (!$message) {
            return;
        }
        $return = array();
        $i = 0;
        $quotes = false;
        if ($message[$i] == ':') {
            $return['type'] = 'relayed';
            ++$i;
        } else {
            $return['type'] = 'direct';
        }
        $return['rawpieces'] = array();
        $temp = '';
        for (; $i < strlen($message); ++$i) {
            if ($quotes and $message[$i] != '"') {
                $temp .= $message[$i];
            } else {
                switch ($message[$i]) {
                    case ' ':
                        $return['rawpieces'][] = $temp;
                        $temp = '';
                        break;
                    case '"':
                        if ($quotes or $temp == '') {
                            $quotes = !$quotes;
                            break;
                        }
                        // Ignore
                    case ':':
                        if ($temp == '') {
                            ++$i;
                            $return['rawpieces'][] = substr($message, $i);
                            $i = strlen($message);
                            break;
                        }
                        // Ignore
                    default:
                        $temp .= $message[$i];
                }
            }
        }
        if ($temp != '') {
            $return['rawpieces'][] = $temp;
        }
        if ($return['type'] == 'relayed') {
            $return['source'] = $return['rawpieces'][0];
            $return['command'] = strtolower($return['rawpieces'][1]);
            $return['target'] = $return['rawpieces'][2];
            $return['pieces'] = array_slice($return['rawpieces'], 3);
        } else {
            $return['source'] = 'Server';
            $return['command'] = strtolower($return['rawpieces'][0]);
            $return['target'] = 'You';
            $return['pieces'] = array_slice($return['rawpieces'], 1);
        }
        $return['raw'] = $message;

        return $return;
    }

    public static function say($chans, $message)
    {
        global $logger;
        $relay_node = Db::getCurrentRelayNode();
        if(!isset($relay_node)) {
            $logger->addError("Could not get relay node. Failed to send: " . $message);
            return;
        }
        if (array_key_exists('irc' . $chans, self::$chans)) {
            $chans = 'irc' . $chans;
            $logger->addInfo('Saying to ' . $chans . ' (' . self::$chans[$chans] . '): ' . $message);
            foreach (explode(',', self::$chans[$chans]) as $chan) {
                $udp = fsockopen('udp://' . $relay_node, 1337);
                if($udp !== false) {
                    fwrite($udp, $chan . ' :' . $message);
                    fclose($udp);
                }
            }
        } else {
            $logger->addInfo('Saying to ' . $chans . ': ' . $message);
            $udp = fsockopen('udp://' . $relay_node, 1337);
            if($udp !== false) {
                fwrite($udp, $chans . ' :' . $message);
                fclose($udp);
            }
        }
    }

    public static function init()
    {
        $ircconfig = explode("\n", Api::$q->getpage('User:' . Config::$owner . '/CBChannels.js'));
        $tmp = array();
        foreach ($ircconfig as $tmpline) {
            if (strlen($tmpline) > 1) {
                if ($tmpline[0] != '#') {
                    $tmpline = explode('=', $tmpline, 2);
                    if (count($tmpline) == 2) {
                        $tmp[trim($tmpline[0])] = trim($tmpline[1]);
                    }
                }
            }
        }
        self::$chans = $tmp;
    }
}
