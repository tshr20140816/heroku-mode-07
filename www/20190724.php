<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

$apcu = new APCUIterator();
error_log($apcu->getTotalSize());
