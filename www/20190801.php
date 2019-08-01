<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190801($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190801($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $user = getenv('TEST_USER');
    $password = getenv('TEST_PASSWORD');
    
    $imap = imap_open('{imap.mail.yahoo.co.jp:993/ssl}', $user, $password);
    
    $list = imap_list($imap, '{imap.mail.yahoo.co.jp:993/ssl}', '*');
    
    error_log(print_r($list, true));
    
    imap_close($imap);
}