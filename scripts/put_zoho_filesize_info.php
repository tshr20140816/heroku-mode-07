<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START scripts/put_zoho_filesize_info.php " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

put_zoho_filesize_info($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function put_zoho_filesize_info($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
}
