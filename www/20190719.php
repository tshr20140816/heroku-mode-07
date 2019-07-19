<?php
include(dirname(__FILE__) . '/../classes/MyUtils.php');
$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));
$mu = new MyUtils();
$rc = func_20190719($mu);
$time_finish = microtime(true);
error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();
function func_20190719($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

}
