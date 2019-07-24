<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

error_log(getenv('HEROKU_API_KEY'));
error_log(base64_encode(getenv('HEROKU_API_KEY')));
