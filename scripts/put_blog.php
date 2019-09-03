<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$time_start = microtime(true);
error_log("${pid} START scripts/put_blog.php " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

switch (count($argv)) {
    case 2:
        $mu->post_blog_wordpress(base64_decode($argv[1]));
        break;
    case 3:
        $mu->post_blog_wordpress(base64_decode($argv[1]), base64_decode($argv[2]));
        break;
    case 4:
        $mu->post_blog_wordpress(base64_decode($argv[1]), base64_decode($argv[2]), base64_decode($argv[3]));
        break;
}

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
