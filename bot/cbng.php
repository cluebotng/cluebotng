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
function namespace2id($ns)
{
    return Globals::$namespaces[strtolower(str_replace('_', ' ', $ns))];
}

function namespace2name($nsid)
{
    $convertFlipped = array_flip(Globals::$namespaces);
    return ucfirst($convertFlipped[$nsid]);
}

function parseFeed($feed)
{
    if (preg_match(
        '/^\[\[((Talk|User|Wikipedia|File|MediaWiki|Template|Help|Category|Portal|Special)(( |_)talk)?:)?' .
        '([^\x5d]*)\]\] (\S*) (https?:\/\/en\.wikipedia\.org\/w\/index\.php\?diff=(\d*)&oldid=(\d*).*|' .
        'https?:\/\/en\.wikipedia\.org\/wiki\/\S+)? \* ([^*]*) \* (\(([^)]*)\))? (.*)$/S',
        $feed,
        $m
    )) {
        $change = array(
            'namespace' => $m[1] ? $m[1] : 'Main:',
            'namespaceid' => namespace2id($m[1] ? substr($m[1], 0, -1) : 'Main'),
            'title' => $m[5],
            'flags' => $m[6],
            'url' => $m[7],
            'revid' => $m[8],
            'old_revid' => $m[9],
            'user' => $m[10],
            'length' => $m[12],
            'comment' => $m[13],
            'timestamp' => time(),
        );
        return $change;
    }

    return false;
}

function setupUrlFetch($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, 'ClueBot/2.0');
    if (isset($proxyhost) and isset($proxyport) and $proxyport != null and $proxyhost != null) {
        curl_setopt($ch, CURLOPT_PROXYTYPE, isset($proxytype) ? $proxytype : CURLPROXY_HTTP);
        curl_setopt($ch, CURLOPT_PROXY, $proxyhost);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxyport);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPGET, 1);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    return $ch;
}

function getUrlsInParallel($urls)
{
    $mh = curl_multi_init();
    $chs = array();
    foreach ($urls as $url) {
        $ch = setupUrlFetch($url);
        curl_multi_add_handle($mh, $ch);
        $chs[] = $ch;
    }
    $running = null;
    curl_multi_exec($mh, $running);
    while ($running > 0) {
        curl_multi_select($mh);
        curl_multi_exec($mh, $running);
    }
    $ret = array();
    foreach ($chs as $ch) {
        $ret[] = unserialize(curl_multi_getcontent($ch));
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);

    return $ret;
}

function xmlizePart($doc, $key, $data)
{
    $element = $doc->createElement($key);
    if (is_array($data)) {
        foreach ($data as $arrKey => $value) {
            $element->appendChild(xmlizePart($doc, $arrKey, $value));
        }
    } else {
        $element->appendChild($doc->createTextNode($data));
    }

    return $element;
}

function xmlize($data)
{
    $doc = new \DOMDocument('1.0');
    $root = $doc->createElement('WPEditSet');
    $doc->appendChild($root);
    if (isset($data[0])) {
        foreach ($data as $entry) {
            $root->appendChild(xmlizePart($doc, 'WPEdit', $entry));
        }
    } else {
        $root->appendChild(xmlizePart($doc, 'WPEdit', $data));
    }

    return $doc->saveXML();
}

function parseAllFeed($feed)
{
    $feedData = parseFeed($feed);

    return parseFeedData($feedData);
}

function genOldFeedData($id)
{
    /* namespace, namespaceid, title, flags, url, revid, old_revid, user, length, comment, timestamp */
    ini_set('user_agent', 'ClueBot/2.0 (Training EditDB Scraper)');
    $data = unserialize(file_get_contents(
        'https://en.wikipedia.org/w/api.php?action=query&rawcontinue=1' .
        '&prop=revisions&rvprop=timestamp|user|comment&format=php&revids=' . urlencode($id)
    ));
    if (isset($data['query']['badrevids'])) {
        return false;
    }
    $data = current($data['query']['pages']);
    $change = array(
        'namespace' => namespace2name($data['ns']),
        'namespaceid' => $data['ns'],
        'title' => str_replace(namespace2name($data['ns']) . ':', '', $data['title']),
        'flags' => '',
        'url' => '',
        'revid' => $id,
        'old_revid' => '',
        'user' => $data['revisions'][0]['user'],
        'length' => '',
        'comment' => isset($data['revisions'][0]['comment']) ? $data['revisions'][0]['comment'] : '',
        'timestamp' => strtotime($data['revisions'][0]['timestamp']),
    );

    return $change;
}

function parseFeedData($feedData, $useOld = false)
{
    global $logger;
    $startTime = microtime(true);
    $urls = array(
        'https://en.wikipedia.org/w/api.php?action=query&rawcontinue=1&prop=revisions&titles=' .
        urlencode(($feedData['namespaceid'] == 0 ? '' : $feedData['namespace'] . ':') . $feedData['title']) .
        '&rvstartid=' . $feedData['revid'] . '&rvlimit=2&rvprop=timestamp|user|content&format=php',
    );
    list($api) = getUrlsInParallel($urls);
    $api = current($api['query']['pages']);
    $cb = getCbData($feedData['user'], $feedData['namespaceid'], $feedData['title'], $feedData['timestamp']);
    if (!(isset($cb['user_edit_count'])
        and isset($cb['user_distinct_pages'])
        and isset($cb['user_warns'])
        and isset($api['revisions'][1]['user'])
        and isset($cb['user_reg_time'])
        and isset($cb['common']['page_made_time'])
        and isset($cb['common']['creator'])
        and isset($cb['common']['num_recent_edits'])
        and isset($cb['common']['num_recent_reversions'])
        and isset($api['revisions'][0]['timestamp'])
        and isset($api['revisions'][0]['*'])
        and isset($api['revisions'][1]['timestamp'])
        and isset($api['revisions'][1]['*']))
    ) {
        $logger->addError("Failed to get all edit info: " . var_export($feedData, true) . ", " . var_export($cb, true));
        IRC::debug("Failed to get edit info for \x0315[[\x0307" . $feedData['title'] . "\x0315]] " .
                   "by \"\x0303" . $feedData['user'] . "\x0315\" (\x0312 " . $feedData['url'] . " \x0315)");
        return false;
    }
    $data = array(
        'EditType' => 'change',
        'EditID' => $feedData['revid'],
        'comment' => $feedData['comment'],
        'user' => $feedData['user'],
        'user_edit_count' => $cb['user_edit_count'],
        'user_distinct_pages' => $cb['user_distinct_pages'],
        'user_warns' => $cb['user_warns'],
        'prev_user' => $api['revisions'][1]['user'],
        'user_reg_time' => $cb['user_reg_time'],
        'common' => array(
            'page_made_time' => $cb['common']['page_made_time'],
            'title' => $feedData['title'],
            'namespace' => $feedData['namespace'],
            'creator' => $cb['common']['creator'],
            'num_recent_edits' => $cb['common']['num_recent_edits'],
            'num_recent_reversions' => $cb['common']['num_recent_reversions'],
        ),
        'current' => array(
            'minor' => (stripos($feedData['flags'], 'm') === false) ? 'false' : 'true',
            'timestamp' => strtotime($api['revisions'][0]['timestamp']),
            'text' => $api['revisions'][0]['*'],
        ),
        'previous' => array(
            'timestamp' => strtotime($api['revisions'][1]['timestamp']),
            'text' => $api['revisions'][1]['*'],
        ),
    );
    $feedData['startTime'] = $startTime;
    $feedData['all'] = $data;

    return $feedData;
}

function toXML($data)
{
    $xml = xmlize($data);

    return $xml;
}

function isVandalism($data, &$score)
{
    $fp = fsockopen(Db::getCurrentCoreNode(), Config::$coreport, $errno, $errstr, 15);
    if (!$fp) {
        return false;
    }
    fwrite($fp, str_replace('</WPEditSet>', '', toXML($data)));
    fflush($fp);
    $returnXML = '';
    $endeditset = false;
    while (!feof($fp)) {
        $returnXML .= fgets($fp, 4096);
        if (strpos($returnXML, '</WPEdit>') === false and !$endeditset) {
            fwrite($fp, '</WPEditSet>');
            fflush($fp);
            $endeditset = true;
        }
    }
    fclose($fp);
    $data = simplexml_load_string($returnXML);
    if ($data == null) {
        $score = 0;
        $isVand = false;
    } else {
        $score = (string)$data->WPEdit->score;
        $isVand = ((string)$data->WPEdit->think_vandalism) == 'true';
    }

    return $isVand;
}

function oldData($id)
{
    $feedData = genOldFeedData($id);
    if ($feedData === false) {
        return false;
    }
    $feedData = parseFeedData($feedData, true);
    if ($feedData === false) {
        return false;
    }
    $feedData = $feedData['all'];

    return $feedData;
}

function oldXML($ids)
{
    if (!is_array($ids)) {
        $ids = array($ids);
    }
    $feedData = array();
    foreach ($ids as $id) {
        $feedData[] = oldData($id);
    }

    return toXML($feedData);
}
