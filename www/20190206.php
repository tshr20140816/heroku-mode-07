<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$filename = 'useragent.txt';
$filepath = '/app/' . $filename;

$filesize = filesize($filepath);
$fh = fopen($filepath, 'r');

$ch = curl_init('https://webdav.hidrive.strato.com/users/' . getenv('HIDRIVE_USER'). '/test2.txt');

curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt($ch, CURLOPT_USERPWD, getenv('HIDRIVE_USER') . ':' . getenv('HIDRIVE_PASSWORD'));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_INFILE, $fh);
curl_setopt($ch, CURLOPT_INFILESIZE, $filesize);

$res = curl_exec($ch);

fclose($fh);

error_log(print_r($res, true));

