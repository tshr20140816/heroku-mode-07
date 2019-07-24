<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

error_log(base64_decode(getenv('CLOUDMERSIVE_API_KEY')));
error_log($mu->get_encrypt_string(base64_decode(getenv('CLOUDMERSIVE_API_KEY'))));
