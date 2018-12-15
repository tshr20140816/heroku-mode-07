<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

$url = 'http://the-outlets-hiroshima.com/static/detail/car';

$res = $mu->get_contents($url);

error_log($res);

$rc = preg_match('/<p id="parkingnow"><img src="(.+?)"/s', $res, $matches);

error_log(print_r($matches, TRUE));

$filePath = '/tmp/sample_image.jpg';
$res = file_get_contents($matches[1]);
error_log(strlen($res));
file_put_contents($filePath, $res);
$res = file_get_contents($filePath);
error_log(strlen($res));

$url = 'http://www.ocrwebservice.com/restservices/processDocument?language=japanese&outputformat=txt&gettext=true&getwords=true';
$url = 'http://www.ocrwebservice.com/restservices/processDocument?gettext=true';

$session = curl_init();
curl_setopt($session, CURLOPT_URL, $url);

$username = getenv('OCRWEBSERVICE_USER');
$license_code = getenv('OCRWEBSERVICE_LICENSE_CODE');
error_log($username);
error_log($license_code);
curl_setopt($session, CURLOPT_USERPWD, "$username:$license_code");

curl_setopt($session, CURLOPT_UPLOAD, true);
curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($session, CURLOPT_TIMEOUT, 200);
curl_setopt($session, CURLOPT_HEADER, false);
//curl_setopt($session, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($session, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

$fp = fopen($filePath, 'r');
curl_setopt($session, CURLOPT_INFILESIZE, filesize($filePath));
error_log('C010');
$result = curl_exec($session);
error_log('C020');
$httpCode = curl_getinfo($session, CURLINFO_HTTP_CODE);
curl_close($session);
fclose($fp);

error_log($httpCode);

$data = json_decode($result);

error_log(print_r($data, TRUE));
?>
