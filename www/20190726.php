<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = func_20190726($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190726($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $hatena_id = $mu_->get_env('HATENA_ID', true);
    $hatena_blog_id = $mu_->get_env('HATENA_BLOG_ID', true);
    $hatena_api_key = $mu_->get_env('HATENA_API_KEY', true);
    
    $url = "https://blog.hatena.ne.jp/${hatena_id}/${hatena_blog_id}/atom/entry";
    
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "${hatena_id}:${hatena_api_key}",
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => ['Expect:',],
    ];
    
    $res = $mu_->get_contents($url, $options);
    
    error_log($res);
}
