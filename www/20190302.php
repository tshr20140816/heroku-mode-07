<?php
include(dirname(__FILE__) . '/../classes/MyUtils.php');
$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));
$mu = new MyUtils();

$user_opendrive = base64_decode(getenv('OPENDRIVE_USER'));
$password_opendrive = base64_decode(getenv('OPENDRIVE_PASSWORD'));

$url = 'https://dev.opendrive.com/api/v1/session/login.json';

$post_data = ['username' => $user_opendrive, 'passwd' => $password_opendrive, 'version' => '1', 'partner_id' => '',];

$options = [
    CURLOPT_POST => true,
    CURLOPT_ENCODING => 'gzip, deflate, br',
    CURLOPT_POSTFIELDS => http_build_query($post_data),
];
$res = $mu->get_contents($url, $options);
error_log($res);

$data = json_decode($res);
error_log(print_r($data, true));

$session_id = $data->SessionID;

$url = "https://dev.opendrive.com/api/v1/users/info.json/${session_id}";
$res = $mu->get_contents($url);
error_log($res);

$data = json_decode($res);
error_log(print_r($data, true));

$file_name = '/tmp/test2.txt';

file_put_contents($file_name, 'TEST');

$file_size = filesize($file_name);
$fh = fopen($file_name, 'r');

$url = 'https://webdav.opendrive.com/' . pathinfo($file_name)['basename'];

$options = [
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => "${user_opendrive}:${password_opendrive}",
    CURLOPT_PUT => true,
    CURLOPT_INFILE => $fh,
    CURLOPT_INFILESIZE => $file_size,
];
$res = $mu->get_contents($url, $options);

error_log($res);

fclose($fh);

unlink($file_name);
