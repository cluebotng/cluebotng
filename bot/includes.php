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

date_default_timezone_set('Europe/London');
include 'vendor/autoload.php';
$logger = new \Monolog\Logger('cluebotng');
$logger->pushHandler(new \Monolog\Handler\RotatingFileHandler(getenv('HOME').'/logs/cluebotng.log', 2, \Monolog\Logger::INFO, true, 0600, false));

require_once 'cluebot-ng.config.php';
require_once 'redis_functions.php';
require_once 'action_functions.php';
require_once 'cbng.php';
require_once 'feed_functions.php';
require_once 'irc_functions.php';
require_once 'mysql_functions.php';
require_once 'wikipedia_query.classes.php';
require_once 'wikipedia_api.classes.php';
require_once 'wikipedia_index.classes.php';
require_once 'http.classes.php';
require_once 'globals.php';
require_once 'api.php';
require_once 'process_functions.php';
require_once 'misc_functions.php';
require_once 'db_legacy_functions.php';
require_once 'db_ng_functions.php';
require_once 'db_functions.php';

if (Config::$sentry_url != null) {
    \Raven_Autoloader::register();
    $client = new \Raven_Client(Config::$sentry_url);
    $error_handler = new \Raven_ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();
}
