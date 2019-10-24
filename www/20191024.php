<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = check_train2($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function check_train2($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
        
    $cloudinary_cloud_name = $mu_->get_env('CLOUDINARY_CLOUD_NAME', true);
    $cloudinary_api_key = $mu_->get_env('CLOUDINARY_API_KEY', true);
    $cloudinary_api_secret = $mu_->get_env('CLOUDINARY_API_SECRET', true);
    
    $time = time();
    $hash = hash('sha512', $url);
    $line = "curl -u ${cloudinary_api_key}:${cloudinary_api_secret} https://api.cloudinary.com/v1_1/${cloudinary_cloud_name}/usage";
    $res = $mu_->cmd_execute($line);
    
    error_log(print_r(json_decode($res[0]), true));
}
