<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$rc = apcu_clear_cache();

$mu = new MyUtils();

//

$url = 'http://www.carp.co.jp/_calendar/list.html';
$res = $mu->get_contents($url, null, true);

//

$yyyy = date('Y');
$url = "https://e-moon.net/calendar_list/calendar_moon_${yyyy}/";
$res = $mu->get_contents($url, null, true);

//

$url = 'https://www.w-nexco.co.jp/traffic_info/construction/traffic.php?fdate='
    . date('Ymd', strtotime('+1 day'))
    . '&tdate='
    . date('Ymd', strtotime('+14 day'))
    . '&ak=1&ac=1&kisei%5B%5D=901&dirc%5B%5D=1&dirc%5B%5D=2&order=2&ronarrow=0'
    . '&road%5B%5D=1011&road%5B%5D=1912&road%5B%5D=1020&road%5B%5D=225A&road%5B%5D=1201'
    . '&road%5B%5D=1222&road%5B%5D=1231&road%5B%5D=234D&road%5B%5D=1232&road%5B%5D=1260';
$res = $mu->get_contents($url, null, true);

//

for ($j = 0; $j < 4; $j++) {
    $yyyy = date('Y', strtotime('+' . $j . ' years'));

    $url = 'http://calendar-service.net/cal?start_year=' . $yyyy
        . '&start_mon=1&end_year=' . $yyyy . '&end_mon=12'
        . '&year_style=normal&month_style=numeric&wday_style=ja_full&format=csv&holiday_only=1&zero_padding=1';

    $res = $mu->get_contents($url, null, true);
}

//

$start_yyyy = date('Y');
$start_m = date('n');
$finish_yyyy = date('Y', strtotime('+3 month'));
$finish_m = date('n', strtotime('+3 month'));

$url = 'http://calendar-service.net/cal?start_year=' . $start_yyyy
    . '&start_mon=' . $start_m . '&end_year=' . $finish_yyyy . '&end_mon=' . $finish_m
    . '&year_style=normal&month_style=numeric&wday_style=ja_full&format=csv&holiday_only=1&zero_padding=1';

$res = $mu->get_contents($url, null, true);

//

$yyyy = (int)date('Y');
for ($j = 0; $j < 2; $j++) {
    $post_data = ['from_year' => $yyyy];

    $res = $mu->get_contents(
        'http://www.calc-site.com/calendars/solar_year',
        [CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        ],
        true
    );

    $yyyy++;
}

//

$area_id = $mu->get_env('AREA_ID');
for ($j = 0; $j < 4; $j++) {
    $timestamp = strtotime(date('Y-m-01') . " +${j} month");
    $yyyy = date('Y', $timestamp);
    $mm = date('m', $timestamp);
    /*
    $res = $mu->get_contents('https://eco.mtk.nao.ac.jp/koyomi/dni/'
                             . $yyyy . '/s' . $area_id . $mm . '.html', null, true);
    */
    $res = $mu->get_contents("https://eco.mtk.nao.ac.jp/koyomi/dni/${yyyy}/s${area_id}${mm}.html", null, true);
}

//

$timestamp = strtotime('+1 day');
$yyyy = date('Y', $timestamp);
$mm = date('m', $timestamp);

$res = $mu->get_contents('https://eco.mtk.nao.ac.jp/koyomi/dni/' . $yyyy . '/s' . $mu->get_env('AREA_ID') . $mm . '.html', null, true);

//

$timestamp = strtotime('+1 day');
$yyyy = date('Y', $timestamp);
$mm = date('m', $timestamp);

$res = $mu->get_contents('https://eco.mtk.nao.ac.jp/koyomi/dni/' . $yyyy . '/m' . $mu->get_env('AREA_ID') . $mm . '.html', null, true);

//

$options = [CURLOPT_HTTPHEADER => ['Accept: application/vnd.heroku+json; version=3',
                                   'Authorization: Bearer ' . getenv('HEROKU_API_KEY'),
                                   ]];
$res = $mu->get_contents('https://api.heroku.com/account', $options, true);

//

$res = $mu->get_contents('https://map.yahooapis.jp/geoapi/V1/reverseGeoCoder?output=json&appid='
                         . getenv('YAHOO_API_KEY')
                         . '&lon=' . $mu->get_env('LONGITUDE') . '&lat=' . $mu->get_env('LATITUDE'), null, true);

//

$url = 'https://github.com/apache/httpd/releases.atom?4nocache' . date('Ymd', strtotime('+9 hours'));
$res = $mu->get_contents($url, null, true);

//

$url = 'https://devcenter.heroku.com/articles/php-support?4nocache' . date('Ymd', strtotime('+9 hours'));
$res = $mu->get_contents($url, null, true);

//

$sql_delete = <<< __HEREDOC__
DELETE
  FROM t_webcache
 WHERE url_base64 = :b_url_base64
__HEREDOC__;

$pdo = $mu->get_pdo();
$statement = $pdo->prepare($sql_delete);

$sub_address = $mu->get_env('SUB_ADDRESS');
for ($i = 11; $i > -1; $i--) {
    $url = 'https://feed43.com/' . $sub_address . ($i * 5 + 11) . '-' . ($i * 5 + 15) . '.xml';
    $url_base64 = base64_encode($url);
    $rc = $statement->execute([':b_url_base64' => $url_base64]);
    error_log($pid . ' DELETE $rc : ' . $rc);
}

$pdo = null;

for ($i = 11; $i > -1; $i--) {
    $url = 'https://feed43.com/' . $sub_address . ($i * 5 + 11) . '-' . ($i * 5 + 15) . '.xml';
    $res = $mu->get_contents($url, null, true);
}

$time_finish = microtime(true);
$mu->post_blog_wordpress($requesturi . ' ' . substr(($time_finish - $time_start), 0, 6) . 's');
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');

exit();
