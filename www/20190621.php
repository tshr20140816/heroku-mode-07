<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190621($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/common/data/common_ja.json';
    $res = $mu_->get_contents_proxy($url);
    $tmp = explode('</script>', $res);
    $tmp = trim(end($tmp));
    error_log($tmp);
    error_log(mb_detect_encoding($tmp));
    $rc = preg_match('/"station": {(.+?)}/s', $tmp, $match);
    error_log(print_r($match, true));
    $json = json_decode('{' . $match[1] . '}', true);
    error_log(print_r($json, true));
    
    /*
    $json = json_decode($tmp, true);
    error_log('json_last_error : ' . json_last_error());
    error_log('json_last_error_msg : ' . json_last_error_msg());
    */
    /*
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json';
    $res = $mu_->get_contents_proxy($url);
    $tmp = explode('</script>', $res);
    $tmp = json_decode(trim(end($tmp)), true);
    error_log(print_r($tmp, true));
    */
}

