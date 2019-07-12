<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$sql = <<< __HEREDOC__
UPDATE t_env
   SET value = CASE value WHEN 1 THEN 2 ELSE 1 END
 WHERE key = 'TTRSS_SELECTED'
__HEREDOC__;

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();
