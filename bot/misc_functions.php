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
    function myfnmatch($pattern, $string)
    {
        if (strlen($string) < 4000) {
            return fnmatch($pattern, $string);
        } else {
            $pattern = strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.', '\[' => '[', '\]' => ']'));
            if (preg_match('#^'.$pattern.'$#', $string)) {
                return true;
            }

            return false;
        }
    }
    function doInit()
    {
        if (config::$pass == null) {
            config::$pass = trim(file_get_contents(getenv('HOME').'/.cluebotng.password.only'));
        }
        API::init();
        API::$a->login(config::$user, config::$pass);
        globals::$tfas = 0;
        globals::$stdin = fopen('php://stdin', 'r');
        globals::$run = API::$q->getpage('User:'.config::$user.'/Run');
        globals::$wl = API::$q->getpage('Wikipedia:Huggle/Whitelist');
        globals::$optin = API::$q->getpage('User:'.config::$user.'/Optin');
        globals::$aoptin = API::$q->getpage('User:'.config::$user.'/AngryOptin');
        globals::$stalk = array();
        globals::$edit = array();
        $tmp = explode("\n", API::$q->getpage('User:'.config::$owner.'/CBAutostalk.js'));
        foreach ($tmp as $tmp2) {
            if (strlen($tmp2) > 0 && substr($tmp2, 0, 1) != '#') {
                $tmp3 = explode('|', $tmp2, 2);
                if (count($tmp3) == 2) {
                    globals::$stalk[ $tmp3[ 0 ] ] = trim($tmp3[ 1 ]);
                } else {
                    print "Skipping auto stalk entry: $tmp2\n";
                }
            }
        }
        $tmp = explode("\n", API::$q->getpage('User:'.config::$owner.'/CBAutoedit.js'));
        foreach ($tmp as $tmp2) {
            if (strlen($tmp2) > 0 && substr($tmp2, 0, 1) != '#') {
                $tmp3 = explode('|', $tmp2, 2);
                if (count($tmp3) == 2) {
                    globals::$edit[ $tmp3[ 0 ] ] = trim($tmp3[ 1 ]);
                } else {
                    print "Skipping auto edit entry: $tmp2\n";
                }
            }
        }
    }
