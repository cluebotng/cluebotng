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

class Process
{
    public static function processEdit($change)
    {
        global $logger;
        if ((time() - Globals::$tfas) >= 1800 and
            preg_match(
                '/\(\'\'\'\[\[([^|]*)\|more...\]\]\'\'\'\)/iU',
                Api::$q->getpage('Wikipedia:Today\'s featured article/' . date('F j, Y')),
                $tfam
            )
        ) {
            Globals::$tfas = time();
            Globals::$tfa = $tfam[1];
        }
        if (Config::$fork) {
            $pid = pcntl_fork();
            if ($pid != 0) {
                $logger->addDebug('Forked - ' . $pid);
                return;
            }
        }
        $change = parseFeedData($change);
        $change['justtitle'] = $change['title'];
        if (in_array('namespace', $change) && $change['namespace'] != 'Main:') {
            $change['title'] = $change['namespace'] . $change['title'];
        }
        self::processEditThread($change);
        if (Config::$fork) {
            die();
        }
    }

    public static function processEditThread($change)
    {
        global $logger;
        $change['edit_status'] = 'not_reverted';
        if (!isset($s)) {
            $change['edit_score'] = 'N/A';
            $s = null;
        } else {
            $change['edit_score'] = $s;
        }
        if (!in_array('all', $change)) {
            Feed::bail($change, 'Missing edit data', $s);
            return;
        }

        if (!isVandalism($change['all'], $s)) {
            Feed::bail($change, 'Below threshold', $s);
            return;
        }

        if (Action::isWhitelisted($change['user'])) {
            $logger->addInfo("User " . $change['user'] . " is whitelisted");
            Feed::bail($change, 'Whitelisted', $s);
            return;
        }
        $reason = 'ANN scored at ' . $s;
        $heuristic = '';
        $log = null;
        $diff = 'https://en.wikipedia.org/w/index.php' .
            '?title=' . urlencode($change['title']) .
            '&diff=' . urlencode($change['revid']) .
            '&oldid=' . urlencode($change['old_revid']);
        $report = '[[' . str_replace('File:', ':File:', $change['title']) . ']] was '
            . '[' . $diff . ' changed] by '
            . '[[Special:Contributions/' . $change['user'] . '|' . $change['user'] . ']] '
            . '[[User:' . $change['user'] . '|(u)]] '
            . '[[User talk:' . $change['user'] . '|(t)]] '
            . $reason . ' on ' . gmdate('c');
        $oftVand = unserialize(file_get_contents('oftenvandalized.txt'));
        if (rand(1, 50) == 2) {
            foreach ($oftVand as $art => $artVands) {
                foreach ($artVands as $key => $time) {
                    if ((time() - $time) > 2 * 24 * 60 * 60) {
                        unset($oftVand[$art][$key]);
                    }
                }
            }
        }
        $oftVand[$change['title']][] = time();
        file_put_contents('oftenvandalized.txt', serialize($oftVand));
        $ircreport = "\x0315[[\x0307" . $change['title'] . "\x0315]] by \"\x0303" . $change['user'] .
            "\x0315\" (\x0312 " . $change['url'] . " \x0315) \x0306" . $s . "\x0315 (";
        $change['mysqlid'] = Db::detectedVandalism(
            $change['user'],
            $change['title'],
            $heuristic,
            $reason,
            $change['url'],
            $change['old_revid'],
            $change['revid']
        );
        list($shouldRevert, $revertReason) = Action::shouldRevert($change);
        $change['revert_reason'] = $revertReason;
        if ($shouldRevert) {
            $logger->addInfo("Reverting");
            $rbret = Action::doRevert($change);
            if ($rbret !== false) {
                $change['edit_status'] = 'reverted';
                IRC::revert(
                    $ircreport . "\x0304Reverted\x0315) (\x0313" . $revertReason .
                    "\x0315) (\x0302" . (microtime(true) - $change['startTime']) . " \x0315s)"
                );
                Action::doWarn($change, $report);
                Db::vandalismReverted($change['mysqlid']);
                Feed::bail($change, $revertReason, $s, true);
            } else {
                $change['edit_status'] = 'beaten';
                $rv2 = Api::$a->revisions($change['title'], 1);
                if ($change['user'] != $rv2[0]['user']) {
                    IRC::revert(
                        $ircreport . "\x0303Not Reverted\x0315) (\x0313Beaten by " .
                        $rv2[0]['user'] . "\x0315) (\x0302" . (microtime(true) - $change['startTime']) . " \x0315s)"
                    );
                    Db::vandalismRevertBeaten($change['mysqlid'], $change['title'], $rv2[0]['user'], $change['url']);
                    Feed::bail($change, 'Beaten by ' . $rv2[0]['user'], $s);
                }
            }
        } else {
            Feed::bail($change, $revertReason, $s);
        }
    }
}
