<?php

/*
search_hotel_call
→ search_hotel
*/
include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);

error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/search_hotel.php';
$line = 'curl -m 3 -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . ' -H "User-Agent: search_hotel_call.php"' . " ${url} > /dev/null 2>&1 &";
error_log("${pid} ${line}");
exec($line);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
