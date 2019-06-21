<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = func_20190621($mu, '/tmp/20190621dummy');
@unkink('/tmp/20190621dummy');

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621($mu_, $file_name_blog_) {
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

    $pattern1 = '/<div id="area">.*?<p class="mark">(.*?)<.+?<span class="bstop_name" itemprop="name">(.*?)<.+? itemprop="alternateName">(.*?)</s';
    $pattern2 = '/<p class="time" itemprop="departureTime">\s+(.+?)\s.+?<span class="route">(.*?)<.+?itemprop="name">(.*?)<.+?<\/li>/s';
    foreach ($urls as $url) {
        $res = $mu_->get_contents($url, $options, true);

        error_log($log_prefix . $res);
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
    file_put_contents($file_name_blog_, "Bus Task Add : ${count_task}\n", FILE_APPEND);
    error_log($log_prefix . 'Tasks Bus : ' . print_r($list_add_task, true));
    return $list_add_task;
}
