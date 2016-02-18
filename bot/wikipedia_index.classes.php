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
class WikipediaIndex
{
    public $indexurl = 'https://en.wikipedia.org/w/index.php';
    private $http;
    private $postinterval = 0;
    private $lastpost;
    private $edittoken;

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
     * Post data to a page, nicely.
     *
     * @param $page Page title.
     * @param $data Data to post to page.
     * @param $summery Edit summary.  (Default '')
     * @param $minor Whether to mark edit as minor.  (Default false)
     * @param $rv Revision data.  If not given, it will be fetched.  (Default null)
     * @param $bot Whether to mark edit as bot.  (Default true)
     *
     * @return HTML data from the page.
     *
     * @deprecated
     * @see WikipediaApi::edit
     **/
    public function post($page, $data, $summery = '', $minor = false, $rv = null, $bot = true)
    {
        $wpq = new WikipediaQuery();
        $wpq->queryurl = str_replace('index.php', 'query.php', $this->indexurl);
        $wpapi = new WikipediaApi();
        $wpapi->apiurl = str_replace('index.php', 'api.php', $this->indexurl);

        if ((!$this->edittoken) or ($this->edittoken == '')) {
            $this->edittoken = $wpapi->getedittoken();
        }
        if ($rv == null) {
            $rv = $wpapi->revisions($page, 1, 'older', true);
        }
        if (!$rv[0]['*']) {
            $rv[0]['*'] = $wpq->getpage($page);
        }

        //Fake the edit form.
        $now = gmdate('YmdHis', time());
        $token = htmlspecialchars($this->edittoken);
        $tmp = date_parse($rv[0]['timestamp']);
        $edittime = gmdate('YmdHis', gmmktime(
            $tmp['hour'],
            $tmp['minute'],
            $tmp['second'],
            $tmp['month'],
            $tmp['day'],
            $tmp['year']
        ));
        $html = "<input type='hidden' value=\"{$now}\" name=\"wpStarttime\" />\n";
        $html .= "<input type='hidden' value=\"{$edittime}\" name=\"wpEdittime\" />\n";
        $html .= "<input type='hidden' value=\"{$token}\" name=\"wpEditToken\" />\n";
        $html .= '<input name="wpAutoSummary" type="hidden" value="' . md5('') . '" />' . "\n";

        if (preg_match('/' . preg_quote('{{nobots}}', '/') . '/iS', $rv[0]['*'])) {
            return false;
        }        /* Honor the bots flags */
        if (preg_match('/' . preg_quote('{{bots|allow=none}}', '/') . '/iS', $rv[0]['*'])) {
            return false;
        }
        if (preg_match('/' . preg_quote('{{bots|deny=all}}', '/') . '/iS', $rv[0]['*'])) {
            return false;
        }
        if (preg_match(
            '/' . preg_quote('{{bots|deny=', '/') . '(.*)' . preg_quote('}}', '/') . '/iS',
            $rv[0]['*'],
            $m
        )) {
            if (in_array(explode(',', $m[1]), Config::$user)) {
                return false;
            }
        } /* /Honor the bots flags */
        if (!preg_match('/' . preg_quote(Config::$user, '/') . '/iS', $rv['currentuser'])) {
            return false;
        } /* We need to be logged in */

        $x = $this->forcepost($page, $data, $summery, $minor, $html, Config::$maxlag, Config::$maxlagkeepgoing, $bot);
        $this->lastpost = time();

        return $x;
    }

    /**
     * Post data to a page.
     *
     * @param $page Page title.
     * @param $data Data to post to page.
     * @param $summery Edit summary.  (Default '')
     * @param $minor Whether to mark edit as minor.  (Default false)
     * @param $edithtml HTML from the edit form.  If not given, it will be fetched.  (Default null)
     * @param $maxlag Maxlag for posting.  (Default null)
     * @param $mlkg Whether to keep going after encountering a maxlag error and sleeping or not.  (Default null)
     * @param $bot Whether to mark edit as bot.  (Default true)
     *
     * @return HTML data from the page.
     *
     * @deprecated
     * @see WikipediaApi::edit
     **/
    public function forcepost(
        $page,
        $data,
        $summery = '',
        $minor = false,
        $edithtml = null,
        $maxlag = null,
        $mlkg = null,
        $bot = true
    ) {
        $post['wpSection'] = '';
        $post['wpScrolltop'] = '';
        if ($minor == true) {
            $post['wpMinoredit'] = 1;
        }
        $post['wpTextbox1'] = $data;
        $post['wpSummary'] = $summery;
        if ($edithtml == null) {
            $html = $this->http->get($this->indexurl . '?title=' . urlencode($page) . '&action=edit');
        } else {
            $html = $edithtml;
        }
        preg_match('|\<input type\=\\\'hidden\\\' value\=\"(.*)\" name\=\"wpStarttime\" /\>|U', $html, $m);
        $post['wpStarttime'] = $m[1];
        preg_match('|\<input type\=\\\'hidden\\\' value\=\"(.*)\" name\=\"wpEdittime\" /\>|U', $html, $m);
        $post['wpEdittime'] = $m[1];
        preg_match('|\<input type\=\\\'hidden\\\' value\=\"(.*)\" name\=\"wpEditToken\" /\>|U', $html, $m);
        $post['wpEditToken'] = $m[1];
        preg_match('|\<input name\=\"wpAutoSummary\" type\=\"hidden\" value\=\"(.*)\" /\>|U', $html, $m);
        $post['wpAutoSummary'] = $m[1];
        if ($maxlag != null) {
            $x = $this->http->post(
                $this->indexurl . '?title=' . urlencode($page) . '&action=submit&maxlag=' .
                urlencode($maxlag) . '&bot=' . (($bot == true) ? '1' : '0'),
                $post
            );
            if (preg_match('/Waiting for ([^ ]*): ([0-9.-]+) seconds lagged/S', $x, $lagged)) {
                sleep(10);
                if ($mlkg != true) {
                    return false;
                } else {
                    $x = $this->http->post(
                        $this->indexurl . '?title=' . urlencode($page) . '&action=submit&bot=' .
                        (($bot == true) ? '1' : '0'),
                        $post
                    );
                }
            }

            return $x;
        } else {
            return $this->http->post(
                $this->indexurl . '?title=' . urlencode($page) . '&action=submit&bot=' .
                (($bot == true) ? '1' : '0'),
                $post
            );
        }
    }

    /**
     * Get a diff.
     *
     * @param $title Page title to get the diff of.
     * @param $oldid Old revision ID.
     * @param $id New revision ID.
     * @param $wait Whether or not to wait for the diff to become available.  (Default true)
     *
     * @return Array of added data, removed data, and a rollback token if one was fetchable.
     **/
    public function diff($title, $oldid, $id, $wait = true)
    {
        global $logger;
        $deleted = '';
        $added = '';

        $html = $this->http->get(
            $this->indexurl . '?title=' . urlencode($title) . '&action=render&diff=' .
            urlencode($id) . '&oldid=' . urlencode($oldid) . '&diffonly=1'
        );

        if (preg_match_all(
            '/\&amp\;(oldid\=)(\d*)\\\'\>(Revision as of|Current revision as of)/USs',
            $html,
            $m,
            PREG_SET_ORDER
        )) {
            if ((($oldid != $m[0][2]) and (is_numeric($oldid))) or (($id != $m[1][2]) and (is_numeric($id)))) {
                if ($wait == true) {
                    sleep(1);

                    return $this->diff($title, $oldid, $id, false);
                } else {
                    $logger->addInfo('OLDID as detected: ' . $m[0][2] . ' Wanted: ' . $oldid);
                    $logger->addInfo('NEWID as detected: ' . $m[1][2] . ' Wanted: ' . $id);
                    $logger->addDebug($html);
                    $logger->addError("Revision error");
                    die();
                }
            }
        }

        if (preg_match_all(
            '/\<td class\=(\"|\\\')diff-addedline\1\>\<div\>(.*)\<\/div\>\<\/td\>/USs',
            $html,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $x) {
                $added .= htmlspecialchars_decode(strip_tags($x[2])) . "\n";
            }
        }

        if (preg_match_all(
            '/\<td class\=(\"|\\\')diff-deletedline\1\>\<div\>(.*)\<\/div\>\<\/td\>/USs',
            $html,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $x) {
                $deleted .= htmlspecialchars_decode(strip_tags($x[2])) . "\n";
            }
        }

        if (preg_match('/action\=rollback\&amp\;from\=.*\&amp\;token\=(.*)\"/US', $html, $m)) {
            $rbtoken = $m[1];
            $rbtoken = urldecode($rbtoken);
            return array($added, $deleted, $rbtoken);
        }

        return array($added, $deleted);
    }

    /**
     * Rollback an edit.
     *
     * @param $title Page title to rollback.
     * @param $user Username of last edit to the page to rollback.
     * @param $reason Reason to rollback.  If null, default is generated.  (Default null)
     * @param $token Rollback token to use.  If null, it is fetched.  (Default null)
     * @param $bot Whether or not to mark as bot.  (Default true)
     *
     * @return HTML or false if failure.
     *
     * @deprecated
     * @see WikipediaApi::rollback
     **/
    public function rollback($title, $user, $reason = null, $token = null, $bot = true)
    {
        if (($token == null) or (!$token)) {
            $wpapi = new WikipediaApi();
            $wpapi->apiurl = str_replace('index.php', 'api.php', $this->indexurl);
            $token = $wpapi->revisions($title, 1, 'older', false, null, true, true);
            if ($token[0]['user'] == $user) {
                $token = $token[0]['rollbacktoken'];
            } else {
                return false;
            }
        }
        $x = $this->http->get(
            $this->indexurl . '?title=' . urlencode($title) . '&action=rollback&from=' .
            urlencode($user) . '&token=' . urlencode($token) . (($reason != null) ? '&summary=' .
                urlencode($reason) : '') . '&bot=' . (($bot == true) ? '1' : '0')
        );
        if (!preg_match('/action complete/iS', $x)) {
            return false;
        }

        return $x;
    }

    /**
     * Move a page.
     *
     * @param $old Page title to move.
     * @param $new New title to move to.
     * @param $reason Move page summary.
     *
     * @return HTML page.
     *
     * @deprecated
     * @see WikipediaApi::move
     **/
    public function move($old, $new, $reason)
    {
        $wpapi = new WikipediaApi();
        $wpapi->apiurl = str_replace('index.php', 'api.php', $this->indexurl);
        if ((!$this->edittoken) or ($this->edittoken == '')) {
            $this->edittoken = $wpapi->getedittoken();
        }

        $token = htmlspecialchars($this->edittoken);

        $post = array(
            'wpOldTitle' => $old,
            'wpNewTitle' => $new,
            'wpReason' => $reason,
            'wpWatch' => '0',
            'wpEditToken' => $token,
            'wpMove' => 'Move page',
        );

        return $this->http->post($this->indexurl . '?title=Special:Movepage&action=submit', $post);
    }

    /**
     * Uploads a file.
     *
     * @param $page Name of page on the wiki to upload as.
     * @param $file Name of local file to upload.
     * @param $desc Content of the file description page.
     *
     * @return HTML content.
     **/
    public function upload($page, $file, $desc)
    {
        $post = array(
            'wpUploadFile' => '@' . $file,
            'wpSourceType' => 'file',
            'wpDestFile' => $page,
            'wpUploadDescription' => $desc,
            'wpLicense' => '',
            'wpWatchthis' => '0',
            'wpIgnoreWarning' => '1',
            'wpUpload' => 'Upload file',
        );

        return $this->http->post($this->indexurl . '?title=Special:Upload&action=submit', $post);
    }

    /**
     * Check if a user has email enabled.
     *
     * @param $user Username to check whether or not the user has email enabled.
     *
     * @return True or false depending on whether or not the user has email enabled.
     **/
    public function hasemail($user)
    {
        $tmp = $this->http->get($this->indexurl . '?title=Special:EmailUser&target=' . urlencode($user));
        if (stripos($tmp, 'No e-mail address') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Sends an email to a user.
     *
     * @param $user Username to send email to.
     * @param $subject Subject of email to send.
     * @param $body Body of email to send.
     *
     * @return HTML content.
     **/
    public function email($user, $subject, $body)
    {
        $wpapi = new WikipediaApi();
        $wpapi->apiurl = str_replace('index.php', 'api.php', $this->indexurl);
        if ((!$this->edittoken) or ($this->edittoken == '')) {
            $this->edittoken = $wpapi->getedittoken();
        }

        $post = array(
            'wpSubject' => $subject,
            'wpText' => $body,
            'wpCCMe' => 0,
            'wpSend' => 'Send',
            'wpEditToken' => $this->edittoken,
        );

        return $this->http->post(
            $this->indexurl . '?title=Special:EmailUser&target=' . urlencode($user) .
            '&action=submit',
            $post
        );
    }
}
