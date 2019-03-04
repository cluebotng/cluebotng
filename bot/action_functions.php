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

class Action
{
    public static function doWarn($change, $report)
    {
        $warning = self::getWarningLevel($change['user'], $tpcontent) + 1;
        if (!Config::$dry) {
            if ($warning >= 4) {
                /* Report them if they have been warned 4 times. */
                self::aiv($change, $report);
            } else {
                /* Warn them if they haven't been warned 4 times. */
                IRC::debug("Warning " . $change['user'] . " (" . $warning . ")");
                self::warn($change, $report, $tpcontent, $warning);
            }
        }
    }

    public static function getWarningLevel($user, &$content = null)
    {
        $warning = 0;
        $content = Api::$q->getpage('User talk:' . $user);
        if (preg_match_all(
            '/<!-- Template:(uw-[a-z]*(\d)(im)?|Blatantvandal \(serious warning\)) -->.*' .
            '(\d{2}):(\d{2}), (\d+) ([a-zA-Z]+) (\d{4}) \(UTC\)/iU',
            $content,
            $match,
            PREG_SET_ORDER
        )) {
            foreach ($match as $m) {
                $month = array(
                    'January' => 1, 'February' => 2, 'March' => 3,
                    'April' => 4, 'May' => 5, 'June' => 6, 'July' => 7,
                    'August' => 8, 'September' => 9, 'October' => 10,
                    'November' => 11, 'December' => 12,
                );
                if ($m[1] == 'Blatantvandal (serious warning)') {
                    $m[2] = 4;
                }
                if ((time() - gmmktime($m[4], $m[5], 0, $month[$m[7]], $m[6], $m[8])) <= (2 * 24 * 60 * 60)) {
                    if ($m[2] > $warning) {
                        $warning = $m[2];
                    }
                }
            }
        }

        return (int)$warning;
    }

    private static function aiv($change, $report)
    {
        $aivdata = Api::$q->getpage('Wikipedia:Administrator_intervention_against_vandalism/TB2');
        if (!preg_match('/' . preg_quote($change['user'], '/') . '/i', $aivdata)) {
            IRC::debug(
                '!admin Reporting [[User:' . $change['user'] .
                ']] to [[WP:AIV]]. Contributions: [[Special:Contributions/' . $change['user'] .
                ']] Block: [[Special:Blockip/' . $change['user'] . ']]'
            );
            Api::$a->edit(
                'Wikipedia:Administrator_intervention_against_vandalism/TB2',
                $aivdata . "\n\n" . '* {{' .
                ((long2ip(ip2long($change['user'])) == $change['user']) ? 'IPvandal' : 'Vandal') .
                '|' . $change['user'] . '}}'
                . ' - ' . $report . ' (Automated) ~~~~' . "\n",
                'Automatically reporting [[Special:Contributions/' . $change['user'] . ']].' .
                ' (bot)',
                false,
                false
            );
        }
    }

    private static function warn($change, $report, $content, $warning)
    {
        global $logger;
        $logger->addInfo('Warning ' . $change['user']);
        $ret = Api::$a->edit(
            'User talk:' . $change['user'],
            $content . "\n\n"
            . '{{subst:User:' . Config::$user . '/Warnings/Warning'
            . '|1=' . $warning
            . '|2=' . str_replace('File:', ':File:', $change['title'])
            . '|3=' . $report
            . ' <!{{subst:ns:0}}-- MySQL ID: ' . $change['mysqlid'] . ' --{{subst:ns:0}}>'
            . '|4=' . $change['mysqlid']
            . '}} ~~~~'
            . "\n",
            'Warning [[Special:Contributions/' . $change['user'] . '|' . $change['user'] . ']] - #' . $warning,
            false,
            false
        ); /* Warn the user */
        $logger->addDebug($ret);
    }

    public static function doRevert($change)
    {
        $rev = Api::$a->revisions($change['title'], 5, 'older', false, null, true, true);
        $revid = 0;
        $rbtok = $rev[0]['rollbacktoken'];
        $revdata = false;
        foreach ($rev as $revdata) {
            if ($revdata['user'] != $change['user']) {
                $revid = $revdata['revid'];
                break;
            }
        }
        if ($revdata === false) {
            return;
        }
        if (($revdata['user'] == Config::$user) or (in_array($revdata['user'], explode(',', Config::$friends)))) {
            return false;
        }
        if (Config::$dry) {
            return true;
        }
        $rbret = Api::$a->rollback(
            $change['title'],
            $change['user'],
            /*'Edit by [[Special:Contribs/' . $change['user'] . '|' . $change['user'] . ']] has been reverted by [[WP:CBNG|' . Config::$user . ']] due to possible noncompliance with Wikipedia guidelines. [[WP:CBFP|Report False Positive?]] (' . $change['mysqlid'] .') (Bot)'*/
            'Reverting possible vandalism by [[Special:Contribs/' . $change['user'] . '|' . $change['user'] . ']] ' .
            'to ' . (($revid == 0) ? 'older version' : 'version by ' . $revdata['user']) . '. ' .
            '[[WP:CBFP|Report False Positive?]] ' .
            'Thanks, [[WP:CBNG|' . Config::$user . ']]. (' . $change['mysqlid'] . ') (Bot)',
            $rbtok
        );

        return $rbret;
    }

    public static function shouldRevert($change)
    {
        $reason = 'Default revert';
        if (preg_match('/(assisted|manual)/iS', Config::$status)) {
            echo 'Revert [y/N]? ';
            if (strtolower(substr(fgets(Globals::$stdin, 3), 0, 1)) != 'y') {
                return array(false, 'Manual mode says no');
            }
        }
        if (!preg_match('/(yes|enable|true)/iS', Globals::$run)) {
            return array(false, 'Run disabled');
        }
        if ($change['user'] == Config::$user) {
            return array(false, 'User is myself');
        }
        if (Config::$angry) {
            return array(true, 'Angry-reverting in angry mode');
        }
        if ((time() - Globals::$tfas) >= 1800) {
            if (preg_match(
                '/\(\'\'\'\[\[([^|]*)\|more...\]\]\'\'\'\)/iU',
                Api::$q->getpage('Wikipedia:Today\'s featured article/' . date('F j, Y')),
                $tfam
            )
            ) {
                Globals::$tfas = time();
                Globals::$tfa = $tfam[1];
            }
        }
        if (!self::findAndParseBots($change)) {
            return array(false, 'Exclusion compliance');
        }
        if ($change['all']['user'] == $change['all']['common']['creator']) {
            return array(false, 'User is creator');
        }
        if ($change['all']['user_edit_count'] > 50) {
            if ($change['all']['user_warns'] / $change['all']['user_edit_count'] < 0.1) {
                return array(false, 'User has edit count');
            } else {
                $reason = 'User has edit count, but warns > 10%';
            }
        }
        if (Globals::$tfa == $change['title']) {
            return array(true, 'Angry-reverting on TFA');
        }
        if (preg_match('/\* \[\[(' . preg_quote($change['title'], '/') . ')\]\] \- .*/i', Globals::$aoptin)) {
            IRC::debug('Angry-reverting [[' . $change['title'] . ']].');

            return array(true, 'Angry-reverting on angry-optin');
        }
        $titles = unserialize(file_get_contents('titles.txt'));
        if (!isset($titles[$change['title'] . $change['user']])
            or ((time() - $titles[$change['title'] . $change['user']]) > (24 * 60 * 60))
        ) {
            $titles[$change['title'] . $change['user']] = time();
            file_put_contents('titles.txt', serialize($titles));

            return array(true, $reason);
        }

        return array(false, 'Reverted before');
    }

    public static function findAndParseBots($change)
    {
        $text = $change['all']['current']['text'];
        if (stripos('{{nobots}}', $text) !== false) {
            return false;
        }
        $botname = preg_quote(Config::$user, '/');
        $botname = str_replace(' ', '(_| )?', $botname);
        if (preg_match('/\{\{bots\s*\|\s*deny\s*\=[^}]*(' . $botname . '|\*)[^}]*\}\}/i', $text)) {
            return false;
        }
        if (preg_match('/\{\{bots\s*\|\s*allow\s*\=([^}]*)\}\}/i', $text, $matches)) {
            if (!preg_match('/(' . $botname . '|\*)/i', $matches[1])) {
                return false;
            }
        }

        return true;
    }

    public static function isWhitelisted($user)
    {
        foreach(Globals::$wl as $wl) {
            if (preg_match('/^' . preg_quote($user, '/') . '$/', $wl)) {
                return true;
            }
        }

        return false;
    }
}
