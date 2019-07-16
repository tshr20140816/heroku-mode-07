<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190716b($mu);
// func_20190716($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();


function func_20190716b($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $host = explode('@', $mu_->get_env('LOGGLY_ID', true))[0];
    $api_token = getenv('LOGGLY_API_TOKEN');
    
    $options = [CURLOPT_HTTPHEADER => ["Authorization: Bearer ${api_token}",],];
    
    $url = "https://${host}.loggly.com/apiv2/events/iterate?from=-3d&until=-1d&order=desc&size=50&q=" .
        urlencode('Fatal tag:' . getenv('HEROKU_APP_NAME'));
    $res = $mu_->get_contents($url, $options);
    
    error_log($res);
    
    $rc = preg_match_all('/.+?"raw". .\[.+?\] PHP .+/', $res, $matches);
    
    error_log(print_r($matches, true));
}

function func_20190716($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);
    $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
    $res = $mu_->get_contents($url);
    
    $urls = [];
    foreach (json_decode($res)->FILES as $item) {
        $docid = $item->DOCID;
        $url = "https://apidocs.zoho.com/files/v1/content/${docid}?authtoken=${authtoken_zoho}&scope=docsapi";
        $urls[$url] = null;
    }
    
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 3,
    ];
    $size = 0;
    foreach (array_chunk($urls, 10, true) as $urls_chunk) {
        $list_contents = $mu_->get_contents_multi($urls_chunk, null, $multi_options);
        foreach ($list_contents as $res) {
            $size += strlen($res);
        }
        $list_contents = null;
    }
    error_log(number_format($size));    
}
