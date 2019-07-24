<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$ueragent = $_SERVER['HTTP_USER_AGENT'];
error_log("${pid} USER AGENT : ${ueragent}");

$mu = new MyUtils();

header('Content-Type: text/calendar');
if ($ueragent != $mu->get_env('USER_AGENT_ICS')) {
    error_log("${pid} USER AGENT NG");
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR";
    $mu->post_blog_wordpress('/ical.php ' . substr((microtime(true) - $time_start), 0, 6) . 's', 'x ' . $_SERVER['HTTP_X_FORWARDED_FOR']);
    exit();
}

$pdo = $mu->get_pdo();

$sql = 'SELECT T1.ical_data FROM t_ical T1';
$ical_data = '';
foreach ($pdo->query($sql) as $row) {
    $ical_data = $row['ical_data'];
    break;
}

$pdo = null;

if ($ical_data == '') {
    error_log("${pid} DATA NONE");
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nEND:VCALENDAR";
} else {
    error_log("${pid} OK");
    echo gzdecode(base64_decode($ical_data));
}

$res = file_get_contents('/tmp/_netblocks.google.com.txt');
error_log("${pid} ${res}");
$rc = preg_match_all('/ip4:(.+?) /', $res, $matches);

$target_ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
$is_google = false;
foreach ($matches[1] as $cidr) {
    list($base_ipaddress, $subnetmask) = explode('/', $cidr);
    if (ip2long($target_ipaddress) >> (32 - $subnetmask) === ip2long($base_ipaddress) >> (32 - $subnetmask)) {
        $is_google = true;
        break;
    }
}
$result = $is_google === true ? '⭕' : '❌';

$time_finish = microtime(true);
$mu->post_blog_wordpress_async('/ical.php ' . substr(($time_finish - $time_start), 0, 6) . 's', "${target_ipaddress} ${result}");
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');

exit();
