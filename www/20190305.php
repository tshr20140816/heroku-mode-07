<?php
include(dirname(__FILE__) . '/../classes/MyUtils.php');
$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));
$mu = new MyUtils();

$rc = func_test($mu, '/tmp/dummy');

function func_test($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $url = getenv('TEST_URL_01');
    $basic_user = getenv('BASIC_USER');
    $basic_password = getenv('BASIC_PASSWORD');
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "${basic_user}:${basic_password}",],
    ];
    $res = $mu_->get_contents($url, $options);
}
