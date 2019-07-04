<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190621($mu, '/tmp/20190621dummy');
@unlink('/tmp/20190621dummy');

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621b($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $cookie = tempnam("/tmp", 'cookie_' . md5(microtime(true)));
    
    $options = [
        CURLOPT_ENCODING => 'gzip, deflate, br',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'DNT: 1',
            'Upgrade-Insecure-Requests: 1',
            ],
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ];
    
    $url = 'https://jr-central.co.jp/';
    $res = $mu_->get_contents($url, $options);
    
    /*
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/pc/ja/ti08.html';
    $res = $mu_->get_contents($url, $options);
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/common/data/common_ja.json';
    $res = $mu_->get_contents($url, $options);
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json';
    $res = $mu_->get_contents($url, $options);
    */
    unlink($cookie);
}

function func_20190621($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);

    $url = "https://webdav.hidrive.strato.com/users/${user_hidrive}/";
    
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_HTTPHEADER => ['Depth: 1',],
    ];
    
    $res = $mu_->get_contents($url, $options);
    
    foreach (explode('</D:response>', $res) as $item) {
        $rc = preg_match('/<D:href>(.+?)<.+?<lp1:creationdate>(.+?)</s', $item, $match);
        if ($rc === 1) {
            if (strtotime($match[2]) > strtotime('-20 hours')) {
                error_log(print_r($match, true));
            }
            // error_log(date('Y/m/d H:i:s', strtotime($match[2])));
        }
    }
    
}
