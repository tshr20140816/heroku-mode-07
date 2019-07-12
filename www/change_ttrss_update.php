<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$sql_update = <<< __HEREDOC__
UPDATE m_env
   SET value = CASE value WHEN '1' THEN '2' ELSE '1' END
 WHERE key = 'TTRSS_SELECTED'
__HEREDOC__;

$sql_select = <<< __HEREDOC__
SELECT M1.value
  FROM m_env M1
 WHERE M1.key = 'URL_TTRSS_' || ( SELECT M2.value
                                    FROM m_env M2
                                   WHERE M2.key = 'TTRSS_SELECTED'
                                )
__HEREDOC__;

$pdo = $mu->get_pdo();
$statement = $pdo->prepare($sql_update);
$rc = $statement->execute();

foreach ($pdo->query($sql_select) as $row) {
    $url = $row['value'];
}

$pdo = null;

header('Content-Type: text/plain');
echo substr(parse_url($url, PHP_URL_HOST), 0, 5);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();
