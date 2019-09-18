<?php

/*
hourly
→ (rainfall)
→ (check_zoho_file_size)
→ search_hotel
  → get_twitter_jaxa
    → get_river_image
  → lib_info * 4
    → (library_rental_ok)
*/
include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

const LIST_YOBI = array('日', '月', '火', '水', '木', '金', '土');

$rc = apcu_clear_cache();

$mu = new MyUtils();

$hour_now = (int)date('G', strtotime('+9 hours')); // JST

$file_outlet_parking_information = '/tmp/outlet_parking_information.txt';
@unlink($file_outlet_parking_information);

$longitude = $mu->get_env('LONGITUDE');
$latitude = $mu->get_env('LATITUDE');
$api_key_yahoo = $mu->get_env('YAHOO_API_KEY', true);

// cache search off url list

// outlet parking information ここでは呼び捨て 後で回収
$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/outlet_parking_information.php';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

$urls[$mu->get_env('URL_AMEDAS')] = null;
$urls[$mu->get_env('URL_WEATHER_WARN')] = null;
$urls[$mu->get_env('URL_TAIKAN_SHISU')] = null;
$urls[$mu->get_env('URL_KASA_SHISU')] = null;
$urls[$mu->get_env('URL_KASA_SHISU_YAHOO')] = null;
$urls[$mu->get_env('URL_RIVER_1')] = null;
$urls[$mu->get_env('URL_RIVER_2')] = null;
for ($i = 1; $i < 5; $i++) {
    $urls[$mu->get_env('URL_PARKING_1') . '?park_id=' . $i . '&mode=pc'] = null;
}
$url = "https://map.yahooapis.jp/weather/V1/place?interval=5&output=json&appid=${api_key_yahoo}&coordinates=${longitude},${latitude}";
$urls[$url] = null;

if ($hour_now % 2 === 1) {
    $urls['https://tenki.jp/week/' . $mu->get_env('LOCATION_NUMBER') . '/?4nocache' . date('YmdH', strtotime('+9 hours'))] = null;
}

// cache search on url list

$url = 'https://map.yahooapis.jp/geoapi/V1/reverseGeoCoder?output=json&appid=' . $api_key_yahoo
    . '&lon=' . $longitude . '&lat=' . $latitude;
$urls_is_cache[$url] = null;

// get contents multi
$list_contents = $mu->get_contents_multi($urls, $urls_is_cache);

// Access Token
$access_token = $mu->get_access_token();

// Get Folders
$folder_id_work = $mu->get_folder_id('WORK');
$folder_id_label = $mu->get_folder_id('LABEL');

// Get Contexts
$list_context_id = $mu->get_contexts();

$list_add_task = [];

if ($hour_now % 2 === 1) {
    // holiday
    $list_holiday = get_holiday($mu);

    // 24sekki
    $list_24sekki = get_24sekki($mu);

    // Sun rise set
    $list_sunrise_sunset = get_sun_rise_set($mu);

    // Moon age
    $list_moon_age = get_moon_age($mu);

    // 指数 傘 体感
    $list_shisu = get_shisu($mu, $list_contents);

    // Weather Information

    // $res = $mu->get_contents('https://tenki.jp/week/' . $mu->get_env('LOCATION_NUMBER') . '/');
    $url = 'https://tenki.jp/week/' . $mu->get_env('LOCATION_NUMBER') . '/?4nocache' . date('YmdH', strtotime('+9 hours'));
    if (array_key_exists($url, $list_contents)) {
        $res = $list_contents[$url];
    } else {
        $res = $mu->get_contents($url);
    }

    $rc = preg_match('/announce_datetime:(\d+-\d+-\d+) (\d+)/', $res, $matches);

    $dt = $matches[1]; // yyyy-mm-dd

    $update_marker = $mu->to_small_size(' _' . substr($matches[1], 8) . $matches[2] . '_'); // __DDHH__

    $tmp = explode(getenv('POINT_NAME'), $res);
    $tmp = explode('<td class="forecast-wrap">', $tmp[1]);

    $template_add_task = '{"title":"__TITLE__","duedate":"__DUEDATE__","context":"__CONTEXT__",'
      . '"tag":"WEATHER","folder":"__FOLDER_ID__"}';
    $template_add_task = str_replace('__FOLDER_ID__', $folder_id_label, $template_add_task);
    $url_kasa_shisu = $mu->get_env('URL_KASA_SHISU');
    $url_taikan_shisu = $mu->get_env('URL_TAIKAN_SHISU');
    for ($i = 0; $i < 10; $i++) {
        $ymd = date('Ymd', strtotime("${dt} +${i} day"));
        $list = explode("\n", str_replace(' ', '', trim(strip_tags($tmp[$i + 1]))));
        $tmp2 = $list[0];
        $tmp2 = str_replace('晴', '☀️', $tmp2);
        $tmp2 = str_replace('曇', '☁️', $tmp2);
        $tmp2 = str_replace('雨', '☂️', $tmp2);
        $tmp2 = str_replace('雪', '❄️', $tmp2);
        $tmp2 = str_replace('のち', '/', $tmp2);
        $tmp2 = str_replace('時々', '|', $tmp2);
        $tmp2 = str_replace('一時', '|', $tmp2);
        $tmp3 = '### '
          . LIST_YOBI[date('w', strtotime($ymd))] . '曜日 '
          . date('m/d', strtotime($ymd))
          . ' ### '
          . $tmp2 . ' ' . $list[2] . ' ' . $list[1]
          . $update_marker;

        if (array_key_exists($ymd, $list_holiday)) {
            $tmp3 = str_replace(' ###', ' ★' . $list_holiday[$ymd] . '★ ###', $tmp3);
        }
        if (array_key_exists($ymd, $list_24sekki)) {
            $tmp3 .= $list_24sekki[$ymd];
        }
        if (array_key_exists($ymd, $list_sunrise_sunset)) {
            $tmp3 .= ' ' . $list_sunrise_sunset[$ymd];
        }
        if (array_key_exists($ymd, $list_moon_age)) {
            $tmp3 .= ' ' . $list_moon_age[$ymd];
        }
        if (array_key_exists($ymd, $list_shisu[$url_kasa_shisu])) {
            $tmp3 .= ' 傘' . $list_shisu[$url_kasa_shisu][$ymd];
        }
        if (array_key_exists($ymd, $list_shisu[$url_taikan_shisu])) {
            $tmp3 .= ' 体' . $list_shisu[$url_taikan_shisu][$ymd];
        }

        error_log("${pid} ${tmp3}");

        $tmp4 = str_replace('__TITLE__', $tmp3, $template_add_task);
        $tmp4 = str_replace('__DUEDATE__', strtotime($ymd), $tmp4);
        $tmp4 = str_replace('__CONTEXT__', $list_context_id[date('w', strtotime($ymd))], $tmp4);

        $list_add_task[] = $tmp4;
    }

    // Weather Information (Guest)

    $list_weather_guest_area = $mu->get_weather_guest_area();

    $update_marker = $mu->to_small_size(' _' . date('Ymd', strtotime('+9 hours')) . '_');
    foreach ($list_weather_guest_area as $weather_guest_area) {
        $is_add_flag = false;
        $tmp = explode(',', $weather_guest_area);
        $location_number = $tmp[0];
        $point_name = $tmp[1];
        $ymd = $tmp[2];
        if ((int)$ymd < (int)date('Ymd', strtotime('+11 days') + 9 * 60 * 60)) {
            $res = $mu->get_contents('https://tenki.jp/week/' . $location_number . '/');
            $rc = preg_match('/announce_datetime:(\d+-\d+-\d+) (\d+)/', $res, $matches);
            $dt = $matches[1]; // yyyy-mm-dd
            $tmp = explode($point_name, $res);
            $tmp = explode('<td class="forecast-wrap">', $tmp[1]);
            for ($i = 0; $i < 10; $i++) {
                $timestamp = strtotime("${dt} +${i} day") + 9 * 60 * 90;
                if (date('Ymd', $timestamp) == $ymd) {
                    $list = explode("\n", str_replace(' ', '', trim(strip_tags($tmp[$i + 1]))));
                    $title = date('m/d', $timestamp)
                      . " 【${point_name} ${list[0]} ${list[2]} ${list[1]}】${update_marker}";
                    $is_add_flag = true;
                    break;
                }
            }
        }
        if ($is_add_flag === false) {
            $title = date('m/d', strtotime($ymd)) . " 【${point_name} 天気予報未取得】${update_marker}";
        }
        $tmp = str_replace('__TITLE__', $title, $template_add_task);
        $tmp = str_replace('__DUEDATE__', strtotime($ymd), $tmp);
        $tmp = str_replace('__CONTEXT__', $list_context_id[date('w', strtotime($ymd))], $tmp);
        $list_add_task[] = $tmp;
    }
}

// amedas
$list_add_task = array_merge($list_add_task, get_task_amedas($mu, $list_contents));

// Rainfall
$rainfall_continue_flag = false;
foreach (get_task_rainfall($mu, $list_contents) as $task) {
    $list_add_task[] = $task;
    if (strpos($task, '☀') === false) {
        $rainfall_continue_flag = true;
    }
}

// parking information
$list_add_task = array_merge($list_add_task, get_task_parking_information($mu, $list_contents, $file_outlet_parking_information));

// Get Tasks
$url = 'https://api.toodledo.com/3/tasks/get.php'
  . '?comp=0&fields=tag,duedate,context,star,folder&access_token=' . $access_token;
$res = $mu->get_contents($url);
$tasks = json_decode($res, true);

error_log($pid . ' TASKS COUNT : ' . count($tasks));

// heroku buildpack php
check_heroku_buildpack_php($mu);

// iCalendar データ作成
make_ical($mu, $tasks);

// 予定有りでラベル無しの日のラベル追加

$list_label_task = [];
$list_schedule_task = [];
foreach ($tasks as $task) {
    if (array_key_exists('duedate', $task) && array_key_exists('folder', $task)) {
        if ($task['folder'] == $folder_id_label) {
            $list_label_task[] = $task['duedate'];
        } else {
            $list_schedule_task[] = $task['duedate'];
        }
    }
}

$list_non_label = array_unique(array_diff($list_schedule_task, $list_label_task));
sort($list_non_label);
error_log($pid . ' $list_non_label : ' . print_r($list_non_label, true));

$timestamp = strtotime('+20 day') + 9 * 60 * 60;
foreach ($list_non_label as $non_label) {
    if ($non_label > $timestamp) {
        $yyyy = $mu->to_small_size(date('Y', $non_label));

        $tmp = '### ' . LIST_YOBI[date('w', $non_label)] . '曜日 '
          . date('m/d', $non_label) . ' ### ' . $yyyy;
        $list_add_task[] = '{"title":"' . $tmp
          . '","duedate":"' . $non_label
          . '","context":"' . $list_context_id[date('w', $non_label)]
          . '","tag":"ADDITIONAL","folder":"' . $folder_id_label . '"}';
    }
}

// 削除タスク抽出

$is_exists_no_duedate_task = false;
$list_delete_task = [];
foreach ($tasks as $task) {
    if (array_key_exists('id', $task) && array_key_exists('tag', $task)) {
        if ($task['tag'] == 'HOURLY' || ($hour_now % 2 === 1 && $task['tag'] == 'WEATHER')) {
            $list_delete_task[] = $task['id'];
        } elseif ($task['duedate'] == 0) {
            $is_exists_no_duedate_task = true;
        }
    }
}
error_log($pid . ' $list_delete_task : ' . print_r($list_delete_task, true));

// 日付(duedate)設定漏れ警告

if ($is_exists_no_duedate_task === true) {
    $list_add_task[] = '{"title":"NO DUEDATE TASK EXISTS","duedate":"' . mktime(0, 0, 0, 12, 31, 2017)
        . '","context":"' . $list_context_id[date('w', mktime(0, 0, 0, 12, 31, 2017))]
        . '","tag":"HOURLY","folder":"' . $folder_id_label . '"}';
}

error_log($pid . ' $list_add_task : ' . print_r($list_add_task, true));

// WORK & Star の日付更新

$list_edit_task = [];
$template_edit_task = '{"id":"__ID__","title":"__TITLE__","context":"__CONTEXT__"}';
foreach ($tasks as $task) {
    if (array_key_exists('id', $task) && array_key_exists('folder', $task)) {
        if ($task['folder'] == $folder_id_work && $task['star'] == '1') {
            $duedate = $task['duedate'];
            $title = $task['title'];
            if (substr($title, 0, 5) == date('m/d', $duedate)) {
                continue;
            }
            $tmp = str_replace('__ID__', $task['id'], $template_edit_task);
            $tmp = str_replace('__TITLE__', date('m/d', $duedate) . substr($title, 5), $tmp);
            $tmp = str_replace('__CONTEXT__', $list_context_id[date('w', $duedate)], $tmp);
            $list_edit_task[] = $tmp;
        }
    }
}

// duedate と context の不一致更新

$template_edit_task = '{"id":"__ID__","context":"__CONTEXT__"}';
foreach ($tasks as $task) {
    if (array_key_exists('id', $task)) {
        $real_context_id = $list_context_id[date('w', $task['duedate'])];
        $task_context_id = $task['context'];
        if ($task_context_id == '0' || $task_context_id != $real_context_id) {
            error_log($pid . ' $task : ' . print_r($task, true));
            $tmp = str_replace('__ID__', $task['id'], $template_edit_task);
            $tmp = str_replace('__CONTEXT__', $real_context_id, $tmp);
            $list_edit_task[] = $tmp;
        }
    }
}

error_log($pid . ' $list_edit_task : ' . print_r($list_edit_task, true));

// Add Tasks
$count_add_task = count($list_add_task);
$rc = $mu->add_tasks($list_add_task);

// Edit Tasks
$count_edit_task = count($list_edit_task);
$rc = $mu->edit_tasks($list_edit_task);

// Delete Tasks
$count_delete_task = count($list_delete_task);
$mu->delete_tasks($list_delete_task);

$host_name = getenv('HEROKU_APP_NAME');
if ($hour_now > 5 && $hour_now < 18) {
    $host_name = substr($host_name, 0, strlen($host_name) - 1) . '2';
    $url = 'https://' . $host_name . '.herokuapp.com/check_zoho_file_size.php';
    exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");
}

if ($rainfall_continue_flag === true) {
    $url = 'https://' . $host_name . '.herokuapp.com/rainfall.php?c=11';
    exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");
}

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/search_hotel.php';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

error_log($pid . ' apcu_sma_info : ' . print_r(apcu_sma_info(), true));
error_log($pid . ' apcu_cache_info : ' . print_r(apcu_cache_info(), true));

if (apcu_exists('HTTP_STATUS') === true) {
    $dic_http_status = apcu_fetch('HTTP_STATUS');
} else {
    $dic_http_status = [];
}
ksort($dic_http_status);
$blog_text = '';
foreach ($dic_http_status as $key => $val) {
    $blog_text .= "${key} : ${val}\n";
}

$time_finish = microtime(true);
$mu->post_blog_wordpress("${requesturi} add : ${count_add_task} / edit : ${count_edit_task} / delete : ${count_delete_task} ["
                         . substr(($time_finish - $time_start), 0, 6) . 's]',
                         $blog_text,
                         'hourly'
                        );
error_log($pid . ' Web Access Count : ' . $mu->_count_web_access);
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');

exit();

function check_heroku_buildpack_php($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://raw.githubusercontent.com/tshr20140816/heroku-mode-07/master/composer.lock';
    $res = $mu_->get_contents($url);
    $res = json_decode($res, true)['packages-dev'];
    
    foreach ($res as $item) {
        if ($item['name'] == 'heroku/heroku-buildpack-php') {
            $current_version = $item['version'];
            break;
        }
    }
    
    $res = file_get_contents('/app/composer.lock');
    $res = json_decode($res, true)['packages-dev'];
    
    foreach ($res as $item) {
        if ($item['name'] == 'heroku/heroku-buildpack-php') {
            $latest_version = $item['version'];
            break;
        }
    }
    
    error_log($log_prefix . 'heroku-buildpack-php current : ' . $current_version);
    error_log($log_prefix . 'heroku-buildpack-php latest : ' . $latest_version);
    if ($current_version != $latest_version) {
            $mu_->post_blog_wordpress_async('heroku-buildpack-php : update ' . $latest_version);
    }
}

function get_task_parking_information($mu_, $list_contents_, $file_outlet_parking_information_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_label = $mu_->get_folder_id('LABEL');

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $list_parking_name = [' ', '体', 'ク', 'セ', 'シ'];

    $list_add_task = [];

    $update_marker = $mu_->to_small_size(' _' . date('Ymd Hi', strtotime('+ 9 hours')) . '_');

    $parking_information_all = '';
    for ($i = 1; $i < 5; $i++) {
        $url = $mu_->get_env('URL_PARKING_1') . '?park_id=' . $i . '&mode=pc';
        if (array_key_exists($url, $list_contents_)) {
            $res = $list_contents_[$url];
        } else {
            $res = $mu_->get_contents($url);
        }

        $hash_text = hash('sha512', $res);

        $pdo = $mu_->get_pdo();

        $sql = <<< __HEREDOC__
SELECT T1.parse_text
  FROM t_imageparsehash T1
 WHERE T1.group_id = 2
   AND T1.hash_text = :b_hash_text;
__HEREDOC__;

        $statement = $pdo->prepare($sql);
        $rc = $statement->execute([':b_hash_text' => $hash_text]);
        error_log($log_prefix . 'SELECT RESULT : ' . $rc);
        $results = $statement->fetchAll();

        $parse_text = '';
        foreach ($results as $row) {
            $parse_text = $row['parse_text'];
        }

        $pdo = null;

        if (strlen($parse_text) == 0) {
            $parse_text = '不明';
            error_log($log_prefix . '$hash_text : ' . $hash_text);
        }
        $parking_information_all .= ' [' . $list_parking_name[$i] . "]${parse_text}";
    }

    // 最大20秒 outlet_parking_information.php をここで待つ
    for ($i = 0; $i < 20; $i++) {
        if (file_exists($file_outlet_parking_information_) === true) {
            break;
        }
        error_log($log_prefix . 'waiting ' . $i);
        sleep(1);
    }

    if (file_exists($file_outlet_parking_information_) === true) {
        $outlet = file_get_contents($file_outlet_parking_information_);
        $list_add_task[] = '{"title":"P [ア]' . $outlet . $parking_information_all . $update_marker
            . '","duedate":"' . mktime(0, 0, 0, 1, 5, 2018)
            . '","context":"' . $list_context_id[date('w', mktime(0, 0, 0, 1, 5, 2018))]
            . '","tag":"HOURLY","folder":"' . $folder_id_label . '"}';
    }

    error_log($log_prefix . 'TASKS PARKING INFORMATION : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_amedas($mu_, $list_contents_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_label = $mu_->get_folder_id('LABEL');

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $list_add_task = [];

    $url = $mu_->get_env('URL_AMEDAS');
    if (array_key_exists($url, $list_contents_)) {
        $res = $list_contents_[$url];
    } else {
        $res = $mu_->get_contents($url);
    }

    $tmp = explode('">時刻</td>', $res);
    $tmp = explode('</table>', $tmp[1]);

    $tmp1 = explode('</tr>', $tmp[0]);
    $headers = explode('</td>', $tmp1[0]);
    error_log($log_prefix . '$headers : ' . print_r($headers, true));

    for ($i = 0; $i < count($headers); $i++) {
        switch (trim(strip_tags($headers[$i]))) {
            case '気温':
                $index_temp = $i + 2;
                break;
            case '降水量':
                $index_rain = $i + 2;
                break;
            case '風向':
                $index_wind = $i + 2;
                break;
            case '風速':
                $index_wind_speed = $i + 2;
                break;
            case '湿度':
                $index_humi = $i + 2;
                break;
            case '気圧':
                $index_pres = $i + 2;
                break;
        }
    }

    $rc = preg_match_all(
        '/<tr>.*?<td.*?>(.+?)<\/td>.*?' . str_repeat('<td.*?>(.+?)<\/td>', count($headers) - 1) . '.+?<\/tr>/s',
        $tmp[0],
        $matches,
        PREG_SET_ORDER
    );
    array_shift($matches);

    $title = '';
    foreach ($matches as $match) {
        $hour = $match[1];
        $temp = $match[$index_temp];
        $rain = $match[$index_rain];
        $wind = $match[$index_wind] . $match[$index_wind_speed];
        $humi = $match[$index_humi];
        $pres = $match[$index_pres];
        if ($temp == '&nbsp;') {
            continue;
        }
        $title = "${hour}時 ${temp}℃ ${humi}% ${rain}mm ${wind}m/s ${pres}hPa";
    }

    // 警報 注意報

    $url = $mu_->get_env('URL_WEATHER_WARN');
    if (array_key_exists($url, $list_contents_)) {
        $res = $list_contents_[$url];
    } else {
        $res = $mu_->get_contents($url);
    }

    $rc = preg_match_all('/<ul class="warnDetail_head_labels">(.+?)<\/ul>/s', $res, $matches, PREG_SET_ORDER);
    $tmp = preg_replace('/<.+?>/s', ' ', $matches[0][1]);
    $warn = trim(preg_replace('/\s+/s', ' ', $tmp));

    // 体感指数

    $url = $mu_->get_env('URL_TAIKAN_SHISU');
    if (array_key_exists($url, $list_contents_)) {
        $res = $list_contents_[$url];
    } else {
        $res = $mu_->get_contents($url);
    }

    $rc = preg_match('/<!-- today index -->.+?<span class="indexes-telop-0">(.+?)<\/span>/s', $res, $matches);
    $taikan_shisu = ' 体感指数 : ' . $matches[1];

    if ($title != '') {
        $list_add_task[] = '{"title":"' . $title . ' ' . $warn . $taikan_shisu
          . '","duedate":"' . mktime(0, 0, 0, 1, 2, 2018)
          . '","context":"' . $list_context_id[date('w', mktime(0, 0, 0, 1, 2, 2018))]
          . '","tag":"HOURLY","folder":"' . $folder_id_label . '"}';
    }

    error_log($log_prefix . 'TASKS AMEDAS : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_task_rainfall($mu_, $list_contents_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_label = $mu_->get_folder_id('LABEL');

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $list_add_task = [];

    $url = $mu_->get_env('URL_KASA_SHISU_YAHOO');
    if (array_key_exists($url, $list_contents_)) {
        $res = $list_contents_[$url];
    } else {
        $res = $mu_->get_contents($url);
    }

    $rc = preg_match('/<!--指数情報-->.+?<span>傘指数(.+?)<.+?<p class="index_text">(.+?)</s', $res, $matches);
    $suffix = ' 傘指数' . $matches[1] . ' ' . $matches[2];

    $longitude = $mu_->get_env('LONGITUDE');
    $latitude = $mu_->get_env('LATITUDE');
    $api_key_yahoo = $mu_->get_env('YAHOO_API_KEY', true);

    $url = 'https://map.yahooapis.jp/geoapi/V1/reverseGeoCoder?output=json&appid=' . $api_key_yahoo
        . '&lon=' . $longitude . '&lat=' . $latitude;
    if (array_key_exists($url, $list_contents_)) {
        $res = $list_contents_[$url];
    } else {
        $res = $mu_->get_contents($url, null, true);
    }
    $data = json_decode($res, true);
    // error_log($log_prefix . '$data : ' . print_r($data, true));
    error_log($log_prefix . $data['Feature'][0]['Property']['Building'][0]['Name']);

    $url = 'https://map.yahooapis.jp/weather/V1/place?interval=5&output=json&appid=' . $api_key_yahoo
        . '&coordinates=' . $longitude . ',' . $latitude;
    if (array_key_exists($url, $list_contents_)) {
        $res = $list_contents_[$url];
    } else {
        $res = $mu_->get_contents($url);
    }

    $data = json_decode($res, true);
    error_log($log_prefix . '$data : ' . print_r($data, true));
    $data = $data['Feature'][0]['Property']['WeatherList']['Weather'];

    $list_rainfall = [];
    foreach ($data as $rainfall) {
        if ($rainfall['Rainfall'] != '0') {
            $list_rainfall[] = $mu_->to_small_size(substr($rainfall['Date'], 8)) . ' ' . $rainfall['Rainfall'];
        }
    }
    if (count($list_rainfall) > 0) {
        $tmp = '☔ ' . implode(' ', $list_rainfall);
    } else {
        $tmp = '☀';
    }
    $update_marker = $mu_->to_small_size(' _' . date('Ymd Hi', strtotime('+ 9 hours')) . '_');
    $list_add_task[] = '{"title":"' . $tmp . $suffix . $update_marker
      . '","duedate":"' . mktime(0, 0, 0, 1, 1, 2018)
      . '","context":"' . $list_context_id[date('w', mktime(0, 0, 0, 1, 1, 2018))]
      . '","tag":"HOURLY","folder":"' . $folder_id_label . '"}';

    error_log($log_prefix . 'TASKS RAINFALL : ' . print_r($list_add_task, true));
    return $list_add_task;
}

function get_holiday($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $start_yyyy = date('Y');
    $start_m = date('n');
    $finish_yyyy = date('Y', strtotime('+1 month'));
    $finish_m = date('n', strtotime('+1 month'));

    $url = 'http://calendar-service.net/cal?start_year=' . $start_yyyy . '&start_mon=' . $start_m
      . '&end_year=' . $finish_yyyy . '&end_mon=' . $finish_m
      . '&year_style=normal&month_style=numeric&wday_style=ja_full&format=csv&holiday_only=1&zero_padding=1';

    $res = $mu_->get_contents($url, null, true);
    $res = mb_convert_encoding($res, 'UTF-8', 'EUC-JP');

    $tmp = explode("\n", $res);
    array_shift($tmp);
    array_pop($tmp);

    $list_holiday = [];
    for ($i = 0; $i < count($tmp); $i++) {
        $tmp1 = explode(',', $tmp[$i]);
        $list_holiday[date('Ymd', mktime(0, 0, 0, $tmp1[1], $tmp1[2], $tmp1[0]))] = $tmp1[7];
    }
    error_log($log_prefix . '$list_holiday : ' . print_r($list_holiday, true));

    return $list_holiday;
}

function get_24sekki($mu_)
{
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
            error_log($log_prefix . "${tmp1} " . $matches[1]);
            $list_24sekki[date('Ymd', strtotime($tmp1))] = '【' . $matches[1] . '】';
        }
        $yyyy++;
    }
    error_log($log_prefix . '$list_24sekki : ' . print_r($list_24sekki, true));

    return $list_24sekki;
}

function get_sun_rise_set($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $timestamp = time() + 9 * 60 * 60; // JST
    // 10日後が翌月になるときは2か月分取得
    $loop_count = date('m', $timestamp) === date('m', $timestamp + 10 * 24 * 60 * 60) ? 1 : 2;

    $area_id = $mu_->get_env('AREA_ID');
    $list_sunrise_sunset = [];
    for ($j = 0; $j < $loop_count; $j++) {
        if ($j === 1) {
            $timestamp = time() + 9 * 60 * 60 + 10 * 24 * 60 * 60; // JST
        }
        $yyyy = date('Y', $timestamp);
        $mm = date('m', $timestamp);

        $res = $mu_->get_contents(
            'https://eco.mtk.nao.ac.jp/koyomi/dni/' . $yyyy . '/s' . $area_id . $mm . '.html',
            null,
            true
        );

        $tmp = explode('<table ', $res);
        $tmp = explode('</table>', $tmp[1]);
        $tmp = explode('</tr>', $tmp[0]);
        array_shift($tmp);
        array_pop($tmp);

        $dt = date('Y-m-', $timestamp) . '01';

        for ($i = 0; $i < count($tmp); $i++) {
            $ymd = date('Ymd', strtotime($dt) + $i * 24 * 60 * 60);
            $rc = preg_match(
                '/.+?<\/td>.*?<td>(.+?)<\/td>.*?<td>.+?<\/td>.*?<td>.+?<\/td>.*?<td>.+?<\/td>.*?<td>(.+?)</',
                $tmp[$i],
                $matches
            );
            $list_sunrise_sunset[$ymd] = '↗' . trim($matches[1]) . ' ↘' . trim($matches[2]);
        }
    }
    $list_sunrise_sunset = $mu_->to_small_size($list_sunrise_sunset);
    error_log($log_prefix . '$list_sunrise_sunset : ' . print_r($list_sunrise_sunset, true));

    return $list_sunrise_sunset;
}

function get_moon_age($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $timestamp = time() + 9 * 60 * 60; // JST
    // 10日後が翌月になるときは2か月分取得
    $loop_count = date('m', $timestamp) === date('m', $timestamp + 10 * 24 * 60 * 60) ? 1 : 2;

    $area_id = $mu_->get_env('AREA_ID');
    $list_moon_age = [];
    for ($j = 0; $j < $loop_count; $j++) {
        if ($j === 1) {
            $timestamp = time() + 9 * 60 * 60 + 10 * 24 * 60 * 60; // JST
        }
        $yyyy = date('Y', $timestamp);
        $mm = date('m', $timestamp);

        $res = $mu_->get_contents(
            'https://eco.mtk.nao.ac.jp/koyomi/dni/' . $yyyy . '/m' . $area_id . $mm . '.html',
            null,
            true
        );

        $tmp = explode('<table ', $res);
        $tmp = explode('</table>', $tmp[1]);
        $tmp = explode('</tr>', $tmp[0]);
        array_shift($tmp);
        array_pop($tmp);

        $dt = date('Y-m-', $timestamp) . '01';

        for ($i = 0; $i < count($tmp); $i++) {
            $ymd = date('Ymd', strtotime($dt) + $i * 24 * 60 * 60);
            $rc = preg_match('/.+<td>(.+?)</', $tmp[$i], $matches);
            $list_moon_age[$ymd] = '☽' . trim($matches[1]);
        }
    }
    $list_moon_age = $mu_->to_small_size($list_moon_age);
    error_log($log_prefix . '$list_moon_age : ' . print_r($list_moon_age, true));

    return $list_moon_age;
}

function get_shisu($mu_, $list_contents_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $ymd = date('Ymd', strtotime('+9 hours'));

    $list_shisu = [];
    foreach ([$mu_->get_env('URL_TAIKAN_SHISU'), $mu_->get_env('URL_KASA_SHISU')] as $url) {
        if (array_key_exists($url, $list_contents_)) {
            $res = $list_contents_[$url];
        } else {
            $res = $mu_->get_contents($url);
        }

        $rc = preg_match('/<!-- today index -->.+?<span class="indexes-telop-0">(.+?)<\/span>/s', $res, $matches);
        $list_shisu[$url][$ymd] = $matches[1];

        $rc = preg_match('/<!-- tomorrow index -->.+?<span class="indexes-telop-0">(.+?)<\/span>/s', $res, $matches);
        $list_shisu[$url][date('Ymd', strtotime($ymd) + 24 * 60 * 60)] = $matches[1];

        $rc = preg_match('/<!-- week -->(.+?)<!-- \/week -->/s', $res, $matches);
        $rc = preg_match_all('/<p class="indexes-telop-0">(.+?)<\/p>/s', $matches[1], $matches2, PREG_SET_ORDER);

        for ($i = 0; $i < count($matches2); $i++) {
            $list_shisu[$url][date('Ymd', strtotime($ymd) + 24 * 60 * 60 * ($i + 2))] = $matches2[$i][1];
        }
    }
    error_log($log_prefix . '$list_shisu : ' . print_r($list_shisu, true));

    return $list_shisu;
}

function make_ical($mu_, $tasks_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_label = $mu_->get_folder_id('LABEL');

    $vevent_header = <<< __HEREDOC__
BEGIN:VCALENDAR
VERSION:2.0
__HEREDOC__;

    $vevent_footer = <<< __HEREDOC__
END:VCALENDAR
__HEREDOC__;

    $template_vevent = <<< __HEREDOC__
BEGIN:VEVENT
SUMMARY:__SUMMARY__
DTSTART;VALUE=DATE:__DTSTART__
DTEND;VALUE=DATE:__DTEND__
END:VEVENT
__HEREDOC__;

    $timestamp_yesterday = strtotime('-1 day');

    $list_vevent = [];
    $list_vevent[] = $vevent_header;
    foreach ($tasks_ as $task) {
        if (array_key_exists('id', $task)
            && array_key_exists('folder', $task)
            && array_key_exists('duedate', $task)
           ) {
            if ($folder_id_label == $task['folder'] || $task['duedate'] < $timestamp_yesterday) {
                continue;
            }
            $tmp = $template_vevent;
            if (preg_match('/^\d\d\/\d\d .+/s', $task['title']) == 1) {
                $tmp = str_replace('__SUMMARY__', trim(substr($task['title'], 6)), $tmp);
            } else {
                $tmp = str_replace('__SUMMARY__', $task['title'], $tmp);
            }
            $tmp = str_replace('__DTSTART__', date('Ymd', $task['duedate']), $tmp);
            $tmp = str_replace('__DTEND__', date('Ymd', $task['duedate'] + 24 * 60 * 60), $tmp);
            $list_vevent[] = $tmp;
        }
    }
    $list_vevent[] = $vevent_footer;

    error_log($log_prefix . 'VEVENT COUNT : ' . count($list_vevent));

    $ical_data = implode("\r\n", $list_vevent);

    $pdo = $mu_->get_pdo();

    $sql = 'TRUNCATE TABLE t_ical';
    $statement = $pdo->prepare($sql);
    $rc = $statement->execute();
    error_log($log_prefix . 'TRUNCATE $rc : ' . $rc);

    $sql = 'INSERT INTO t_ical (ical_data) VALUES (:b_ical_data)';
    $statement = $pdo->prepare($sql);
    $rc = $statement->execute([':b_ical_data' => base64_encode(gzencode($ical_data, 9))]);
    error_log($log_prefix . 'INSERT $rc : ' . $rc);

    $pdo = null;
}
