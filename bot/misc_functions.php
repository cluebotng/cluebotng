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
function myfnmatch($pattern, $string)
{
    if (strlen($string) < 4000) {
        return fnmatch($pattern, $string);
    } else {
        $pattern = strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.', '\[' => '[', '\]' => ']'));
        if (preg_match('#^' . $pattern . '$#', $string)) {
            return true;
        }

        return false;
    }
}

function loadHuggleWhitelist() {
    global $logger;
    if(($hgWLRaw = file_get_contents('https://huggle-wl.wmflabs.org/?action=read&wp=en.wikipedia.org')) != null) {
        Globals::$wl = array_slice(explode('|', $hgWLRaw), 0, -1);
        $logger->addInfo('Loaded huggle whitelist (' . len(Globals::$wl) . ')');
    } else {
      $logger->addWarning('Failed to load huggle whitelist');
    }
}

function doInit()
{
    global $logger;
    if (Config::$pass == null) {
        Config::$pass = trim(file_get_contents(getenv('HOME') . '/.cluebotng.password.only'));
    }
    Api::init();
    Api::$a->login(Config::$user, Config::$pass);
    Globals::$tfas = 0;
    Globals::$stdin = fopen('php://stdin', 'r');
    Globals::$run = Api::$q->getpage('User:' . Config::$user . '/Run');
    Globals::$optin = Api::$q->getpage('User:' . Config::$user . '/Optin');
    Globals::$aoptin = Api::$q->getpage('User:' . Config::$user . '/AngryOptin');
    loadHuggleWhitelist();
    Globals::$stalk = array();
    Globals::$edit = array();
    $tmp = explode("\n", Api::$q->getpage('User:' . Config::$owner . '/CBAutostalk.js'));
    foreach ($tmp as $tmp2) {
        if (strlen($tmp2) > 0 && substr($tmp2, 0, 1) != '#') {
            $tmp3 = explode('|', $tmp2, 2);
            if (count($tmp3) == 2) {
                Globals::$stalk[$tmp3[0]] = trim($tmp3[1]);
            } else {
                $logger->addInfo("Skipping auto stalk entry: $tmp2");
            }
        }
    }
    $tmp = explode("\n", Api::$q->getpage('User:' . Config::$owner . '/CBAutoedit.js'));
    foreach ($tmp as $tmp2) {
        if (strlen($tmp2) > 0 && substr($tmp2, 0, 1) != '#') {
            $tmp3 = explode('|', $tmp2, 2);
            if (count($tmp3) == 2) {
                Globals::$edit[$tmp3[0]] = trim($tmp3[1]);
            } else {
                $logger->addInfo("Skipping auto edit entry: $tmp2");
            }
        }
    }
}
