<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190621b($mu, '/tmp/20190621dummy');
@unlink('/tmp/20190621dummy');

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621b($mu_, $file_name_blog_) {
    $url = 'http://hyogo.rivercam.info/nishinomiya/detail/mukogawanamaze.html?' . hash('md5', microtime(true));
    $res = $mu_->get_contents($url);
    
    // error_log($res);
    $rc = preg_match('/.+?(\d+\/\d+ \d+:\d+).+?<td>(.+?)<img alt="上昇率" /s', $res, $match);
    
    // error_log(print_r($match, true));
    error_log($match[1] . ' ' . trim($match[2]));
}

function func_20190621($mu_, $file_name_blog_) {
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

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
    ];
    
    $url = 'https://trafficinfo.westjr.co.jp/sp/chugoku.html';
    $res = $mu_->get_contents($url, $options);
    /*
    $url = 'https://trafficinfo.westjr.co.jp/chugoku.html';
    $res = $mu_->get_contents($url, $options);
    */
    $res = mb_convert_encoding($res, 'UTF-8', 'SJIS');
    // error_log($res);
    $rc = preg_match_all('/<article .*?>(.+?)<\/article>/s', $res, $matches);
    
    $description = trim(strip_tags($matches[1][2]));
    $hash = hash('sha512', $res);
    error_log($hash);
    
    $livedoor_id = $mu_->get_env('LIVEDOOR_ID', true);
    $url = "http://blog.livedoor.jp/${livedoor_id}/search?q=ksjogpsnbujpo";
    $res = $mu_->get_contents($url);
}
