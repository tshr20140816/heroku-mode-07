<?php

include('/app/classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$html = <<< __HEREDOC__
<html><body>
<form method="POST" action="./get_encrypt_string.php">
<input type="text" name="original" />
<input type="submit" /> 
</form>
</body></html>
__HEREDOC__;

if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    $original_string = $_POST['original'];
    
    error_log($mu->get_encrypt_string($original_string));
} else {
    echo $html;
}

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
