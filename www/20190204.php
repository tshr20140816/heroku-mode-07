<?php

require_once 'HTTP/WebDAV/Client.php';

// include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

// $mu = new MyUtils();

$client = new HTTP_WebDAV_Client_Stream();
// https://stackoverflow.com/questions/3369675/php-idisk-webdav-client
