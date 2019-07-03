<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$time_start = microtime(true);
error_log("${pid} START scripts/update_ttrss.php " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

if (count($argv) == 2) {
    backup_cloudapp($mu, $argv[1]);
}

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function backup_cloudapp($mu_, $file_name_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $user_cloudapp = $mu_->get_env('CLOUDAPP_USER', true);
    $password_cloudapp = $mu_->get_env('CLOUDAPP_PASSWORD', true);

    $url = 'http://my.cl.ly/items/new';
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
        CURLOPT_HTTPHEADER => ['Accept: application/json',],
    ];
    $res = $mu_->get_contents($url, $options);
    $json = json_decode($res);

    $post_data = [
        'AWSAccessKeyId' => $json->params->AWSAccessKeyId,
        'key' => $json->params->key,
        'policy' => $json->params->policy,
        'signature' => $json->params->signature,
        'success_action_redirect' => $json->params->success_action_redirect,
        'acl' => $json->params->acl,
        'file' => new CURLFile($file_name_, 'text/plain', pathinfo($file_name_)['basename']),
    ];
    $options = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    $res = $mu_->get_contents($json->url, $options);
    $rc = preg_match('/Location: (.+)/i', $res, $match);

    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
        CURLOPT_HTTPHEADER => ['Accept: application/json',],
        CURLOPT_HEADER => true,
    ];
    $res = $mu_->get_contents(trim($match[1]), $options);

    unlink($file_name_);
}
