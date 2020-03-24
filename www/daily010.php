<?php

/*
daily010
→ daily020
  → daily030
    → daily040
    → get_youtube_play_count
      → make_graph
        → backup_cloudapp
          → backup_opendrive
*/
include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);

error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/daily020.php?index=100';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
