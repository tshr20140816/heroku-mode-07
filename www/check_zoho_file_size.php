<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

check_zoho_file_size($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function check_zoho_file_size($mu_)
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
    if (count($job_list) === 0) {
        return;
    }
    $job_list = array_chunk($job_list, 4)[0];
    file_put_contents('/tmp/jobs.txt', implode("\n", $job_list));

    $line = 'cat /tmp/jobs.txt | xargs -t -L 1 -P 4 -I{} '
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
                $docids[$docid]['FILE_SIZE'] = (int)$match[1];
            }
            unlink($file_name);
        }
    }

    error_log($log_prefix . print_r($docids, true));
    error_log($log_prefix . 'Data Size : ' . number_format(strlen(base64_encode(bzcompress(serialize($docids))))));

    $pdo = $mu_->get_pdo();
    $statement_upsert = $pdo->prepare($sql_upsert);
    
    $rc = $statement_upsert->execute([':b_key' => 'apidocs.zoho.com',
                                      ':b_value' => base64_encode(bzcompress(serialize($docids))),
                                     ]);
    error_log($log_prefix . 'UPSERT $rc : ' . $rc);
    
    $pdo = null;
}
