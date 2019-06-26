<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190621($mu, '/tmp/20190621dummy');
@unlink('/tmp/20190621dummy');

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621($mu_, $file_name_blog_) {
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://trafficinfo.westjr.co.jp/sp/chugoku.html';
    $res = $mu_->get_contents($url);
    $url = 'https://trafficinfo.westjr.co.jp/chugoku.html';
    $res = $mu_->get_contents($url);
    
}
