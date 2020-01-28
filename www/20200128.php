<?php

include('/app/classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

f20200128($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function f20200128($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $urls[$mu_->get_env('URL_OUTLET')] = null;
    
    for ($i = 1; $i < 5; $i++) {
        $urls[$mu_->get_env('URL_PARKING_1') . '?park_id=' . $i . '&mode=pc'] = null;
    }
    
    $list_contents = $mu_->get_contents_multi($urls);
    
    $res = $res = $list_contents[$mu_->get_env('URL_OUTLET')];
    $rc = preg_match('/<p id="parkingnow"><img src="(.+?)"/s', $res, $matches);
    $res = $mu_->get_contents($matches[1]);
    $hash_text = hash('sha512', $res);

    $pdo = $mu_->get_pdo();

    $sql = <<< __HEREDOC__
SELECT T1.parse_text
  FROM t_imageparsehash T1
 WHERE T1.group_id = 1
   AND T1.hash_text = :b_hash_text;
__HEREDOC__;

    $statement = $pdo->prepare($sql);
    $rc = $statement->execute([':b_hash_text' => $hash_text]);
    error_log($log_prefix . "SELECT RESULT : ${rc}");
    $results = $statement->fetchAll();
    error_log($log_prefix . '$results : ' . print_r($results, true));

    $parse_text = '';
    foreach ($results as $row) {
        $parse_text = $row['parse_text'];
        break;
    }

    $pdo = null;
    
    error_log($parse_text);
}
