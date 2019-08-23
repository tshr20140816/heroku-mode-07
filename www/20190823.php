<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190823($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190823($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $user = $mu_->get_env('PCLOUD_USER', true);
    $password = $mu_->get_env('PCLOUD_PASSWORD', true);
    
    $base_name = 'composer.lock';
    copy("../${base_name}", "/tmp/${base_name}");
    
    $url = "https://webdav.pcloud.com/${base_name}";
        
    $line = 'curl -v -X DELETE -u ' . "${user}:${password} " . $url;
    error_log($log_prefix . $line);
    $res = null;
    exec($line, $res);
    error_log($log_prefix . print_r($res, true));
    $res = null;
    
    $line = "curl -v -X PUT -T /tmp/${base_name} -u ${user}:${password} --compressed ${url}";
    error_log($log_prefix . $line);
    $res = null;
    exec($line, $res);
    error_log($log_prefix . print_r($res, true));
    $res = null;
}
