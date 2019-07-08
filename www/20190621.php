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
    error_log(strlen($tmp));
    $tmp = json_decode($tmp);
    error_log(print_r($tmp, true));
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json';
    $res = $mu_->get_contents_proxy($url);
    $tmp = explode('</script>', $res);
    $tmp = json_decode(trim(end($tmp)), true);
    error_log(print_r($tmp, true));
    
}

