<?php

/*
daily010
→ daily020
  → daily030
    → daily040
    → get_youtube_play_count
      → get_results_batting
        → make_graph
          → backup_cloudapp
*/
include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);

error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/daily020.php';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
