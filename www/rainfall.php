<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);

error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

const LIST_YOBI = array('日', '月', '火', '水', '木', '金', '土');

$mu = new MyUtils();

if (!isset($_GET['c'])
    || $_GET['c'] === ''
    || is_array($_GET['c'])
    || !ctype_digit($_GET['c'])
    || (int)$_GET['c'] > 12
   ) {
    error_log("${pid} FINISH Invalid Param");
    exit();
}

if ((int)date('i') < 5) {
    error_log("${pid} FINISH Stop Term");
    exit();    
}

$count = (int)$_GET['c'];

$continue_flag = false;
if ($count !== 0) {
    error_log("${pid} SLEEP");
    sleep(28);
    $url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com' . $_SERVER['PHP_SELF'] . '?c=' . ($count - 1);
    // $options = [CURLOPT_TIMEOUT => 3, CURLOPT_USERPWD => getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD')];
    // $res = $mu->get_contents($url, $options);
    exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");
} else {
    $file_name_blog = '/tmp/rainfall.txt';
    @unlink($file_name_blog);
    
    // Access Token
    $access_token = $mu->get_access_token();
    
    // Get Tasks
    $url = 'https://api.toodledo.com/3/tasks/get.php'
        . "?comp=0&fields=tag,duedate&access_token=${access_token}&after=" . strtotime('-30 minutes');
    $res = $mu->get_contents($url);
    $tasks = json_decode($res, true);
    
    $list_delete_task = [];
    foreach ($tasks as $task) {
        if (array_key_exists('duedate', $task) && array_key_exists('tag', $task)) {
            if ($task['duedate'] == 1514808000 && $task['tag'] == 'HOURLY') {
                // 1514808000 = 2018/01/01
                $list_delete_task[] = $task['id'];
                break;
            }
        }
    }    
    $list_add_task = get_task_rainfall2($mu, $file_name_blog);
    foreach ($list_add_task as $task) {
        if (strpos($task, '☀') === false) {
            $continue_flag = true;
        }
    }
    
    // Add Tasks
    $rc = $mu->add_tasks($list_add_task);
    
    // Delete Tasks
    $mu->delete_tasks($list_delete_task);
    
    if ($continue_flag === true) {
        $url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com' . $_SERVER['PHP_SELF'] . '?c=11';
        // $options = [CURLOPT_TIMEOUT => 2, CURLOPT_USERPWD => getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD')];
        // $res = $mu->get_contents($url, $options);        
        exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");
    }
}

$time_finish = microtime(true);
if ($count === 0) {
    $title = $requesturi . ' [' . substr(($time_finish - $time_start), 0, 6) . 's]';
    $description = $mu->to_big_size(file_get_contents($file_name_blog));
    $mu->post_blog_wordpress($title, $description, true);
    $mu->post_blog_livedoor($title, $description, 'rain');
    unlink($file_name_blog);
}
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');

exit();

function get_task_rainfall2($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    // Get Folders
    $folder_id_label = $mu_->get_folder_id('LABEL');

    // Get Contexts
    $list_context_id = $mu_->get_contexts();

    $list_add_task = [];

    $url = $mu_->get_env('URL_KASA_SHISU_YAHOO');
    $res = $mu_->get_contents($url);

    $rc = preg_match('/<!--指数情報-->.+?<span>傘指数(.+?)<.+?<p class="index_text">(.+?)</s', $res, $matches);
    $suffix = ' 傘指数' . $matches[1] . ' ' . $matches[2];

    $longitude = $mu_->get_env('LONGITUDE');
    $latitude = $mu_->get_env('LATITUDE');
    $api_key_yahoo = $mu_->get_env('YAHOO_API_KEY', true);

    $url = 'https://map.yahooapis.jp/geoapi/V1/reverseGeoCoder?output=json&appid=' . $api_key_yahoo
        . '&lon=' . $longitude . '&lat=' . $latitude;
    $res = $mu_->get_contents($url, null, true);
    $data = json_decode($res, true);
    error_log($log_prefix . '$data : ' . print_r($data, true));

    $url = 'https://map.yahooapis.jp/weather/V1/place?interval=5&output=json&appid=' . $api_key_yahoo
        . '&coordinates=' . $longitude . ',' . $latitude;
    $res = $mu_->get_contents($url);

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
        $tmp = '☂ ' . implode(' ', $list_rainfall);
        file_put_contents($file_name_blog_, implode("\n", $list_rainfall));
    } else {
        $tmp = '☀';
        file_put_contents($file_name_blog_, 'NONE');
    }
    $update_marker = $mu_->to_small_size(' _' . date('Ymd Hi', strtotime('+ 9 hours')) . '_');
    $list_add_task[] = '{"title":"' . $tmp . $suffix . $update_marker
      . '","duedate":"' . mktime(0, 0, 0, 1, 1, 2018)
      . '","context":"' . $list_context_id[date('w', mktime(0, 0, 0, 1, 1, 2018))]
      . '","tag":"HOURLY","folder":"' . $folder_id_label . '"}';

    error_log($log_prefix . 'TASKS RAINFALL : ' . print_r($list_add_task, true));
    return $list_add_task;
}
