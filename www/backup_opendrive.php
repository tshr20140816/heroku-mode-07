<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

backup_opendrive($mu);

$time_finish = microtime(true);
// $mu->post_blog_wordpress("${requesturi} [" . substr(($time_finish - $time_start), 0, 6) . 's]', $description);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function backup_opendrive($mu_)
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

    $files = [];
    foreach (explode('</D:response>', $res) as $item) {
        $rc = preg_match('/<D:href>(.+?)<.+?<lp1:creationdate>(.+?)<.+?<lp1:getcontentlength>/s', $item, $match);
        if ($rc === 1) {
            if (strtotime($match[2]) > strtotime('-20 hours')) {
                $files[] = $match[1];
            }
        }
    }

    $user_opendrive = $mu_->get_env('OPENDRIVE_USER', true);
    $password_opendrive = $mu_->get_env('OPENDRIVE_PASSWORD', true);

    foreach ($files as $file) {
        $base_name = pathinfo($file)['basename'];
        $url = "https://webdav.hidrive.strato.com/users/${user_hidrive}/${base_name}";
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $res = $mu_->get_contents($url, $options);
        @unlink("/tmp/${base_name}");
        file_put_contents("/tmp/${base_name}", $res);

        $file_size = filesize("/tmp/${base_name}");
        $fh = fopen("/tmp/${base_name}", 'rb');

        $url = 'https://webdav.opendrive.com/' . $base_name;
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${user_opendrive}:${password_opendrive}",
            CURLOPT_PUT => true,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => $file_size,
            CURLOPT_HEADER => true,
        ];
        $res = $mu_->get_contents($url, $options);

        fclose($fh);
        unlink("/tmp/${base_name}");
    }
}
