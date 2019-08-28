<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190823d($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190823d($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $sql_select = <<< __HEREDOC__
SELECT T1.value
  FROM t_data_log T1
 WHERE T1.key = :b_key
__HEREDOC__;

    $sql_upsert = <<< __HEREDOC__
INSERT INTO t_data_log VALUES(:b_key, :b_value)
    ON CONFLICT (key)
    DO UPDATE SET value = :b_value
__HEREDOC__;

    $pdo = $mu_->get_pdo();
    $statement_select = $pdo->prepare($sql_select);
    $statement_upsert = $pdo->prepare($sql_upsert);
    
    $statement_select->execute([':b_key' => 'apidocs.zoho.com']);
    $result = $statement_select->fetchAll();
    $docids = [];
    if (count($result) != 0) {
        $docids = unserialize(bzdecompress(base64_decode($result[0]['value'])));
    }
    $result = null;
    $pdo = null;

    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);
    
    $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
    $res = $mu_->get_contents($url);

    foreach (json_decode($res)->FILES as $item) {
        $docid = $item->DOCID;
        if (array_key_exists($docid, $docids) === true 
            && $docids[$docid]['DOCNAME'] === $item->DOCNAME
            && $docids[$docid]['CREATED_TIME_IN_MILLISECONDS'] === $item->CREATED_TIME_IN_MILLISECONDS
           ) {
            $docids[$docid]['IS_EXISTS'] = true;
        } else {
            $docids[$docid] = ['DOCNAME' => $item->DOCNAME,
                               'CREATED_TIME_IN_MILLISECONDS' => $item->CREATED_TIME_IN_MILLISECONDS,
                               'FILE_SIZE' => 0,
                               'IS_EXISTS' => true,
                              ];
        }
    }
    
    $unset_list = [];
    $job_list = [];
    foreach ($docids as $key => $value) {
        if ($value['IS_EXISTS'] === false) {
            $unset_list[] = $key;
        } else if ($value['FILE_SIZE'] === 0) {
            $job_list[] = $key;
        }
    }
    foreach($unset_list as $key) {
        unset($docids[$key]);
    }
    $unset_list = null;

    error_log($log_prefix . 'total count : ' . count($job_list));
    $job_list = array_chunk($job_list, 5)[0];
    file_put_contents('/tmp/jobs.txt', implode("\n", array_chunk($job_list, 10)[0]));

    $curl_write_out_option = <<< __HEREDOC__
(%{time_total}s %{size_download}b) 
__HEREDOC__;
    
    $line = 'cat /tmp/jobs.txt | xargs -t -L 1 -P 5 -I{} '
        . 'curl -sS -m 120 -w "(%{time_total}s %{size_download}b) " -D /tmp/zoho_{} -o /dev/null '
        . "https://apidocs.zoho.com/files/v1/content/{}?authtoken=${authtoken_zoho}&scope=docsapi 2>/tmp/xargs_log.txt";
    /*
    $line = 'cat /tmp/jobs.txt | xargs -t -L 1 -P 2 -I{} '
        . "bash -c 'curl -sS -m 120 -w @/tmp/curl_write_out_option -D /tmp/zoho_{} "
        . "https://apidocs.zoho.com/files/v1/content/{}?authtoken=${authtoken_zoho}&scope=docsapi 2>>/tmp/xargs_log.txt'";
    */
    $res = null;
    error_log($log_prefix . $line);
    $time_start = microtime(true);
    exec($line, $res);
    $time_finish = microtime(true);
    foreach ($res as $one_line) {
        error_log($log_prefix . $one_line);
    }
    $res = null;
    error_log($log_prefix . 'Error : ' . file_get_contents('/tmp/xargs_log.txt'));
    error_log($log_prefix . 'Process Time : ' . substr(($time_finish - $time_start), 0, 6) . 's');
    unlink('/tmp/jobs.txt');
    unlink('/tmp/xargs_log.txt');
    
    foreach ($job_list as $docid) {
        $file_name = "/tmp/zoho_${docid}";
        if (!file_exists($file_name) || filesize($file_name) === 0) {
            error_log($log_prefix . 'File None : ' . $file_name);
        } else {
            $res = file_get_contents($file_name);
            $rc = preg_match('/Content-Length: (\d+)/', $res, $match);
            if ($rc === 1) {
                error_log($log_prefix . $match[1] . ' : ' . trim($docid, "'"));
                // $size += (int)$match[1];
                $docids[$docid]['FILE_SIZE'] = (int)$match[1];
            }
            unlink($file_name);
        }
    }
    
    error_log(print_r($docids, true));
    error_log(strlen(base64_encode(bzcompress(serialize($docids)))));
    
    $pdo = $mu_->get_pdo();
    $statement_upsert = $pdo->prepare($sql_upsert);
    
    $rc = $statement_upsert->execute([':b_key' => 'apidocs.zoho.com',
                                      ':b_value' => base64_encode(bzcompress(serialize($docids))),
                                     ]);
    error_log($log_prefix . 'UPSERT $rc : ' . $rc);
    
    $pdo = null;
    return;
    
    // $jobs = array_chunk($jobs, 2, true)[0];

    error_log($log_prefix . 'total count : ' . count($docids));
    file_put_contents('/tmp/jobs.txt', implode("\n", array_keys($docids)));

    $line = 'cat /tmp/jobs.txt | xargs -t -L 1 -P 7 -I{} '
        . 'curl -sS -m 120 -w "(%{time_total}s %{size_download}b) " -D /tmp/zoho_{} -o /dev/null '
        . "https://apidocs.zoho.com/files/v1/content/{}?authtoken=${authtoken_zoho}&scope=docsapi 2>/tmp/xargs_log.txt";
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

    $size = 0;
    foreach ($jobs as $key => $value) {
        if (!file_exists($key) || filesize($key) === 0) {
            error_log('File None : ' . $value);
        } else {
            $res = file_get_contents($key);
            $rc = preg_match('/Content-Length: (\d+)/', $res, $match);
            error_log($log_prefix . $match[1] . ' : ' . trim($value, "'"));
            $size += (int)$match[1];
            unlink($key);
        }
    }

    $percentage = substr($size / (5 * 1024 * 1024 * 1024) * 100, 0, 5);
    $size = number_format($size);

    error_log($log_prefix . "Zoho usage : ${size}Byte ${percentage}%");
    // file_put_contents($file_name_blog_, "\nZoho usage : ${size}Byte ${percentage}%\n\n", FILE_APPEND);
}

function func_20190823c($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);

    $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
    $res = $mu_->get_contents($url);

    $jobs = [];
    foreach (json_decode($res)->FILES as $item) {
        $docid = $item->DOCID;
        $url = "https://apidocs.zoho.com/files/v1/content/${docid}?authtoken=${authtoken_zoho}&scope=docsapi";
        $file_name = tempnam('/tmp', 'curl_' .  md5(microtime(true)));
        $jobs[$file_name] = "'curl -sS -m 120 -w @/tmp/curl_write_out_option -D ${file_name} -o /dev/null ${url}'";
    }
    $curl_write_out_option = <<< __HEREDOC__
(%{time_total}s %{size_download}b) 
__HEREDOC__;
    file_put_contents('/tmp/curl_write_out_option', $curl_write_out_option);

    error_log($log_prefix . 'total count : ' . count($jobs));
    file_put_contents('/tmp/jobs.txt', implode("\n", $jobs));

    $line = "cat /tmp/jobs.txt | xargs -L 1 -P 2 -I{} bash -c {} 2>/tmp/xargs_log.txt";
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

    $size = 0;
    foreach ($jobs as $key => $value) {
        if (!file_exists($key) || filesize($key) === 0) {
            error_log('File None : ' . $value);
        } else {
            $res = file_get_contents($key);
            $rc = preg_match('/Content-Length: (\d+)/', $res, $match);
            error_log($log_prefix . $match[1] . ' : ' . trim($value, "'"));
            $size += (int)$match[1];
            unlink($key);
        }
    }

    $percentage = substr($size / (5 * 1024 * 1024 * 1024) * 100, 0, 5);
    $size = number_format($size);

    error_log($log_prefix . "Zoho usage : ${size}Byte ${percentage}%");
    // file_put_contents($file_name_blog_, "\nZoho usage : ${size}Byte ${percentage}%\n\n", FILE_APPEND);
}

function func_20190823b($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $authtoken_zoho = $mu_->get_env('ZOHO_AUTHTOKEN', true);

    $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
    $res = $mu_->get_contents($url);

    $jobs = [];
    $job = null;
    foreach (json_decode($res)->FILES as $item) {
        $docid = $item->DOCID;
        $url = "https://apidocs.zoho.com/files/v1/content/${docid}?authtoken=${authtoken_zoho}&scope=docsapi";
        $file_name = tempnam('/tmp', 'curl_' .  md5(microtime(true)));
        $jobs[$file_name] = "curl -D ${file_name} -o /dev/null ${url}";
    }
    
    file_put_contents('/tmp/jobs.txt', implode("\n", $jobs));

    $size = 0;
    for ($i = 0; $i < 5; $i++) {
        clearstatcache();
        $jobs_new = [];
        $line = 'cat /tmp/jobs.txt | parallel -j2 --joblog /tmp/joblog.txt 2>&1';
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

        foreach ($jobs as $key => $value) {
            if (!file_exists($key) || filesize($key) === 0) {
                $jobs_new[$key] = $value;
            } else {
                $res = file_get_contents($key);
                // error_log($log_prefix . $res);
                $rc = preg_match('/Content-Length: (\d+)/', $res, $match);
                error_log($match[1]);
                $size += (int)$match[1];
                unlink($key);
            }
        }
        error_log('jobs_new count : ' . count($jobs_new));
        if (count($jobs_new) === 0) {
            break;
        }
        // error_log(print_r($jobs_new, true));
        $jobs = $jobs_new;
        file_put_contents('/tmp/jobs.txt', implode("\n", $jobs_new));
    }

    $percentage = substr($size / (5 * 1024 * 1024 * 1024) * 100, 0, 5);
    $size = number_format($size);

    error_log($log_prefix . "Zoho usage : ${size}Byte ${percentage}%");
    // file_put_contents($file_name_blog_, "\nZoho usage : ${size}Byte ${percentage}%\n\n", FILE_APPEND);
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
