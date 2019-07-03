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

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';
    $res = $mu_->get_contents($url);
    $json = json_decode($res, true);

    $update_time = $json['update'];
    $delays_up = [];
    $delays_down = [];
    foreach ($json['trains'] as $train) {
        if ($train['delayMinutes'] != '0') {
            $tmp = explode('_', $train['pos']);
            $station_name = $stations[$tmp[0]];
            if ($train['direction'] == '0') {
                $delays_up[] = '上り ' . $station_name . ' ' . $train['dest'] . '行き ' . $train['displayType']
                    . ' ' . $train['delayMinutes'] . '分遅れ';
            } else {
                $delays_down[] = '下り ' . $station_name . ' ' . $train['dest'] . '行き ' . $train['displayType']
                    . ' ' . $train['delayMinutes'] . '分遅れ';
            }
        }
    }

    $description = '';
    if (count($delays_up) > 0) {
        $description = implode("\n", $delays_up);
    }
    if (count($delays_down) > 0) {
        $description .= "\n\n";
        $description .= implode("\n", $delays_down);
    }

    if (trim($description) == '') {
        return;
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

    $url = 'https://trafficinfo.westjr.co.jp/chugoku.html';
    $res = $mu_->get_contents($url, $options);
    $res = mb_convert_encoding($res, 'UTF-8', 'SJIS');

    $rc = preg_match("/<div id='syosai_7'>(.+?)<!--#syosai_n-->/s", $res, $match);
    if ($rc == 1) {
        $rc = preg_match_all("/<div class='jisyo'>(.+?)<!-- \.jisyo-->/s", $match[1], $matches);
        if ($rc == false) {
            $rc = 0;
        }
    }

    $description = trim($description) . "\n\n-----";
    if ($rc > 0) {
        foreach ($matches[1] as $item) {
            $tmp = trim(strip_tags($item));
            $tmp = preg_replace('/\t+/', '', $tmp);
            $tmp = mb_convert_kana($tmp, 'as');
            if (strpos($tmp, '【芸備線】 西日本豪雨に伴う 運転見合わせ') == false) {
                $description .= "\n\n" . $tmp;
            }
        }
    }

    $mu_->post_blog_livedoor('TRAIN', $description);
}
    
