<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

$apcu = new APCUIterator();
error_log($apcu->getTotalSize());

$tmp = $mu->get_env('WEB_PROXY');

error_log($apcu->getTotalSize());
