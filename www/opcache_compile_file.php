<?php

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$res = [];
exec('ls *.php', $res);

error_log(print_r($res, true));

$rc = opcache_compile_file('../classes/MyUtils.php');
error_log("${pid} MyUtils.php : ${rc}");

foreach ($res as $file_name) {
    if (preg_match('/^\d+\.php$/', $file_name) === 1) {
        continue;
    }
    $rc = opcache_compile_file("./${file_name}");
    error_log("${pid} ${file_name} : ${rc}");
}

error_log("${pid} opcache_get_configuration : " . print_r(opcache_get_configuration(), true));

error_log("${pid} opcache_get_status : " . print_r(opcache_get_status(), true));

error_log("${pid} memory_get_usage : " . number_format(memory_get_usage()) . 'byte');

$res = file_get_contents('/tmp/php_error.txt');
error_log("\n" . $res);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();
