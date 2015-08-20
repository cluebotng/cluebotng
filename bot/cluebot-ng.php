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
    declare (ticks = 1);

    require_once 'includes.php';

    function sig_handler($signo)
    {
        switch ($signo) {
            case SIGCHLD:
                while (($x = pcntl_waitpid(0, $status, WNOHANG)) != -1) {
                    if ($x == 0) {
                        break;
                    }
                    $status = pcntl_wexitstatus($status);
                }
                break;
        }
    }

    pcntl_signal(SIGCHLD, 'sig_handler');

    date_default_timezone_set('UTC');
    doInit();
    IRC::init();
    for (;;) {
        Feed::connectLoop();
    }
