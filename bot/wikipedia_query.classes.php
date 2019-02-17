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

class WikipediaQuery
{
    public $queryurl = 'https://en.wikipedia.org/w/query.php';
    private $http;
    private $api; //Obsolete, but kept for compatibility purposes.

    /**
     * This is our constructor.
     **/
    public function __construct()
    {
        global $__wp__http;
        if (!isset($__wp__http)) {
            $__wp__http = new Http();
        }
        $this->http = &$__wp__http;
        $this->api = new WikipediaApi();
    }

    /**
     * Gets the content of a page.
     *
     * @param $page The wikipedia page to fetch.
     *
     * @return The wikitext for the page.
     **/
    public function getpage($page)
    {
        $this->checkurl();
        $ret = $this->api->revisions($page, 1, 'older', true, null, true, false, false, false);

        if (is_array($ret) && count($ret) > 0 && array_key_exists('*', $ret[0])) {
            return $ret[0]['*'];
        }
    }

    /**
     * Reinitializes the queryurl.
     *
     * @private
     **/
    private function checkurl()
    {
        $this->api->apiurl = str_replace('query.php', 'api.php', $this->queryurl);
    }

    /**
     * Gets the page id for a page.
     *
     * @param $page The wikipedia page to get the id for.
     *
     * @return The page id of the page.
     **/
    public function getpageid($page)
    {
        $this->checkurl();
        $ret = $this->api->revisions($page, 1, 'older', false, null, true, false, false, false);

        return $ret['pageid'];
    }

    /**
     * Gets the number of contributions a user has.
     *
     * @param $user The username for which to get the edit count.
     *
     * @return The number of contributions the user has.
     **/
    public function contribcount($user)
    {
        $this->checkurl();
        $ret = $this->api->users($user, 1, null, true);
        if ($ret !== false) {
            return $ret[0]['editcount'];
        }

        return false;
    }
}
