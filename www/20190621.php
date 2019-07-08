<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190621c($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621c($mu_)
{
    $url = 'https://spocale.com/team_and_players/12';
    $res = $mu_->get_contents($url, null, true);
    // error_log($res);
    
    $rc = preg_match_all('/<a href="\/games\/(.+?)">/', $res, $matches);
    error_log(print_r($matches, true));
    
    $url = 'https://spocale.com/games/' . $matches[1][0];
    $res = $mu_->get_contents($url, null, true);
    // error_log($res);
    
    $rc = preg_match('/<div class="time-wrap">.*?<.+?>(.+?)<.+?>.*?<.+?>(.+?)</s', $res, $match);
    
    error_log(print_r($match, true));
    
    $dt = strtotime(str_replace('.', '/', $match[1]) . ' ' . $match[2]);
    error_log(date('Y/m/d H:i', $dt));
    
    if (date('Ymd', $dt) != date('Ymd', strtotime('+9 hours'))) {
        return;
    }
    
    $rc = preg_match('/.+<div class="table-header">.*?<h4><i class="i tv"><\/i>テレビで視聴する<\/h4>(.+?)<div class="table-header">/s', $res, $match);
    // error_log(print_r($match, true));
    
    $tv = '';
    foreach (explode('<div class="table-list">', $match[1]) as $item) {
        // error_log(trim(preg_replace("/(\n| )+/s", ' ', strip_tags($item))));
        $tmp = trim(preg_replace("/(\n| )+/s", ' ', strip_tags($item)));
        $tmp = str_replace('~', '', $tmp);
        $tmp = trim(str_replace('LIVE', '', $tmp));
        if (strlen($tmp) > 0) { 
            // error_log($tmp);
            $tv .= ' ' . $tmp;
        }
    }
    error_log(date('m/d H:i', $dt) . $tv);
}

function func_20190621b($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $cookie = tempnam("/tmp", 'cookie_' . md5(microtime(true)));
    
    $options = [
        CURLOPT_ENCODING => 'gzip, deflate, br',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'DNT: 1',
            'Upgrade-Insecure-Requests: 1',
            ],
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ];
    
    $url = 'https://jr-central.co.jp/';
    $res = $mu_->get_contents($url, $options);
    
    /*
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/pc/ja/ti08.html';
    $res = $mu_->get_contents($url, $options);
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/common/data/common_ja.json';
    $res = $mu_->get_contents($url, $options);
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json';
    $res = $mu_->get_contents($url, $options);
    */
    unlink($cookie);
}

