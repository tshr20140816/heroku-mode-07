<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

// $apcu = new APCUIterator();
// error_log($apcu->getTotalSize());

error_log(print_r(apcu_cache_info(true), true));

error_log($mu->get_env('WEB_PROXY'));

// error_log($apcu->getTotalSize());

error_log(print_r(apcu_cache_info(true), true));
