<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

error_log(base64_decode(getenv('TTRSS_USER')));
error_log($mu->get_encrypt_string(base64_decode(getenv('TTRSS_USER'))));
error_log(base64_decode(getenv('TTRSS_PASSWORD')));
error_log($mu->get_encrypt_string(base64_decode(getenv('TTRSS_PASSWORD'))));
