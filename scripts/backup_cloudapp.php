    
<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$time_start = microtime(true);
error_log("${pid} START scripts/update_ttrss.php " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

if (count($argv) == 2) {
    backup_cloudapp($mu, $argv[1]);
}

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function backup_cloudapp($mu_, $file_name_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

}
