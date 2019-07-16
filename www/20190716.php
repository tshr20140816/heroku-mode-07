<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190716($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190716($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $options = [CURLOPT_HEADER => true,
               CURLOPT_NOBODY => true,
               ];
    
    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);
    $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
    $res = $mu_->get_contents($url);
    $size = 0;
    foreach (json_decode($res)->FILES as $item) {
        $docid = $item->DOCID;
        $url = "https://apidocs.zoho.com/files/v1/content/${docid}?authtoken=${authtoken_zoho}&scope=docsapi";
        $res = $mu_->get_contents($url, $options);
        // $size += strlen($res);
        error_log($res);
        break;
    }
    // error_log(number_format($size));
}
