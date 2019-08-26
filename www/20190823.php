<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190823b($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190823b($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);

    $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
    $res = $mu_->get_contents($url);

    $jobs = [];
    foreach (json_decode($res)->FILES as $item) {
        $docid = $item->DOCID;
        $url = "https://apidocs.zoho.com/files/v1/content/${docid}?authtoken=${authtoken_zoho}&scope=docsapi";
        // $urls[$url] = null;
        $file_name = tempnam('/tmp', 'curl_' .  md5(microtime(true)));
        $jobs_all[] = "curl -I -o /dev/null ${url}";
    }

    /*
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 10,
    ];
    $size = 0;
    foreach (array_chunk($urls, 10, true) as $urls_chunk) {
        $list_contents = $mu_->get_contents_multi($urls_chunk, null, $multi_options);
        foreach ($list_contents as $res) {
            $size += strlen($res);
        }
        $list_contents = null;
    }
    */
    file_put_contents('/tmp/jobs.txt', implode("\n", $jobs));
    error_log(file_get_contents('/tmp/jobs.txt'));

    $line = 'cat /tmp/jobs.txt | parallel -j5 --joblog /tmp/joblog.txt 2>&1';
    $res = null;
    error_log($log_prefix . $line);
    $time_start = microtime(true);
    exec($line, $res);
    $time_finish = microtime(true);
    foreach ($res as $one_line) {
        error_log($log_prefix . $one_line);
    }
    $res = null;
    error_log(file_get_contents('/tmp/joblog.txt'));
    error_log($log_prefix . 'Process Time : ' . substr(($time_finish - $time_start), 0, 6) . 's');
    unlink('/tmp/jobs.txt');
    unlink('/tmp/joblog.txt');
    
    /*
    $percentage = substr($size / (5 * 1024 * 1024 * 1024) * 100, 0, 5);
    $size = number_format($size);

    error_log($log_prefix . "Zoho usage : ${size}Byte ${percentage}%");
    // file_put_contents($file_name_blog_, "\nZoho usage : ${size}Byte ${percentage}%\n\n", FILE_APPEND);
    */
}

function func_20190823($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);
    
    $base_name = 'composer.json';
    $file_name_ = '/tmp/composer.json';
    copy("../${base_name}", "/tmp/${base_name}");
    
    $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
    $res = $mu_->get_contents($url);
    foreach (json_decode($res)->FILES as $item) {
        if ($item->DOCNAME == $base_name) {
            error_log(print_r($item, true));
            $url = "https://apidocs.zoho.com/files/v1/content/" . $item->DOCID . "?authtoken=${authtoken_zoho}&scope=docsapi";
            $res = $mu_->get_contents($url);
            error_log($res);
            
            $url = "https://apidocs.zoho.com/files/v1/delete?authtoken=${authtoken_zoho}&scope=docsapi";
            $post_data = ['docid' => $item->DOCID,];
            $options = [CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query($post_data),
                        CURLOPT_HEADER => true,
                       ];
            $res = $mu_->get_contents($url, $options);
            // break;
        }
    }
    
    return;
    
    $jobs = <<< __HEREDOC__
curl -v -m 120 -X POST --compressed -o /dev/null -F filename={$base_name} -F content=@{$file_name_} https://www.yahoo.co.jp/sorry 2>&1
curl -v -m 120 -X POST --compressed -o /dev/null -F filename={$base_name} -F content=@{$file_name_} https://apidocs.zoho.com/files/v1/upload?authtoken={$authtoken_zoho}&scope=docsapi 2>&1
curl -v -m 120 -X POST --compressed -o /dev/null -F filename={$base_name} -F content=@{$file_name_} https://www.yahoo.co.jp/ 2>&1
__HEREDOC__;
    
    error_log($jobs);

    file_put_contents('/tmp/jobs.txt', $jobs);
    $line = 'cat /tmp/jobs.txt | parallel -j6 --joblog /tmp/joblog.txt 2>&1';
    $res = null;
    error_log($log_prefix . $line);
    $time_start = microtime(true);
    exec($line, $res);
    $time_finish = microtime(true);
    foreach ($res as $one_line) {
        error_log($log_prefix . $one_line);
    }
    $res = null;
    unlink('/tmp/jobs.txt');
    error_log(file_get_contents('/tmp/joblog.txt'));
    error_log($log_prefix . 'Process Time : ' . substr(($time_finish - $time_start), 0, 6) . 's');
}
