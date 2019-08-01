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
    
    // $list = imap_list($imap, '{imap.mail.yahoo.co.jp:993/ssl}', '*');
    // error_log(print_r($list, true));
    
    for ($i = 500; $i > 0; $i--) {
        $header = imap_headerinfo($imap, $i);
        error_log(print_r($header, true));
        error_log(date('Ymd', $header->udate));
        
        if (date('Ym', $header->udate) == '201902') {
            $rc = imap_mail_move($imap, $i, '2019');
            error_log('imap_mail_move : ' . $rc);
            $rc = imap_expunge($imap);
            error_log('imap_expunge : ' . $rc);
        }
    }
    
    imap_close($imap);
}
