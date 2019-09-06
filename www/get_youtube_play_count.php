<?php
include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

get_youtube_play_count($mu);

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/get_results_batting.php';
$options = [
    CURLOPT_TIMEOUT => 3,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD'),
];
$mu->get_contents($url, $options);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function get_youtube_play_count($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = $mu_->get_env('URL_YOUTUBE');
    $url = str_replace('https://www.', 'https://m.', $url);
    $options = [CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 9; Pixel 3 Build/PQ1A.181105.013) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Mobile Safari/537.36'];
    $res = $mu_->get_contents($url, $options);
    
    $tmp = explode('<div id="initial-data"><!-- ', $res);
    $tmp = explode(' -->', $tmp[1]);
    
    $json = json_decode($tmp[0]);
    $playlist = [];
    foreach ($json->contents->singleColumnWatchNextResults->playlist->playlist->contents as $item) {
        $title = $item->playlistPanelVideoRenderer->title->runs[0]->text;
        $time = $item->playlistPanelVideoRenderer->lengthText->runs[0]->text;
        $url = 'https://www.youtube.com/watch?v=' . $item->playlistPanelVideoRenderer->videoId;
        
        $data['title'] = $title;
        // $data['time'] = $time;
        $playlist[$url] = $data;
    }
    
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 100,
        CURLMOPT_MAXCONNECTS => 100,
    ];

    for (;;) {
        $urls = [];
        foreach (array_keys($playlist) as $url) {
            if (array_key_exists('count', $playlist[$url])) {
                continue;
            }
            $urls[str_replace('https://www.', 'https://m.', $url)] = $options;
            if (count($urls) === $multi_options[CURLMOPT_MAX_HOST_CONNECTIONS]) {
                break;
            }        
        }

        if (count($urls) === 0) {
            break;
        }

        $list_contents = $mu_->get_contents_multi($urls, null, $multi_options);
        foreach (array_keys($list_contents) as $url_org) {
            $tmp = explode('ytInitialPlayerConfig = ', $list_contents[$url_org]);
            $tmp = explode('setTimeout(function() {', $tmp[2]);
            $json = json_decode(trim(trim($tmp[0]), ';'));
            $json = json_decode($json->args->player_response);
            
            $url = str_replace('https://m.', 'https://www.', $url_org);
            $data = $playlist[$url];
            $data['count'] = number_format($json->videoDetails->viewCount);
            $playlist[$url] = $data;
        }
        $list_contents = [];
    }

    error_log($log_prefix . print_r($playlist, true));

    $livedoor_id = $mu_->get_env('LIVEDOOR_ID', true);
    $url = "http://blog.livedoor.jp/${livedoor_id}/search?q=Play+Count";
    $res = $mu_->get_contents($url);

    $rc = preg_match('/<div class="article-body-inner">(.+?)<\/div>/s', $res, $match);
    $dic_previous_count = [];
    foreach (explode('<br />', str_replace("\n", '', trim($match[1]))) as $item) {
        if (strlen($item) == 0) {
            continue;
        }
        $tmp = strrev($item);
        $tmp = explode(' ', $tmp, 4);
        $dic_previous_count[strrev($tmp[3])] = strrev($tmp[0]);
    }

    $content = '';
    foreach (array_keys($playlist) as $url) {
        $data = $playlist[$url];
        if (array_key_exists($data['title'], $dic_previous_count)) {
            $content .= $data['title'] . ' ' . $dic_previous_count[$data['title']] . ' → ' . $data['count'] . "\n";
        } else {
            $content .= $data['title'] . ' 0 → ' . $data['count'] . "\n";
        }
    }
    error_log($log_prefix . $content);
    $mu_->post_blog_wordpress('Play Count', $content);
}
