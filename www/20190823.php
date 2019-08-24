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
    
    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);
    $user_pcloud = $mu_->get_env('PCLOUD_USER', true);
    $password_pcloud = $mu_->get_env('PCLOUD_PASSWORD', true);
    $user_teracloud = $mu_->get_env('TERACLOUD_USER', true);
    $password_teracloud = $mu_->get_env('TERACLOUD_PASSWORD', true);
    $api_key_teracloud = $mu_->get_env('TERACLOUD_API_KEY', true);
    $node_teracloud = $mu_->get_env('TERACLOUD_NODE', true);
    $user_opendrive = $mu_->get_env('OPENDRIVE_USER', true);
    $password_opendrive = $mu_->get_env('OPENDRIVE_PASSWORD', true);
    $user_cloudme = $mu_->get_env('CLOUDME_USER', true);
    $password_cloudme = $mu_->get_env('CLOUDME_PASSWORD', true);
    $user_4shared = $mu_->get_env('4SHARED_USER', true);
    $password_4shared = $mu_->get_env('4SHARED_PASSWORD', true);
    $user_mega = $mu_->get_env('MEGA_USER', true);
    $password_mega = $mu_->get_env('MEGA_PASSWORD', true);
    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);
    
    $base_name = 'composer.json';
    copy("../${base_name}", "/tmp/${base_name}");
    
    $jobs = <<< __HEREDOC__
curl -v -m 120 -X DELETE -u {$user_hidrive}:{$password_hidrive} https://webdav.hidrive.strato.com/users/{$user_hidrive}/{$base_name}
curl -v -m 120 -X DELETE -u {$user_pcloud}:{$password_pcloud} https://webdav.pcloud.com/{$base_name}
curl -v -m 120 -X DELETE -u {$user_teracloud}:{$password_teracloud} https://{$node_teracloud}.teracloud.jp/dav/{$base_name}
curl -v -m 120 -X DELETE --digest -u {$user_cloudme}:{$password_cloudme} https://webdav.cloudme.com/{$user_cloudme}/xios/{$base_name}
curl -v -m 120 -X DELETE -u {$user_4shared}:{$password_4shared} https://webdav.4shared.com/{$base_name}
megarm -u {$user_mega} -p {$password_mega} /Root/{$base_name}
__HEREDOC__;
    
    file_put_contents('/tmp/jobs.txt', $jobs);
    $line = 'cat /tmp/jobs.txt | parallel -j6 --joblog /tmp/joblog.txt 2>&1';
    $res = null;
    error_log($log_prefix . $line);
    exec($line, $res);
    foreach ($res as $one_line) {
        error_log($log_prefix . $one_line);
    }
    $res = null;
    error_log(file_get_contents('/tmp/joblog.txt'));
    
    return;
    
    $jobs = <<< __HEREDOC__
curl -v -m 120 -X PUT --compressed -T {$file_name_} -u {$user_hidrive}:{$password_hidrive} https://webdav.hidrive.strato.com/users/{$user_hidrive}/{$base_name}
curl -v -m 120 -X PUT --compressed -T {$file_name_} -u {$user_pcloud}:{$password_pcloud} https://webdav.pcloud.com/{$base_name}
curl -v -m 120 -X PUT --compressed -T {$file_name_} -u {$user_teracloud}:{$password_teracloud} https://{$node_teracloud}.teracloud.jp/dav/{$base_name}
curl -v -m 120 -X PUT --compressed -T {$file_name_} --digest -u {$user_cloudme}:{$password_cloudme} https://webdav.cloudme.com/{$user_cloudme}/xios/{$base_name}
curl -v -m 120 -X PUT --compressed -T {$file_name_} -u {$user_4shared}:{$password_4shared} https://webdav.4shared.com/{$base_name}
megaput -u {$user_mega} -p {$password_mega} --path /Root/{$base_name} {$file_name_}
__HEREDOC__;
    
    // curl -v -m 120 -X POST --compressed -F filename={$base_name} -F content={$file_name_} https://apidocs.zoho.com/files/v1/upload?authtoken={{authtoken_zoho}&scope=docsapi

    file_put_contents('/tmp/jobs.txt', $jobs);
    $line = 'cat /tmp/jobs.txt | parallel -j6 --joblog /tmp/joblog.txt 2>&1';
    $res = null;
    error_log($log_prefix . $line);
    exec($line, $res);
    foreach ($res as $one_line) {
        error_log($log_prefix . $one_line);
    }
    $res = null;
    unlink('/tmp/jobs.txt');
    error_log(file_get_contents('/tmp/joblog.txt'));
}
