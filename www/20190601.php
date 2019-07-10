<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = func_20190601($mu);


error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190601($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $url = 'https://www.jtb.co.jp/kokunai_tour/list/1301/?departure=HIJ&capacity=2&godate=20190830&traveldays=2&room=1&transportation=2&samehm=1&toursort=low&itemperpage=20';
    $res = $mu_->get_contents($url);
    
    error_log($res);
}
