<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190716c($mu);
// func_20190716b($mu);
// func_20190716($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190716c($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $user_teracloud = $mu_->get_env('TERACLOUD_USER', true);
    $password_teracloud = $mu_->get_env('TERACLOUD_PASSWORD', true);
    $api_key_teracloud = $mu_->get_env('TERACLOUD_API_KEY', true);
    $node_teracloud = $mu_->get_env('TERACLOUD_NODE', true);

    // $url = "https://${node_teracloud}.teracloud.jp/v2/api/fileproperties/";
    $url = "https://${node_teracloud}.teracloud.jp/v2/api/dataset/;type=all;recursive=true";
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "${user_teracloud}:${password_teracloud}",
        CURLOPT_HTTPHEADER => ["X-TeraCLOUD-API-KEY: ${api_key_teracloud}",],
    ];
    $res = $mu_->get_contents($url, $options);

    error_log(print_r(json_decode($res, true), true));
}

function func_20190716b($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $host = explode('@', $mu_->get_env('LOGGLY_ID', true))[0];
    $api_token = getenv('LOGGLY_API_TOKEN');
    
    $options = [CURLOPT_HTTPHEADER => ["Authorization: Bearer ${api_token}",],];
    
    /*
    $url = "https://${host}.loggly.com/apiv2/events/iterate?from=-7d&until=now&order=asc&size=1000&q=" .
        urlencode('Warning -loggly tag:' . getenv('HEROKU_APP_NAME'));
    */
    $url = "https://${host}.loggly.com/apiv2/events/iterate?from=-7d&until=now&order=desc&size=1000&q=" .
        urlencode('(Fatal OR Warning) AND (-loggly OR -"raw" OR -"unparsed" OR -"logmsg" OR -"level") tag:' . getenv('HEROKU_APP_NAME'));
    $res = $mu_->get_contents($url, $options);
    
    // error_log($res);
    
    $rc = preg_match_all('/\s+?"raw": "\[\d+-...-\d+ \d+:\d+:\d+ UTC\] PHP .+/', $res, $matches);
    
    $list = [];
    foreach ($matches[0] as $item) {
        $rc = preg_match('/\/\d+\.php /', $item);
        if ($rc == 0) {
            $list[] = $item;
        }
    }
    error_log(print_r($list, true));
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
