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

class WikipediaApi
{
    public $apiurl = 'https://en.wikipedia.org/w/api.php';
    private $http;
    private $edittoken;
    private $tokencache;
    private $user;
    private $pass;

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
    }

    /**
     * This function returns the recent changes for the wiki.
     *
     * @param $count The number of items to return. (Default 10)
     * @param $namespace The namespace ID to filter items on. Null for no filtering. (Default null)
     * @param $dir The direction to pull items.  "older" or "newer".  (Default 'older')
     * @param $ts The timestamp to start at.  Null for the beginning/end (depending on direction).  (Default null)
     *
     * @return Associative array of recent changes metadata.
     **/
    public function recentchanges($count = 10, $namespace = null, $dir = 'older', $ts = null)
    {
        $append = '';
        if ($ts !== null) {
            $append .= '&rcstart=' . urlencode($ts);
        }
        $append .= '&rcdir=' . urlencode($dir);
        if ($namespace !== null) {
            $append .= '&rcnamespace=' . urlencode($namespace);
        }
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&list=recentchanges&rcprop=user|comment' .
            '|flags|timestamp|title|ids|sizes&format=php&rclimit=' . $count . $append
        );
        $x = unserialize($x);

        return $x['query']['recentchanges'];
    }

    /**
     * This function returns search results from Wikipedia's internal search engine.
     *
     * @param $search The query string to search for.
     * @param $limit The number of results to return. (Default 10)
     * @param $offset The number to start at.  (Default 0)
     * @param $namespace The namespace ID to filter by.  Null means no filtering.  (Default 0)
     * @param $what What to search, 'text' or 'title'.  (Default 'text')
     * @param $redirs Whether or not to list redirects.  (Default false)
     *
     * @return Associative array of search result metadata.
     **/
    public function search($search, $limit = 10, $offset = 0, $namespace = 0, $what = 'text', $redirs = false)
    {
        $append = '';
        if ($limit != null) {
            $append .= '&srlimit=' . urlencode($limit);
        }
        if ($offset != null) {
            $append .= '&sroffset=' . urlencode($offset);
        }
        if ($namespace != null) {
            $append .= '&srnamespace=' . urlencode($namespace);
        }
        if ($what != null) {
            $append .= '&srwhat=' . urlencode($what);
        }
        if ($redirs == true) {
            $append .= '&srredirects=1';
        } else {
            $append .= '&srredirects=0';
        }
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&list=search&format=php&srsearch=' .
            urlencode($search) . $append
        );
        $x = unserialize($x);

        return $x['query']['search'];
    }

    /**
     * Retrieve entries from the WikiLog.
     *
     * @param $user Username who caused the entry.  Null means anyone.  (Default null)
     * @param $title Object to which the entry refers.  Null means anything.  (Default null)
     * @param $limit Number of entries to return.  (Default 50)
     * @param $type Type of logs.  Null means any type.  (Default null)
     * @param $start Date to start enumerating logs.  Null means beginning/end depending on $dir.  (Default null)
     * @param $end Where to stop enumerating logs.  Null means whenever limit is satisfied or there are no more logs.
     * @param $dir Direction to enumerate logs.  "older" or "newer".  (Default 'older')
     *
     * @return Associative array of logs metadata.
     **/
    public function logs(
        $user = null,
        $title = null,
        $limit = 50,
        $type = null,
        $start = null,
        $end = null,
        $dir = 'older'
    ) {
        $append = '';
        if ($user != null) {
            $append .= '&leuser=' . urlencode($user);
        }
        if ($title != null) {
            $append .= '&letitle=' . urlencode($title);
        }
        if ($limit != null) {
            $append .= '&lelimit=' . urlencode($limit);
        }
        if ($type != null) {
            $append .= '&letype=' . urlencode($type);
        }
        if ($start != null) {
            $append .= '&lestart=' . urlencode($start);
        }
        if ($end != null) {
            $append .= '&leend=' . urlencode($end);
        }
        if ($dir != null) {
            $append .= '&ledir=' . urlencode($dir);
        }
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&format=php&list=logevents&leprop=ids|' .
            'title|type|user|timestamp|comment|details' . $append
        );
        $x = unserialize($x);

        return $x['query']['logevents'];
    }

    /**
     * Retrieves metadata about a user's contributions.
     *
     * @param $user Username whose contributions we want to retrieve.
     * @param $count Number of entries to return.  (Default 50)
     * @param[in,out] $continue Where to continue enumerating if part of a larger, split request.
     *
     * @param $dir Which direction to enumerate from, "older" or "newer".  (Default 'older')
     *
     * @return Associative array of contributions metadata.
     **/
    public function usercontribs($user, $count = 50, &$continue = null, $dir = 'older')
    {
        if ($continue != null) {
            $append = '&ucstart=' . urlencode($continue);
        } else {
            $append = '';
        }
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&format=php&list=usercontribs&ucuser=' .
            urlencode($user) . '&uclimit=' . urlencode($count) . '&ucdir=' . urlencode($dir) . $append
        );
        $x = unserialize($x);
        $continue = $x['query-continue']['usercontribs']['ucstart'];

        return $x['query']['usercontribs'];
    }

    /**
     * Enumerates user metadata.
     *
     * @param $start The username to start enumerating from.  Null means from the beginning.  (Default null)
     * @param $limit The number of users to enumerate.  (Default 1)
     * @param $group The usergroup to filter by.  Null means no filtering.  (Default null)
     * @param $requirestart Whether or not to require that $start be a valid username.  (Default false)
     * @param[out] $continue This is filled with the name to continue from next query.  (Default null)
     *
     * @return Associative array of user metadata.
     **/
    public function users($start = null, $limit = 1, $group = null, $requirestart = false, &$continue = null)
    {
        $append = '';
        if ($start != null) {
            $append .= '&aufrom=' . urlencode($start);
        }
        if ($group != null) {
            $append .= '&augroup=' . urlencode($group);
        }
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&list=allusers&format=php&auprop=' .
            'blockinfo|editcount|registration|groups&aulimit=' . urlencode($limit) . $append
        );
        $x = unserialize($x);
        $continue = $x['query-continue']['allusers']['aufrom'];
        if (($requirestart == true) and ($x['query']['allusers'][0]['name'] != $start)) {
            return false;
        }

        return $x['query']['allusers'];
    }

    /**
     * Get members of a category.
     *
     * @param $category Category to enumerate from.
     * @param $count Number of members to enumerate.  (Default 500)
     * @param[in,out] $continue Where to continue enumerating from.  This is automatically filled in when run.
     *
     * @return Associative array of category member metadata.
     **/
    public function categorymembers($category, $count = 500, &$continue = null)
    {
        if ($continue != null) {
            $append = '&cmcontinue=' . urlencode($continue);
        } else {
            $append = '';
        }
        $category = 'Category:' . str_ireplace('category:', '', $category);
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&list=categorymembers&cmtitle=' .
            urlencode($category) . '&format=php&cmlimit=' . $count . $append
        );
        $x = unserialize($x);
        $continue = $x['query-continue']['categorymembers']['cmcontinue'];

        return $x['query']['categorymembers'];
    }

    /**
     * Enumerate all categories.
     *
     * @param[in,out] $start Where to start enumerating.  This is updated automatically with the value to continue from.
     *
     * @param $limit Number of categories to enumerate.  (Default 50)
     * @param $dir Direction to enumerate in.  'ascending' or 'descending'.  (Default 'ascending')
     * @param $prefix Only enumerate categories with this prefix.  (Default null)
     *
     * @return Associative array of category list metadata.
     **/
    public function listcategories(&$start = null, $limit = 50, $dir = 'ascending', $prefix = null)
    {
        $append = '';
        if ($start != null) {
            $append .= '&acfrom=' . urlencode($start);
        }
        if ($limit != null) {
            $append .= '&aclimit=' . urlencode($limit);
        }
        if ($dir != null) {
            $append .= '&acdir=' . urlencode($dir);
        }
        if ($prefix != null) {
            $append .= '&acprefix=' . urlencode($prefix);
        }

        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&list=' .
            'allcategories&acprop=size&format=php' . $append
        );
        $x = unserialize($x);

        $start = $x['query-continue']['allcategories']['acfrom'];

        return $x['query']['allcategories'];
    }

    /**
     * Enumerate all backlinks to a page.
     *
     * @param $page Page to search for backlinks to.
     * @param $count Number of backlinks to list.  (Default 500)
     * @param[in,out] $continue Where to start enumerating from.  This is automatically filled in.  (Default null)
     *
     * @param $filter Whether or not to include redirects.  Acceptible values are 'all', 'redirects', and 'nonredirects'
     *
     * @return Associative array of backlink metadata.
     **/
    public function backlinks($page, $count = 500, &$continue = null, $filter = null)
    {
        if ($continue != null) {
            $append = '&blcontinue=' . urlencode($continue);
        } else {
            $append = '';
        }
        if ($filter != null) {
            $append .= '&blfilterredir=' . urlencode($filter);
        }

        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&list=backlinks&bltitle=' .
            urlencode($page) . '&format=php&bllimit=' . $count . $append
        );
        $x = unserialize($x);
        $continue = $x['query-continue']['backlinks']['blcontinue'];

        return $x['query']['backlinks'];
    }

    /**
     * Gets a list of transcludes embedded in a page.
     *
     * @param $page Page to look for transcludes in.
     * @param $count Number of transcludes to list.  (Default 500)
     * @param[in,out] $continue Where to start enumerating from.  This is automatically filled in.  (Default null)
     *
     * @return Associative array of transclude metadata.
     **/
    public function embeddedin($page, $count = 500, &$continue = null)
    {
        if ($continue != null) {
            $append = '&eicontinue=' . urlencode($continue);
        } else {
            $append = '';
        }
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&list=embeddedin&eititle=' .
            urlencode($page) . '&format=php&eilimit=' . $count . $append
        );
        $x = unserialize($x);
        $continue = $x['query-continue']['embeddedin']['eicontinue'];

        return $x['query']['embeddedin'];
    }

    /**
     * Gets a list of pages with a common prefix.
     *
     * @param $prefix Common prefix to search for.
     * @param $namespace Numeric namespace to filter on.  (Default 0)
     * @param $count Number of pages to list.  (Default 500)
     * @param[in,out] $continue Where to start enumerating from.  This is automatically filled in.  (Default null)
     *
     * @return Associative array of page metadata.
     **/
    public function listprefix($prefix, $namespace = 0, $count = 500, &$continue = null)
    {
        $append = '&apnamespace=' . urlencode($namespace);
        if ($continue != null) {
            $append .= '&apfrom=' . urlencode($continue);
        }
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&list=allpages&apprefix=' .
            urlencode($prefix) . '&format=php&aplimit=' . $count . $append
        );
        $x = unserialize($x);
        $continue = $x['query-continue']['allpages']['apfrom'];

        return $x['query']['allpages'];
    }

    /**
     * Edits a page.
     *
     * @param $page Page name to edit.
     * @param $data Data to post to page.
     * @param $summary Edit summary to use.
     * @param $minor Whether or not to mark edit as minor.  (Default false)
     * @param $bot Whether or not to mark edit as a bot edit.  (Default true)
     * @param $wpStarttime Time in MW TS format of beginning of edit.  (Default now)
     * @param $wpEdittime Time in MW TS format of last edit to that page.  (Default correct)
     *
     * @return bool True on success, false on failure.
     **/
    public function edit(
        $page,
        $data,
        $summary = '',
        $minor = false,
        $bot = true,
        $wpStarttime = null,
        $wpEdittime = null,
        $checkrun = true
    ) {
        global $logger;
        $wpq = new WikipediaQuery();
        $wpq->queryurl = str_replace('api.php', 'query.php', $this->apiurl);

        $params = array(
            'action' => 'edit',
            'format' => 'php',
            'title' => $page,
            'text' => $data,
            'token' => $this->getedittoken(),
            'summary' => $summary,
            ($minor ? 'minor' : 'notminor') => '1',
            ($bot ? 'bot' : 'notbot') => '1',
        );

        if ($wpStarttime !== null) {
            $params['starttimestamp'] = $wpStarttime;
        }
        if ($wpEdittime !== null) {
            $params['basetimestamp'] = $wpEdittime;
        }

        $x = $this->http->post($this->apiurl, $params);
        $logger->addDebug($x);
        $x = unserialize($x);
        if ($x['edit']['result'] == 'Success') {
            return true;
        }
        if ($x['error']['code'] == 'badtoken') {
            if ($this->login($this->user, $this->pass)) {
                $this->gettokens('Main Page', true);

                return $this->edit($page, $data, $summary, $minor, $bot, $wpStarttime, $wpEdittime, $checkrun);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * This function returns the edit token.
     *
     * @return Edit token.
     **/
    public function getedittoken()
    {
        $tokens = $this->gettokens('Main Page');
        if ($tokens['edittoken'] == '') {
            $tokens = $this->gettokens('Main Page', true);
        }
        $this->edittoken = $tokens['edittoken'];

        return $tokens['edittoken'];
    }

    /**
     * This function returns the various tokens for a certain page.
     *
     * @param $title Page to get the tokens for.
     * @param $flush Optional - internal use only.  Flushes the token cache.
     *
     * @return An associative array of tokens for the page.
     **/
    public function gettokens($title, $flush = false)
    {
        if (!is_array($this->tokencache)) {
            $this->tokencache = array();
        }
        foreach ($this->tokencache as $t => $data) {
            if (time() - $data['timestamp'] > 6 * 60 * 60) {
                unset($this->tokencache[$t]);
            }
        }
        if (isset($this->tokencache[$title]) && (!$flush)) {
            return $this->tokencache[$title]['tokens'];
        } else {
            $x = $this->http->get(
                $this->apiurl . '?action=query&rawcontinue=1&format=php&prop=info&intoken=edit' .
                '|delete|protect|move|block|unblock|email&titles=' . urlencode($title)
            );
            $x = unserialize($x);
            foreach ($x['query']['pages'] as $y) {
                $tokens = array();
                if (isset($y['edittoken'])) {
                    $tokens['edittoken'] = $y['edittoken'];
                }
                if (isset($y['deletetoken'])) {
                    $tokens['deletetoken'] = $y['deletetoken'];
                }
                if (isset($y['protecttoken'])) {
                    $tokens['protecttoken'] = $y['protecttoken'];
                }
                if (isset($y['movetoken'])) {
                    $tokens['movetoken'] = $y['movetoken'];
                }
                if (isset($y['blocktoken'])) {
                    $tokens['blocktoken'] = $y['blocktoken'];
                }
                if (isset($y['unblocktoken'])) {
                    $tokens['unblocktoken'] = $y['unblocktoken'];
                }
                if (isset($y['emailtoken'])) {
                    $tokens['emailtoken'] = $y['emailtoken'];
                }
                $this->tokencache[$title] = array(
                    'timestamp' => time(),
                    'tokens' => $tokens,
                );

                return $tokens;
            }
        }
    }

    /**
     * This function takes a username and password and logs you into wikipedia.
     *
     * @param $user Username to login as.
     * @param $pass Password that corrisponds to the username.
     **/
    public function login($user, $pass)
    {
        $this->user = $user;
        $this->pass = $pass;
        $x = $this->http->post(
            $this->apiurl . '?action=login&format=php',
            array('lgname' => $user, 'lgpassword' => $pass)
        );
        $logger->addDebug($x);
        $x = unserialize($x);
        if ($x['login']['result'] == 'Success') {
            return true;
        }
        if ($x['login']['result'] == 'NeedToken') {
            $x = $this->http->post(
                $this->apiurl . '?action=login&format=php',
                array('lgname' => $user, 'lgpassword' => $pass, 'lgtoken' => $x['login']['token'])
            );
            $logger->addDebug($x);
            $x = unserialize($x);
            if ($x['login']['result'] == 'Success') {
                return true;
            }
        }

        return false;
    }

    /**
     * Moves a page.
     *
     * @param $old Name of page to move.
     * @param $new New page title.
     * @param $reason Move summary to use.
     **/
    public function move($old, $new, $reason)
    {
        global $logger;
        $tokens = $this->gettokens($old);
        $params = array(
            'action' => 'move',
            'format' => 'php',
            'from' => $old,
            'to' => $new,
            'token' => $tokens['movetoken'],
            'reason' => $reason,
        );

        $x = $this->http->post($this->apiurl, $params);
        $logger->addDebug($x);
        $x = unserialize($x);
    }

    /**
     * Rollback an edit.
     *
     * @param $title Title of page to rollback.
     * @param $user Username of last edit to the page to rollback.
     * @param $reason Edit summary to use for rollback.
     * @param $token Rollback token.  If not given, it will be fetched.  (Default null)
     **/
    public function rollback($title, $user, $reason, $token = null)
    {
        global $logger;
        if (($token == null) or ($token == '')) {
            $token = $this->revisions($title, 1, 'older', false, null, true, true);
            if ($token[0]['user'] == $user) {
                $token = $token[0]['rollbacktoken'];
            } else {
                return false;
            }
        }
        $params = array(
            'action' => 'rollback',
            'format' => 'php',
            'title' => $title,
            'user' => $user,
            'summary' => $reason,
            'token' => $token,
        );

        $logger->addInfo('Posting to API: ' . var_export($params, true));
        $x = $this->http->post($this->apiurl, $params);
        $logger->addDebug($x);
        $x = unserialize($x);

        return (isset($x['rollback']['summary']) and isset($x['rollback']['revid']) and $x['rollback']['revid'])
            ? true
            : false;
    }

    /**
     * Returns revision data (meta and/or actual).
     *
     * @param $page Page for which to return revision data for.
     * @param $count Number of revisions to return. (Default 1)
     * @param $dir Direction to start enumerating multiple revisions from, "older" or "newer". (Default 'older')
     * @param $content Whether to return actual revision content, true or false.  (Default false)
     * @param $revid Revision ID to start at.  (Default null)
     * @param $wait Whether or not to wait a few seconds for the specific revision to become available.  (Default true)
     * @param $getrbtok Whether or not to retrieve a rollback token for the revision.  (Default false)
     * @param $dieonerror Whether or not to kill the process with an error if an error occurs.  (Default false)
     * @param $redirects Whether or not to follow redirects.  (Default false)
     *
     * @return Associative array of revision data.
     **/
    public function revisions(
        $page,
        $count = 1,
        $dir = 'older',
        $content = false,
        $revid = null,
        $wait = true,
        $getrbtok = false,
        $dieonerror = true,
        $redirects = false
    ) {
        $x = $this->http->get(
            $this->apiurl . '?action=query&rawcontinue=1&prop=revisions&titles=' .
            urlencode($page) . '&rvlimit=' . urlencode($count) . '&rvprop=timestamp|ids|user|comment' .
            (($content) ? '|content' : '') . '&format=php&meta=userinfo&rvdir=' . urlencode($dir) .
            (($revid !== null) ? '&rvstartid=' . urlencode($revid) : '') .
            (($getrbtok == true) ? '&rvtoken=rollback' : '') . (($redirects == true) ? '&redirects' : '')
        );
        $x = unserialize($x);

        if ($revid !== null) {
            $found = false;
            if (!isset($x['query']['pages']) or !is_array($x['query']['pages'])) {
                if ($dieonerror == true) {
                    die('No such page.' . "\n");
                } else {
                    return false;
                }
            }
            foreach ($x['query']['pages'] as $data) {
                if (!isset($data['revisions']) or !is_array($data['revisions'])) {
                    if ($dieonerror == true) {
                        die('No such page.' . "\n");
                    } else {
                        return false;
                    }
                }
                foreach ($data['revisions'] as $data2) {
                    if ($data2['revid'] == $revid) {
                        $found = true;
                    }
                }
                unset($data, $data2);
                break;
            }

            if ($found == false) {
                if ($wait == true) {
                    sleep(1);

                    return $this->revisions($page, $count, $dir, $content, $revid, false, $getrbtok, $dieonerror);
                } else {
                    if ($dieonerror == true) {
                        die('Revision error.' . "\n");
                    }
                }
            }
        }

        foreach ($x['query']['pages'] as $key => $data) {
            $data['revisions']['ns'] = $data['ns'];
            $data['revisions']['title'] = $data['title'];
            $data['revisions']['currentuser'] = $x['query']['userinfo']['name'];
            if (array_key_exists('query-continue', $x) &&
                array_key_exists('revisions', $x['query-continue']) &&
                array_key_exists('rvstartid', $x['query-continue']['revisions'])
            ) {
                $data['revisions']['continue'] = $x['query-continue']['revisions']['rvstartid'];
            }
            $data['revisions']['pageid'] = $key;

            return $data['revisions'];
        }
    }
}
