<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$suffix = '4nocache' . date('Ymd', strtotime('+9 hours'));

$pdo = $mu->get_pdo();
$rc = $pdo->exec('TRUNCATE t_webcache');
error_log($pid . ' TRUNCATE t_webcache $rc : ' . $rc);
$pdo = null;

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/daily040.php';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

$urls = [];
$urls_is_cache = [];

$url = 'https://spocale.com/team_and_players/12?' . $suffix;
$urls_is_cache[$url] = null;

$url = 'https://baseball.yahoo.co.jp/npb/standings/?' . $suffix;
$urls_is_cache[$url] = null;

$url = 'https://otn.fujitv.co.jp/json/basic_data/918200222.json';
$urls_is_cache[$url] = null;

$url = $mu->get_env('URL_SOCCER_TEAM_CSV_FILE');
$urls_is_cache[$url] = null;

$url = 'http://www.carp.co.jp/_calendar/list.html';
$urls_is_cache[$url] = null;

for ($yyyy = (int)date('Y'); $yyyy < (int)date('Y') + 2; $yyyy++) {
    $url = "https://e-moon.net/calendar_list/calendar_moon_${yyyy}/";
    $urls_is_cache[$url] = null;
}

$url = 'https://www.w-nexco.co.jp/traffic_info/construction/traffic.php?fdate='
    . date('Ymd', strtotime('+1 day'))
    . '&tdate='
    . date('Ymd', strtotime('+14 day'))
    . '&ak=1&ac=1&kisei%5B%5D=901&dirc%5B%5D=1&dirc%5B%5D=2&order=2&ronarrow=0'
    . '&road%5B%5D=1011&road%5B%5D=1912&road%5B%5D=1020&road%5B%5D=225A&road%5B%5D=1201'
    . '&road%5B%5D=1222&road%5B%5D=1231&road%5B%5D=234D&road%5B%5D=1232&road%5B%5D=1260';
$urls_is_cache[$url] = null;

$url = 'https://github.com/apache/httpd/releases.atom?' . $suffix;
$urls_is_cache[$url] = null;

$url = 'https://github.com/php/php-src/releases.atom?' . $suffix;
$urls_is_cache[$url] = null;

$url = 'https://github.com/curl/curl/releases.atom?' . $suffix;
$urls_is_cache[$url] = null;

$url = 'https://devcenter.heroku.com/articles/php-support?' . $suffix;
$urls_is_cache[$url] = null;

$url = 'https://map.yahooapis.jp/geoapi/V1/reverseGeoCoder?output=json&appid='
    . $mu->get_env('YAHOO_API_KEY', true)
    . '&lon=' . $mu->get_env('LONGITUDE') . '&lat=' . $mu->get_env('LATITUDE');
$urls_is_cache[$url] = null;

$start_yyyy = date('Y');
$start_m = date('n');
$finish_yyyy = date('Y', strtotime('+3 month'));
$finish_m = date('n', strtotime('+3 month'));

$url = 'http://calendar-service.net/cal?start_year=' . $start_yyyy
    . '&start_mon=' . $start_m . '&end_year=' . $finish_yyyy . '&end_mon=' . $finish_m
    . '&year_style=normal&month_style=numeric&wday_style=ja_full&format=csv&holiday_only=1&zero_padding=1';
$urls_is_cache[$url] = null;

for ($j = 0; $j < 4; $j++) {
    $yyyy = date('Y', strtotime('+' . $j . ' years'));
    $url = 'http://calendar-service.net/cal?start_year=' . $yyyy
        . '&start_mon=1&end_year=' . $yyyy . '&end_mon=12'
        . '&year_style=normal&month_style=numeric&wday_style=ja_full&format=csv&holiday_only=1&zero_padding=1';
    $urls_is_cache[$url] = null;
}

$timestamp = strtotime('+1 day');
$yyyy = date('Y', $timestamp);
$mm = date('m', $timestamp);
$url = "https://eco.mtk.nao.ac.jp/koyomi/dni/${yyyy}/m" . $mu->get_env('AREA_ID') . "${mm}.html";
$urls_is_cache[$url] = null;

$area_id = $mu->get_env('AREA_ID');
for ($i = 0; $i < 4; $i++) {
    $timestamp = strtotime(date('Y-m-01') . " +${i} month");
    $yyyy = date('Y', $timestamp);
    $mm = date('m', $timestamp);
    $url = "https://eco.mtk.nao.ac.jp/koyomi/dni/${yyyy}/s${area_id}${mm}.html";
    $urls_is_cache[$url] = null;
}

$yyyy = date('Y');
$ymd = date('Ymd', strtotime('+9 hours'));
for ($i = 3; $i < 10; $i++) {
    $url = "https://elevensports.jp/schedule/farm/${yyyy}/" . str_pad($i, 2, '0', STR_PAD_LEFT) . "?${suffix}";
    $urls_is_cache[$url] = null;
}

$options = [
    CURLOPT_ENCODING => 'gzip, deflate, br',
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
        'Cache-Control: no-cache',
        'Connection: keep-alive',
        'DNT: 1',
        'Upgrade-Insecure-Requests: 1',
        ],
    ];

for ($i = 0; $i < 8; $i++) {
    $url = $mu->get_env('URL_BUS_0' . ($i + 1)) . '&' . $suffix;
    $urls_is_cache[$url] = $options;
}

// multi
$multi_options = [
    CURLMOPT_PIPELINING => 3,
    CURLMOPT_MAX_HOST_CONNECTIONS => 1,
];
$list_contents = $mu->get_contents_multi($urls, $urls_is_cache, $multi_options);
error_log($pid . ' memory_get_usage : ' . number_format(memory_get_usage()) . 'byte');
if (count($list_contents) !== (count($urls) + count($urls_is_cache))) {
    $list_contents = [];
    for ($i = 0; $i < 3; $i++) {
        $list_contents = $mu->get_contents_multi(null, $urls_is_cache, $multi_options);
        if (count($list_contents) === count($urls_is_cache)) {
            break;
        }
        $list_contents = null;
    }
}
$list_contents = null;

for ($yyyy = (int)date('Y'); $yyyy < (int)date('Y') + 2; $yyyy++) {
    $post_data = ['from_year' => $yyyy];
    $options = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
        ];
    $res = $mu->get_contents('http://www.calc-site.com/calendars/solar_year', $options, true);
}

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/get_youtube_play_count.php';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

$time_finish = microtime(true);
$mu->post_blog_wordpress_async("${requesturi} [" . substr(($time_finish - $time_start), 0, 6) . 's]');

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
