<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

error_log(getenv('HEROKU_API_KEY'));
error_log($mu->get_encrypt_string(getenv('HEROKU_API_KEY')));
