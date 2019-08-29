<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$description = backup_etc($mu);

$time_finish = microtime(true);
$mu->post_blog_wordpress("${requesturi} [" . substr(($time_finish - $time_start), 0, 6) . 's]', $description);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function backup_etc($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = "https://webdav.hidrive.strato.com/users/${user_hidrive}/";
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_HTTPHEADER => ['Depth: 1',],
    ];
    $res = $mu_->get_contents($url, $options);

    $files = [];
    foreach (explode('</D:response>', $res) as $item) {
        $rc = preg_match('/<D:href>(.+?)<.+?<lp1:creationdate>(.+?)<.+?<lp1:getcontentlength>/s', $item, $match);
        if ($rc === 1) {
            if (strtotime($match[2]) > strtotime('-20 hours')) {
                $files[] = $match[1];
            }
        }
    }
}
