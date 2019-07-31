<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$url = 'https://github.com/tshr20140816/heroku-mode-07/tree/master/www';

$res = $mu->get_contents($url);

// error_log($res);

$rc = preg_match_all('/<a .+? title="(\d+?)\.php"/', $res, $matches);

error_log(print_r($matches, ture));
