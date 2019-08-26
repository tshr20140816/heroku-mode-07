<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$time_start = microtime(true);
error_log("${pid} START scripts/put_zoho_filesize_info.php " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

put_zoho_filesize_info($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function put_zoho_filesize_info($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);

    $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
    $res = $mu_->get_contents($url);

    $jobs = [];
    foreach (json_decode($res)->FILES as $item) {
        $docid = $item->DOCID;
        $url = "https://apidocs.zoho.com/files/v1/content/${docid}?authtoken=${authtoken_zoho}&scope=docsapi";
        $file_name = "/tmp/zoho_${docid}";
        $jobs[$file_name] = "'curl -sS -m 120 -w @/tmp/curl_write_out_option -D ${file_name} -o /dev/null ${url}'";
    }
    $curl_write_out_option = <<< __HEREDOC__
(%{time_total}s %{size_download}b) 
__HEREDOC__;
    file_put_contents('/tmp/curl_write_out_option', $curl_write_out_option);

    error_log($log_prefix . 'total count : ' . count($jobs));
    file_put_contents('/tmp/jobs.txt', implode("\n", $jobs));
    
    $line = 'rm -f /tmp/zoho_*';
    $res = null;
    error_log($log_prefix . $line);
    $time_start = microtime(true);
    exec($line, $res);
    $time_finish = microtime(true);
    foreach ($res as $one_line) {
        error_log($log_prefix . $one_line);
    }
    $res = null;

    $line = 'cat /tmp/jobs.txt | xargs -L 1 -P 2 -I{} bash -c {} 2>/tmp/xargs_log.txt';
    $res = null;
    error_log($log_prefix . $line);
    $time_start = microtime(true);
    exec($line, $res);
    $time_finish = microtime(true);
    foreach ($res as $one_line) {
        error_log($log_prefix . $one_line);
    }
    $res = null;
    error_log($log_prefix . file_get_contents('/tmp/xargs_log.txt'));
    error_log($log_prefix . 'Process Time : ' . substr(($time_finish - $time_start), 0, 6) . 's');
    unlink('/tmp/jobs.txt');
    unlink('/tmp/xargs_log.txt');
    unlink('/tmp/curl_write_out_option');
}
