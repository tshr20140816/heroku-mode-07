<?php

/*

WEATHER : 気象庁10日間天気予報
ADDITIONAL : タスクはあるがその日にラベルが無い
HOLIDAY : 祝祭日
HOURLY : アメダス、直近1時間雨量予想、quota、日付設定漏れ

WEATHER2 : 長期予報、日の出、日の入り、月の出、月の入り
SOCCER
CULTURECENTER
HIGHWAY
CARP
BUS
AMEFOOT
TV
*/
include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

const LIST_YOBI = array('日', '月', '火', '水', '木', '金', '土');
const LIST_WAFU_GETSUMEI = array('', '睦月', '如月', '弥生', '卯月', '皐月', '水無月', '文月', '葉月', '長月', '神無月', '霜月', '師走');
const LIST_12SHI = array('子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥');

$rc = apcu_clear_cache();

$mu = new MyUtils();

// Access Token
$access_token = $mu->get_access_token();

// Get Contexts
$list_context_id = $mu->get_contexts();

// Get Folders
$folder_id_label = $mu->get_folder_id('LABEL');

$file_name_blog = tempnam("/tmp", 'blog_' .  md5(microtime(true)));
@unlink($file_name_blog);

// holiday 3年後の12月まで
$list_holiday2 = get_holiday2($mu);

// holiday 今月含み4ヶ月分
$list_holiday = get_holiday3($mu);

// 24sekki 今年と来年分
$list_24sekki = get_24sekki2($mu);

// Sun 今月含み4ヶ月分
$list_sunrise_sunset = get_sun($mu);

// Weather Information 今日の10日後から70日分

$list_base = [];
$sub_address = $mu->get_env('SUB_ADDRESS');
for ($i = 0; $i < 12; $i++) {
    $url = 'https://feed43.com/' . $sub_address . ($i * 5 + 11) . '-' . ($i * 5 + 15) . '.xml';
    $res = $mu->get_contents($url, null, true);
    foreach (explode("\n", $res) as $one_line) {
        if (strpos($one_line, '<title>_') !== false) {
            $tmp = explode('_', $one_line);
            $tmp1 = explode(' ', $tmp[2]);
            $tmp2 = explode('/', $tmp1[1]);
            // 10月から4月までの閾値は30、その他は38
            if ((int)$tmp2[0] > ((int)substr($tmp1[0], 0, 1) < 5 ? 30 : 38)) {
                // 華氏 → 摂氏
                $tmp2[0] = (int)(((int)$tmp2[0] - 32) * 5 / 9);
                $tmp2[1] = (int)(((int)$tmp2[1] - 32) * 5 / 9);
                $tmp[2] = $tmp1[0] . ' ' . $tmp2[0] . '/' . $tmp2[1];
            }
            $list_base[$tmp[1]] = $tmp[2];
        }
    }
}
error_log($pid . ' $list_base : ' . print_r($list_base, true));

// Get Tasks

$url = 'https://api.toodledo.com/3/tasks/get.php?comp=0&fields=tag,folder,duedate&access_token=' . $access_token;
$res = $mu->get_contents($url);

$tasks = json_decode($res, true);

// 30日後から70日後までの間の予定のある日を取得

$list_schedule_exists_day = [];
for ($i = 0; $i < count($tasks); $i++) {
    if (array_key_exists('duedate', $tasks[$i]) && array_key_exists('folder', $tasks[$i])) {
        if ($tasks[$i]['folder'] != $folder_id_label) {
            $ymd = date('Ymd', $tasks[$i]['duedate']);
            if (date('Ymd', strtotime('+29 days')) < $ymd && $ymd < date('Ymd', strtotime('+71 days'))) {
                $list_schedule_exists_day[] = $ymd;
            }
        }
    }
}
$list_schedule_exists_day = array_unique($list_schedule_exists_day);
$rc = sort($list_schedule_exists_day);
error_log($pid . ' $list_schedule_exists_day : ' . print_r($list_schedule_exists_day, true));

$list_add_task = [];

// To Small Size
$update_marker = $mu->to_small_size(' _' . date('ymd') . '_');
for ($i = 0; $i < 70; $i++) {
    $timestamp = strtotime(date('Y-m-d') . ' +' . ($i + 10) . ' days');
    $dt = date('n/j', $timestamp);
    if (array_key_exists($dt, $list_base)) {
        $tmp = $list_base[$dt];
    } else {
        $tmp = '----';
    }
    // 30日後以降は土日月及び祝祭日、24節気のみ
    if ($i > 20 && (date('w', $timestamp) + 1) % 7 > 2
        && !array_key_exists($timestamp, $list_holiday)
        && !array_key_exists($timestamp, $list_24sekki)
        && array_search(date('Ymd', $timestamp), $list_schedule_exists_day) == false) {
        continue;
    }
    $tmp = '### ' . LIST_YOBI[date('w', $timestamp)] . '曜日 ' . date('m/d', $timestamp) . ' ### ' . $tmp . $update_marker;
    if (array_key_exists($timestamp, $list_holiday)) {
        $tmp = str_replace(' ###', ' ★' . $list_holiday[$timestamp] . '★ ###', $tmp);
    }
    if (array_key_exists($timestamp, $list_24sekki)) {
        $tmp .= ' ' . $list_24sekki[$timestamp];
    }
    if (array_key_exists($timestamp, $list_sunrise_sunset)) {
        $tmp .= ' ' . $list_sunrise_sunset[$timestamp];
    }
    $list_add_task[date('Ymd', $timestamp)] = '{"title":"' . $tmp
        . '","duedate":"' . $timestamp
        . '","tag":"WEATHER2","context":' . $list_context_id[date('w', $timestamp)]
        .   ',"folder":' . $folder_id_label . '}';
}
$count_task = count($list_add_task);
file_put_contents($file_name_blog, "Weather Task Add : ${count_task}\n", FILE_APPEND);
error_log($pid . ' Tasks Weather : ' . print_r($list_add_task, true));

if (count($list_add_task) == 0) {
    error_log($pid . ' WEATHER DATA NONE');
    exit();
}

$list_delete_task = [];
foreach ($tasks as $task) {
    if (array_key_exists('id', $task) && array_key_exists('tag', $task)) {
        if ($task['tag'] == 'WEATHER2'
            || $task['tag'] == 'SOCCER'
            || $task['tag'] == 'CULTURECENTER'
            || $task['tag'] == 'CARP'
            || $task['tag'] == 'HIGHWAY'
            || $task['tag'] == 'BUS'
            || $task['tag'] == 'F1'
            || $task['tag'] == 'TV'
            || $task['tag'] == 'AMEFOOT') {
            $hash = date('Ymd', $task['duedate']) . hash('sha512', $task['title']);
            $list_delete_task[$hash] = $task['id'];
        } elseif ($task['tag'] == 'HOLIDAY' || $task['tag'] == 'ADDITIONAL') {
            if (array_key_exists(date('Ymd', $task['duedate']), $list_add_task)) {
                $hash = date('Ymd', $task['duedate']) . hash('sha512', $task['title']);
                $list_delete_task[$hash] = $task['id'];
            }
        }
    }
}
error_log($pid . ' $list_delete_task : ' . print_r($list_delete_task, true));

// Sun Tasks 翌日分
$list_add_task = array_merge($list_add_task, get_task_sun($mu, $file_name_blog));

// Moon Tasks 翌日分
$list_add_task = array_merge($list_add_task, get_task_moon($mu, $file_name_blog));

// 追加、削除双方にある重複分は両方から削除

$list_get_task = [get_task_highway($mu, $file_name_blog),
                  get_task_soccer($mu, $file_name_blog),
                  get_task_culturecenter($mu, $file_name_blog),
                  get_task_full_moon($mu, $file_name_blog),
                  get_task_carp($mu, $file_name_blog),
                  get_task_farm($mu, $file_name_blog),
                  get_task_bus($mu, $file_name_blog),
                  get_task_f1($mu, $file_name_blog),
                  get_task_amefootlive($mu, $file_name_blog),
                 ];
foreach ($list_get_task as $list_add_task_tmp) {
    $list_duplicate_task_keys = array_intersect(array_keys($list_add_task_tmp), array_keys($list_delete_task));
    foreach ($list_duplicate_task_keys as $key) {
        unset($list_add_task_tmp[$key]);
        unset($list_delete_task[$key]);
    }
    $list_add_task = array_merge($list_add_task, $list_add_task_tmp);
}

// 祝祭日追加 (folder が LABEL で同じ title が無ければ追加)

$list_label_title = [];
for ($i = 0; $i < count($tasks); $i++) {
    if (array_key_exists('title', $tasks[$i]) && array_key_exists('folder', $tasks[$i])) {
        if ($tasks[$i]['folder'] == $folder_id_label) {
            $list_label_title[] = $tasks[$i]['title'];
        }
    }
}

foreach ($list_holiday2 as $key => $value) {
    if (array_search($key, $list_label_title) == false) {
        $list_add_task[] = '{"title":"' . $key
          . '","duedate":"' . $value
          . '","tag":"HOLIDAY","context":' . $list_context_id[date('w', $value)]
          . ',"folder":' . $folder_id_label . '}';
    }
}

// 和風月名追加 (folder が LABEL で同じ title が無ければ追加)

for ($y = date('Y'); $y < date('Y') + 4; $y++) {
    for ($m = 1; $m < 13; $m++) {
        $timestamp = mktime(0, 0, 0, $m, 1, $y);
        if ($timestamp < strtotime('+1 month')) {
            continue;
        }
        if ($m === 1) {
          $title = '## ' . LIST_WAFU_GETSUMEI[$m] . date(' F ', $timestamp) . LIST_12SHI[($y - 2008) % 12] . ' ## ' . $mu->to_small_size($y);
        } else {
          $title = '## ' . LIST_WAFU_GETSUMEI[$m] . date(' F', $timestamp) . ' ## ' . $mu->to_small_size($y);
        }
        if (array_search($title, $list_label_title) == false) {
            $list_add_task[] = '{"title":"' . $title
              . '","duedate":' . $timestamp
              . ',"context":' . $list_context_id[date('w', $timestamp)]
              . ',"folder":' . $folder_id_label . '}';
            error_log($pid . ' ' . date('Y/m/d', $timestamp) . ' ' . $title);
        }
    }
}

error_log($pid . ' $list_add_task : ' . print_r($list_add_task, true));

// Add Tasks
$count_add_task = count($list_add_task);
$rc = $mu->add_tasks($list_add_task);

// Delete Tasks
$count_delete_task = count($list_delete_task);
$mu->delete_tasks($list_delete_task);

$time_finish = microtime(true);
$mu->post_blog_wordpress("${requesturi} add : ${count_add_task} / delete : ${count_delete_task} ["
                         . substr(($time_finish - $time_start), 0, 6) . 's]',
                        file_get_contents($file_name_blog));
@unlink($file_name_blog);
error_log($pid . ' Web Access Count : ' . $mu->_count_web_access);
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');

exit();

function get_holiday2($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $list_holiday2 = [];
    for ($j = 0; $j < 4; $j++) {
        $yyyy = date('Y', strtotime("+${j} years"));

        $url = 'http://calendar-service.net/cal?start_year=' . $yyyy
            . '&start_mon=1&end_year=' . $yyyy . '&end_mon=12'
            . '&year_style=normal&month_style=numeric&wday_style=ja_full&format=csv&holiday_only=1&zero_padding=1';

        $res = $mu_->get_contents($url, null, true);
        $res = mb_convert_encoding($res, 'UTF-8', 'EUC-JP');

        $tmp = explode("\n", $res);
        array_shift($tmp); // ヘッダ行削除
        array_pop($tmp); // フッタ行(空行)削除

        for ($i = 0; $i < count($tmp); $i++) {
            $tmp1 = explode(',', $tmp[$i]);
            $timestamp = mktime(0, 0, 0, $tmp1[1], $tmp1[2], $tmp1[0]);
            if (date('Ymd', $timestamp) < date('Ymd', strtotime('+100 days'))) {
                continue;
            }

            $yyyy = $mu_->to_small_size($tmp1[0]);
            $list_holiday2['### ' . $tmp1[5] . ' ' . $tmp1[1] . '/' . $tmp1[2] . ' ★' . $tmp1[7] . '★ ### ' . $yyyy] = $timestamp;
        }
    }
    error_log($log_prefix . '$list_holiday2 : ' . print_r($list_holiday2, true));

    return $list_holiday2;
}

function get_holiday3($mu_)
{
    // holiday 今月含み4ヶ月分

    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $start_yyyy = date('Y');
    $start_m = date('n');
    $finish_yyyy = date('Y', strtotime('+3 month'));
    $finish_m = date('n', strtotime('+3 month'));

    $url = 'http://calendar-service.net/cal?start_year=' . $start_yyyy
        . '&start_mon=' . $start_m . '&end_year=' . $finish_yyyy . '&end_mon=' . $finish_m
        . '&year_style=normal&month_style=numeric&wday_style=ja_full&format=csv&holiday_only=1&zero_padding=1';

    $res = $mu_->get_contents($url, null, true);
    $res = mb_convert_encoding($res, 'UTF-8', 'EUC-JP');

    $tmp = explode("\n", $res);
    array_shift($tmp); // ヘッダ行削除
    array_pop($tmp); // フッタ行(空行)削除

    $list_holiday = [];
    for ($i = 0; $i < count($tmp); $i++) {
        $tmp1 = explode(',', $tmp[$i]);
        $timestamp = mktime(0, 0, 0, $tmp1[1], $tmp1[2], $tmp1[0]);
        $list_holiday[$timestamp] = $tmp1[7];
    }
    error_log($log_prefix . '$list_holiday : ' . print_r($list_holiday, true));

    return $list_holiday;
}

function get_24sekki2($mu_)
{
    // 24sekki 今年と来年分

    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $list_24sekki = [];

    $yyyy = (int)date('Y');
    for ($j = 0; $j < 2; $j++) {
        $post_data = ['from_year' => $yyyy];

        $res = $mu_->get_contents(
            'http://www.calc-site.com/calendars/solar_year',
            [CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data),
            ],
            true
        );

        $tmp = explode('<th>二十四節気</th>', $res);
        $tmp = explode('</table>', $tmp[1]);

        $tmp = explode('<tr>', $tmp[0]);
        array_shift($tmp);

        for ($i = 0; $i < count($tmp); $i++) {
            $rc = preg_match('/<td>(.+?)<.+?<.+?>(.+?)</', $tmp[$i], $matches);
            $tmp1 = $matches[2];
            $tmp1 = str_replace('月', '-', $tmp1);
            $tmp1 = str_replace('日', '', $tmp1);
            $tmp1 = $yyyy . '-' . $tmp1;
            $list_24sekki[strtotime($tmp1)] = '【' . $matches[1] . '】';
        }
        $yyyy++;
    }
    error_log($log_prefix . '$list_24sekki : ' . print_r($list_24sekki, true));

    return $list_24sekki;
}

function get_sun($mu_)
{
    // Sun 今月含み4ヶ月分

    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $list_sunrise_sunset = [];

    $area_id = $mu_->get_env('AREA_ID');
    for ($j = 0; $j < 4; $j++) {
        $timestamp = strtotime(date('Y-m-01') . " +${j} month");
        $yyyy = date('Y', $timestamp);
        $mm = date('m', $timestamp);
        $res = $mu_->get_contents('https://eco.mtk.nao.ac.jp/koyomi/dni/' . $yyyy . '/s' . $area_id . $mm . '.html', null, true);

        $tmp = explode('<table ', $res);
        $tmp = explode('</table>', $tmp[1]);
        $tmp = explode('</tr>', $tmp[0]);
        array_shift($tmp);
        array_pop($tmp);

        $dt = date('Y-m-01', $timestamp);

        for ($i = 0; $i < count($tmp); $i++) {
            $timestamp = strtotime("${dt} +${i} day"); // UTC
            $rc = preg_match('/.+?<\/td>.*?<td>(.+?)<\/td>.*?<td>.+?<\/td>.*?<td>.+?<\/td>.*?<td>.+?<\/td>.*?<td>(.+?)</', $tmp[$i], $matches);
            $list_sunrise_sunset[$timestamp] = '↗' . trim($matches[1]) . ' ↘' . trim($matches[2]);
        }
    }
    // To Small Size
    $list_sunrise_sunset = $mu_->to_small_size($list_sunrise_sunset);

    error_log($log_prefix . '$list_sunrise_sunset : ' . print_r($list_sunrise_sunset, true));
    return $list_sunrise_sunset;
}

function get_task_tv($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_private = $mu_->get_folder_id('PRIVATE');
    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $list_add_task = [];
    $add_task_template = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__","tag":"TV","folder":"'
      . $folder_id_private . '"}';

    $url = 'https://spocale.com/team_and_players/12';
    $res = $mu_->get_contents($url, null, true);
    $rc = preg_match_all('/<a href="\/games\/(.+?)">/', $res, $matches);

    $url = 'https://spocale.com/games/' . $matches[1][0];
    $res = $mu_->get_contents($url, null, true);
    $rc = preg_match('/<div class="time-wrap">.*?<.+?>(.+?)<.+?>.*?<.+?>(.+?)</s', $res, $match);
    
    $dt = strtotime(str_replace('.', '/', $match[1]) . ' ' . $match[2]);
    error_log($log_prefix . date('Y/m/d H:i', $dt));
    
    if (date('Ymd', $dt) != date('Ymd', strtotime('+9 hours'))) {
        return [];
    }
    
    $rc = preg_match('/.+<div class="table-header">.*?<h4><i class="i tv"><\/i>テレビで視聴する<\/h4>(.+?)<div class="table-header">/s', $res, $match);
    
    $tv = '';
    foreach (explode('<div class="table-list">', $match[1]) as $item) {
        // error_log(trim(preg_replace("/(\n| )+/s", ' ', strip_tags($item))));
        $tmp = trim(preg_replace("/(\n| )+/s", ' ', strip_tags($item)));
        $tmp = str_replace('~', '', $tmp);
        $tmp = trim(str_replace('LIVE', '', $tmp));
        if (strlen($tmp) > 0) { 
            // error_log($tmp);
            $tv .= ' ' . $tmp;
        }
    }
    $tmp = str_replace('__TITLE__', $tv, $add_task_template);
    $tmp = str_replace('__DUEDATE__', strtotime('+9 hours'), $tmp);
    $list_add_task[] = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);

    $count_task = count($list_add_task);
    file_put_contents($file_name_blog_, "tv Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks tv : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_amefootlive($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_private = $mu_->get_folder_id('PRIVATE');
    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $list_add_task = [];
    $add_task_template = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__","tag":"AMEFOOT","folder":"'
      . $folder_id_private . '"}';

    $url = 'https://amefootlive.jp/live';
    $res = $mu_->get_contents($url, null, true);

    $pattern = '/<header class="entry-header">.+?<div .+?>.*?(\d+).*?(\d+).*?(\d+).*?(\d+):(\d+)<.+?<h2 .+?><a .+?>(.+?)</s';

    $rc = preg_match_all($pattern, explode('<h1>ライブ予定</h1>', $res)[1], $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        array_shift($match);

        $timestamp = strtotime($match[0] . '-' . $match[1] . '-' . $match[2]);
        if ($timestamp < time()) {
            continue;
        }
        $title = $match[1] . '/' . $match[2] . ' ' . $match[3] . ':' . $match[4] . ' amefootlive ' . $match[5];

        $tmp = str_replace('__TITLE__', $title, $add_task_template);
        $tmp = str_replace('__DUEDATE__', $timestamp, $tmp);

        $list_add_task[] = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);
    }

    $count_task = count($list_add_task);
    file_put_contents($file_name_blog_, "amefootlive Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks amefootlive : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_f1($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_private = $mu_->get_folder_id('PRIVATE');
    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $list_add_task = [];
    $add_task_template = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__","tag":"F1","folder":"'
      . $folder_id_private . '"}';

    $url = 'https://otn.fujitv.co.jp/json/basic_data/918200222.json';
    $res = $mu_->get_contents($url, null, true);

    foreach (json_decode($res)->schedule as $item) {
        if ($item->liveFlag == '0') {
            continue;
        }

        $timestamp = strtotime(substr($item->strDateTime, 0, 10));
        if ($timestamp < time()) {
            continue;
        }

        $title = str_replace('-', '/', substr($item->strDateTime, 5, 5))  . ' ';
        $title .= substr($item->strDateTime, 11, 5) . ' ' .  $item->subTitle . ' ⠴⬬⠶⠷⬬⠝ ⚑⚐⚑⚐';

        $tmp = str_replace('__TITLE__', $title, $add_task_template);
        $tmp = str_replace('__DUEDATE__', $timestamp, $tmp);
        $list_add_task[] = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);
    }

    $count_task = count($list_add_task);
    file_put_contents($file_name_blog_, "F1 Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks F1 : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_bus($mu_, $file_name_blog_) {
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $folder_id_bus = $mu_->get_folder_id('BUS');
    $list_context_id = $mu_->get_contexts();
    $list_add_task = [];
    $timestamp = mktime(0, 0, 0, 1, 1, 2019);

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
        $urls[] = $mu_->get_env('URL_BUS_0' . ($i + 1)) . '&4nocache' . date('Ymd', strtotime('+9 hours'));
    }

    $pattern1 = '/<div id="area".*?<p class="mark">(.*?)<.+?<span class="bstop_name" itemprop="name">(.*?)<.+? itemprop="alternateName">(.*?)</s';
    $pattern2 = '/<p class="time" itemprop="departureTime">\s+(.+?)\s.+?<span class="route">(.*?)<.+?itemprop="name">(.*?)<.+?<\/li>/s';
    foreach ($urls as $url) {
        $res = $mu_->get_contents($url, $options, true);

        $rc = preg_match($pattern1, $res, $match);

        $bus_stop_from = $match[2] . ' ' . $match[3] . ' ' . $match[1];
        $bus_stop_from = str_replace('  ', ' ', $bus_stop_from);
        error_log($log_prefix . $bus_stop_from);

        $rc = preg_match_all($pattern2, $res, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $title = str_replace('()', '', $bus_stop_from . ' [' . $match[1] . '] ' . $match[3] . '(' . $match[2] . ')');
            $hash = date('Ymd', $timestamp) . hash('sha512', $title);
            $list_add_task[$hash] = '{"title":"' . $title
                . '","duedate":"' . $timestamp
                . '","context":"' . $list_context_id[date('w', $timestamp)]
                . '","tag":"BUS","folder":"' . $folder_id_bus . '"}';
        }
    }
    $count_task = count($list_add_task);
    // $mu_->post_blog_fc2("Bus Task Add : ${count_task}");
    file_put_contents($file_name_blog_, "Bus Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks Bus : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_carp($mu_, $file_name_blog_) {
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_private = $mu_->get_folder_id('PRIVATE');

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $res = $mu_->get_contents('http://www.carp.co.jp/_calendar/list.html', null, true);
    $pattern = '/<tr.*?><td.*?>(.+?);(.+?)<.+?><.+?>.*?<.+?><.+?>(.+?)<\/td><.+?>(.+?)</s';
    $rc = preg_match_all($pattern, $res, $matches,  PREG_SET_ORDER);

    $list_add_task = [];

    foreach($matches as $item) {
        $timestamp = strtotime('2019/' . mb_substr($item[1], 0, 2) . '/' . mb_substr($item[1], 3, 2));
        if ($timestamp < time()) {
            continue;
        }
        if (mb_substr($item[2], 0, 1) == '(') {
            $item[2] = trim($item[2], '()') . ' 予備日';
        }
        $title = '### ⚾' . ' ' . $item[2] . ' ' . trim(strip_tags($item[3])) . ' ' . $item[4] . ' ###';
        $hash = date('Ymd', $timestamp) . hash('sha512', $title);

        $list_add_task[$hash] = '{"title":"' . $title
          . '","duedate":"' . $timestamp
          . '","context":"' . $list_context_id[date('w', $timestamp)]
          . '","tag":"CARP","folder":"' . $folder_id_private . '"}';
    }
    $count_task = count($list_add_task);
    // $mu_->post_blog_fc2("Carp Task Add : ${count_task}");
    file_put_contents($file_name_blog_, "Carp Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks Carp : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_farm($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $folder_id_private = $mu_->get_folder_id('PRIVATE');
    $list_context_id = $mu_->get_contexts();
    $list_add_task = [];

    $yyyy = date('Y');
    $ymd = date('Ymd', strtotime('+9 hours'));
    for ($i = 3; $i < 10; $i++) {
        $url = "https://elevensports.jp/schedule/farm/${yyyy}/" . str_pad($i, 2, '0', STR_PAD_LEFT) . "?4nocache${ymd}";
        $res = $mu_->get_contents($url, null, true);

        $rc = preg_match_all('/<tr>(.+?)<\/tr>/s', $res, $matches);

        foreach ($matches[1] as $item) {
            if (mb_strpos($item, '広島') === false) {
                continue;
            }
            $rc = preg_match('/<td.+?>(\d+)\/(\d+).+?<td>(.+?)<.+?">(.+?)<.+?">(.+?)<.+?<td>(.+?)</s', $item, $match);

            $timestamp = strtotime($yyyy . '/' . $match[1] . '/' . $match[2]);
            if ($timestamp < time()) {
                continue;
            }

            $title = $match[1] . '/' . $match[2] . ' ' . $match[3] . ' ファーム中継 ' . $match[4] . ' v ' . $match[5] . ' ' . $match[6];
            $hash = date('Ymd', $timestamp) . hash('sha512', $title);

            $list_add_task[$hash] = '{"title":"' . $title
                . '","duedate":"' . $timestamp
                . '","context":"' . $list_context_id[date('w', $timestamp)]
                . '","tag":"CARP","folder":"' . $folder_id_private . '"}';
        }
    }
    $count_task = count($list_add_task);

    file_put_contents($file_name_blog_, "Farm Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks Farm : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_full_moon($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_label = $mu_->get_folder_id('LABEL');
    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $list_add_task = [];
    $add_task_template = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__","tag":"WEATHER2","folder":"'
      . $folder_id_label . '"}';

    $y = (int)date('Y') - 1;
    for ($i = 0; $i < 3; $i++) {
        $y++;
        $url = "https://e-moon.net/calendar_list/calendar_moon_${y}/";
        if ($i > 0) {
            $res = $mu_->get_contents($url, [CURLOPT_NOBODY => true]);
            if ($res != '') {
                continue;
            }
        }
        $res = $mu_->get_contents($url, null, true);

        $pattern = '/<td class="embed_link_to_star_mall_fullmoon">(\d+).+?(\d+).+?(\d+).+?(\d+)(.*?)</s';
        $rc = preg_match_all($pattern, $res, $matches,  PREG_SET_ORDER);

        foreach ($matches as $match) {
            for ($j = 1; $j < 5; $j++) {
                $match[$j] = str_pad($match[$j], 2, '0', STR_PAD_LEFT);
            }
            $match[5] = trim($match[5]);
            if ($match[5] == '') {
                $match[5] = '満月';
            }
            $title = $match[1] . '/' . $match[2] . ' ' . $match[3] . ':' . $match[4] . ' ' .  $match[5] . ' ★';
            $timestamp = mktime(0, 0, 0, $match[1], $match[2], $y);
            if ($timestamp < strtotime('+3 days')) {
                continue;
            }
            $hash = date('Ymd', $timestamp) . hash('sha512', $title);

            $tmp = str_replace('__TITLE__', $title, $add_task_template);
            $tmp = str_replace('__DUEDATE__', $timestamp, $tmp);
            $tmp = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);
            $list_add_task[$hash] = $tmp;
        }
    }
    $count_task = count($list_add_task);
    // $mu_->post_blog_fc2("FULL MOON Task Add : ${count_task}");
    file_put_contents($file_name_blog_, "Full Moon Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks Full Moon : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_soccer($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_private = $mu_->get_folder_id('PRIVATE');

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $res = $mu_->get_contents($mu_->get_env('URL_SOCCER_TEAM_CSV_FILE'), null, true);
    $res = mb_convert_encoding($res, 'UTF-8', 'SJIS');

    $list_tmp = explode("\n", $res);

    $list_add_task = [];
    $add_task_template = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__","tag":"SOCCER","folder":"'
      . $folder_id_private . '"}';
    for ($i = 1; $i < count($list_tmp) - 1; $i++) {
        $tmp = explode(',', $list_tmp[$i]);
        $timestamp = strtotime(trim($tmp[1], '"'));
        if (date('Ymd') >= date('Ymd', $timestamp)) {
            continue;
        }

        $tmp1 = trim($tmp[2], '"');
        $rc = preg_match('/\d+:\d+:\d\d/', $tmp1);
        if ($rc == 1) {
            $tmp1 = substr($tmp1, 0, strlen($tmp1) - 3);
        }
        $title = substr(trim($tmp[1], '"'), 5) . ' ' . $tmp1 . ' ⚽ ' . trim($tmp[0], '"') . ' ' . trim($tmp[6], '"');

        $tmp1 = str_replace('__TITLE__', $title, $add_task_template);
        $tmp1 = str_replace('__DUEDATE__', $timestamp, $tmp1);
        $tmp1 = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp1);
        $hash = date('Ymd', $timestamp) . hash('sha512', $title);
        $list_add_task[$hash] = $tmp1;
    }
    $count_task = count($list_add_task);
    // $mu_->post_blog_fc2("Soccer Task Add : ${count_task}");
    file_put_contents($file_name_blog_, "Soccer Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'TASKS SOCCER : ' . print_r($list_add_task, true));

    return $list_add_task;
}

function get_task_culturecenter($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_private = $mu_->get_folder_id('PRIVATE');

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $y = date('Y');
    $m = date('n');

    $list_add_task = [];
    for ($j = 0; $j < 2; $j++) {
        $url = 'http://www.cf.city.hiroshima.jp/saeki-cs/sche6_park/sche6.cgi?year=' . $y . '&mon=' . $m;

        $res = $mu_->get_contents($url);
        $res = mb_convert_encoding($res, 'UTF-8', 'SJIS');

        $tmp = explode('<col span=1 align=right>', $res);
        $tmp = explode('</table>', $tmp[1]);

        $rc = preg_match_all('/<tr .+?<b>(.+?)<.*?<td(.*?)<\/td><\/tr>/s', $tmp[0], $matches, PREG_SET_ORDER);

        for ($i = 0; $i < count($matches); $i++) {
            $timestamp = mktime(0, 0, 0, $m, $matches[$i][1], $y);
            if (date('Ymd') > date('Ymd', $timestamp)) {
                continue;
            }
            $tmp = $matches[$i][2];
            $tmp = preg_replace('/<font .+?>.+?>/', '', $tmp);
            $tmp = preg_replace('/bgcolor.+?>/', '', $tmp);
            $tmp = trim($tmp, " \t\n\r\0\t>");
            $tmp = str_replace('　', '', $tmp);
            $tmp = trim(str_replace('<br>', ' ', $tmp));
            if (strlen($tmp) == 0) {
                continue;
            }
            $title = date('m/d', $timestamp) . ' 文セ ★ ' . $tmp;
            $hash = date('Ymd', $timestamp) . hash('sha512', $title);
            $list_add_task[$hash] = '{"title":"' . $title
              . '","duedate":"' . $timestamp
              . '","context":"' . $list_context_id[date('w', $timestamp)]
              . '","tag":"CULTURECENTER","folder":"' . $folder_id_private . '"}';
        }
        if ($m == 12) {
            $y++;
            $m = 1;
        } else {
            $m++;
        }
    }
    $count_task = count($list_add_task);
    // $mu_->post_blog_fc2("Culture Center Task Add : ${count_task}");
    file_put_contents($file_name_blog_, "Culture Center Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'TASKS CULTURECENTER : ' . print_r($list_add_task, true));

    return $list_add_task;
}

function get_task_highway($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_private = $mu_->get_folder_id('PRIVATE');

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    /*
    $url = 'https://www.w-nexco.co.jp/traffic_info/construction/traffic.php?fdate='
      . date('Ymd', strtotime('+1 day'))
      . '&tdate='
      . date('Ymd', strtotime('+14 day'))
      . '&ak=1&ac=1&kisei%5B%5D=901&dirc%5B%5D=1&dirc%5B%5D=2&order=2&ronarrow=1'
      . '&road%5B%5D=1011&road%5B%5D=1912&road%5B%5D=1020&road%5B%5D=225A&road%5B%5D=1201'
      . '&road%5B%5D=1222&road%5B%5D=1231&road%5B%5D=234D&road%5B%5D=1232&road%5B%5D=1260';
    */
    $url = 'https://www.w-nexco.co.jp/traffic_info/construction/traffic.php?fdate='
      . date('Ymd', strtotime('+1 day'))
      . '&tdate='
      . date('Ymd', strtotime('+14 day'))
      . '&ak=1&ac=1&kisei%5B%5D=901&dirc%5B%5D=1&dirc%5B%5D=2&order=2&ronarrow=0'
      . '&road%5B%5D=1011&road%5B%5D=1912&road%5B%5D=1020&road%5B%5D=225A&road%5B%5D=1201'
      . '&road%5B%5D=1222&road%5B%5D=1231&road%5B%5D=234D&road%5B%5D=1232&road%5B%5D=1260';

    $res = $mu_->get_contents($url, null, true);

    $tmp = explode('<!--工事日程順-->', $res);
    $tmp = explode('<table cellspacing="0" summary="" class="lb05">', $tmp[0]);
    $tmp = explode('<th>備考</th>', $tmp[1]);

    $rc = preg_match_all('/<tr.*?>' . str_repeat('.*?<td.*?>(.+?)<\/td>', 5) . '.+?<\/tr>/s', $tmp[1], $matches, PREG_SET_ORDER);

    $list_add_task = [];
    $add_task_template = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__","tag":"HIGHWAY","folder":"'
      . $folder_id_private . '"}';
    for ($i = 0; $i < count($matches); $i++) {
        $yyyy = (int)date('Y');
        $tmp = explode('日', $matches[$i][4]);
        $tmp = explode('月', $tmp[0]);
        if (date('m') == '12' && (int)$tmp[0] == 1) {
            $yyyy++;
        }
        $timestamp = mktime(0, 0, 0, $tmp[0], $tmp[1], $yyyy);

        $tmp = $matches[$i];
        $title = date('m/d', $timestamp) . ' ★ ' . $tmp[4] . ' ' . $tmp[2] . ' ' . $tmp[3] . ' ' . $tmp[5] . ' ' . $tmp[1];
        $tmp = str_replace('__TITLE__', $title, $add_task_template);
        $tmp = str_replace('__DUEDATE__', $timestamp, $tmp);
        $tmp = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);
        $hash = date('Ymd', $timestamp) . hash('sha512', $title);
        $list_add_task[$hash] = $tmp;
    }
    $count_task = count($list_add_task);
    // $mu_->post_blog_fc2("Highway Task Add : ${count_task}");
    file_put_contents($file_name_blog_, "Highway Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks Highway : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_sun($mu_, $file_name_blog_)
{
    // 翌日の日の出、日の入りタスク

    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_label = $mu_->get_folder_id('LABEL');
    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $timestamp = strtotime('+1 day');
    $yyyy = date('Y', $timestamp);
    $mm = date('m', $timestamp);

    $res = $mu_->get_contents("https://eco.mtk.nao.ac.jp/koyomi/dni/${yyyy}/s" . $mu_->get_env('AREA_ID') . $mm . '.html', null, true);
    $res = mb_convert_encoding($res, 'UTF-8', 'EUC-JP');

    $tmp = explode('<table ', $res);
    $tmp = explode('</table>', $tmp[1]);
    $tmp = explode('</tr>', $tmp[0]);
    array_shift($tmp);
    array_pop($tmp);

    $list_add_task = [];
    $add_task_template = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__","tag":"WEATHER2","folder":"'
        . $folder_id_label . '"}';
    for ($i = 0; $i < count($tmp); $i++) {
        $rc = preg_match('/<tr><td.*?>' . substr(' ' . date('j', $timestamp), -2) . '<\/td>/', $tmp[$i]);
        if ($rc == 1) {
            $rc = preg_match('/.+?<\/td>.*?<td>(.+?)<\/td>.*?<td>.+?<\/td>.*?<td>.+?<\/td>.*?<td>.+?<\/td>.*?<td>(.+?)</', $tmp[$i], $matches);

            $tmp = date('m/d', $timestamp) . ' 0' . trim($matches[1]) . ' 日の出';
            $tmp = str_replace('__TITLE__', $tmp, $add_task_template);
            $tmp = str_replace('__DUEDATE__', $timestamp, $tmp);
            $tmp = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);
            $list_add_task[] = $tmp;

            $title = date('m/d', $timestamp) . ' ' . trim($matches[2]) . ' 日の入り';
            $tmp = str_replace('__TITLE__', $title, $add_task_template);
            $tmp = str_replace('__DUEDATE__', $timestamp, $tmp);
            $tmp = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);
            $hash = date('Ymd', $timestamp) . hash('sha512', $title);
            $list_add_task[$hash] = $tmp;
            break;
        }
    }
    $count_task = count($list_add_task);
    file_put_contents($file_name_blog_, "Sun Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks Sun : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_moon($mu_, $file_name_blog_)
{
    // 翌日の月の出、月の入りタスク

    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_label = $mu_->get_folder_id('LABEL');
    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $timestamp = strtotime('+1 day');
    $yyyy = date('Y', $timestamp);
    $mm = date('m', $timestamp);

    $res = $mu_->get_contents('https://eco.mtk.nao.ac.jp/koyomi/dni/' . $yyyy . '/m' . $mu_->get_env('AREA_ID') . $mm . '.html', null, true);

    $res = mb_convert_encoding($res, 'UTF-8', 'EUC-JP');

    $tmp = explode('<table ', $res);
    $tmp = explode('</table>', $tmp[1]);
    $tmp = explode('</tr>', $tmp[0]);
    array_shift($tmp);
    array_pop($tmp);

    $list_add_task = [];
    $add_task_template = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__","tag":"WEATHER2","folder":"'
      . $folder_id_label . '"}';
    for ($i = 0; $i < count($tmp); $i++) {
        $rc = preg_match('/<tr><td.*?>' . substr(' ' . date('j', $timestamp), -2) . '<\/td>/', $tmp[$i]);
        if ($rc == 1) {
            $rc = preg_match('/.+?<\/td>.*?<td>(.+?)<\/td>.*?<td>.+?<\/td>.*?<td>.+?<\/td>.*?<td>.+?<\/td>.*?<td>(.+?)</', $tmp[$i], $matches);

            if (trim($matches[1]) != '--:--') {
                $title = date('m/d', $timestamp) . ' ' . substr('0' . trim($matches[1]), -5) . ' 月の出';
                $tmp = str_replace('__TITLE__', $title, $add_task_template);
                $tmp = str_replace('__DUEDATE__', $timestamp, $tmp);
                $tmp = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);
                $hash = date('Ymd', $timestamp) . hash('sha512', $title);
                $list_add_task[] = $tmp;
            }

            if (trim($matches[2]) != '--:--') {
                $title = date('m/d', $timestamp) . ' ' . substr('0' . trim($matches[2]), -5) . ' 月の入り';
                $tmp = str_replace('__TITLE__', $title, $add_task_template);
                $tmp = str_replace('__DUEDATE__', $timestamp, $tmp);
                $tmp = str_replace('__CONTEXT__', $list_context_id[date('w', $timestamp)], $tmp);
                $hash = date('Ymd', $timestamp) . hash('sha512', $title);
                $list_add_task[] = $tmp;
            }
            break;
        }
    }
    $count_task = count($list_add_task);
    file_put_contents($file_name_blog_, "Moon Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks Moon : ' . print_r($list_add_task, true));
    return $list_add_task;
}
