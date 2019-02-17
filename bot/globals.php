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

class Globals
{
    public static $stdin;
    public static $tfas;
    public static $tfa;
    public static $aoptin;
    public static $mw_mysql;
    public static $cb_mysql;
    public static $run;
    public static $wl = array();
    public static $optin;
    public static $edit;
    public static $stalk;
    public static $namespaces = array(
        'special' => -1,
        'media' => -2,
        'main' => 0,
        'talk' => 1,
        'user' => 2,
        'user talk' => 3,
        'wikipedia' => 4,
        'wikipedia talk' => 5,
        'file' => 6,
        'file talk' => 7,
        'mediawiki' => 8,
        'mediawiki talk' => 9,
        'template' => 10,
        'template talk' => 11,
        'help' => 12,
        'help talk' => 13,
        'category' => 14,
        'category talk' => 15,
        'portal' => 100,
        'portal talk' => 101,
    );
}
