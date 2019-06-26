<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = check_train($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function check_train($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    
    $res = $mu_->get_contents($url, null, true);
    
    $stations = [];
    foreach (json_decode($res, true)['stations'] as $station) {
        $stations[$station['info']['code']] = $station['info']['name'];
    }
    
    // error_log(print_r($stations, true));
    
    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';

    $res = $mu_->get_contents($url);
    $json = json_decode($res, true);
    
    // error_log(print_r($json, true));
    
    $update_time = $json['update'];
    $delays_up = [];
    $delays_down = [];
    foreach ($json['trains'] as $train) {
        if ($train['delayMinutes'] != '0') {
            // error_log(print_r($train, true));
            $tmp = explode('_', $train['pos']);
            $station_name = $stations[$tmp[0]];
            // error_log($station_name);
            if ($train['direction'] == '0') {
                $delays_up[] = '上り ' . $station_name . ' ' . $train['dest'] . '行き ' . $train['displayType']
                    . ' ' . $train['delayMinutes'] . '分遅れ';
            } else {
                $delays_down[] = '下り ' . $station_name . ' ' . $train['dest'] . '行き ' . $train['displayType']
                    . ' ' . $train['delayMinutes'] . '分遅れ';
            }
        }
    }
    /*
    error_log(print_r($delays_up, true));
    error_log(print_r($delays_down, true));
    */
    $description = '';
    if (count($delays_up) > 0) {
        $description = implode("\n", $delays_up);
    }
    if (count($delays_down) > 0) {
        $description .= "\n\n";
        $description .= implode("\n", $delays_down);
    }
    if (trim($description) != '') {
        $mu_->post_blog_livedoor('TRAIN', trim($description));
    }
}
    
