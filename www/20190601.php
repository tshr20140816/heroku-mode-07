<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = func_20190601($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190601($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $list_item = [];
    $limit = 30000;
    
    $urls = [];
    for ($i = 0; $i < 10; $i++) {
        $url = $mu_->get_env('URL_JTB_' . $i);
        if (strlen($url) < 10) {
            continue;
        }
        $urls[] = $url;
    }

    foreach ($urls as $url) {
        $res = $mu_->get_contents($url);

        $tmp = explode('<article class="', $res);
        array_shift($tmp);

        foreach ($tmp as $tour) {
            // error_log($tour);
            $rc = preg_match('/<h3 class="domtour-tour-list__name"><a .*?href=".+?\?(.+?)".*?>(.+?)</s', $tour, $match);
            array_shift($match);
            // error_log(print_r($match, true));

            $plan_name = $match[1];

            $url = 'https://www.jtb.co.jp/kokunai_tour/spookserver?Command=TourShouhinListData&hotelsort=low&page=1&rating=5-4&'
                . str_replace('&amp;', '&', $match[0]);
            $res = $mu_->get_contents($url);

            // error_log($res);
            $json = json_decode($res);
            // error_log(print_r($json, true));
            
            $is_first = true;
            foreach ($json->tourShouhinList as $item) {
                // error_log($item->shisetsu_name . ' '. $item->min_price);
                if ($limit > (int)$item->min_price) {
                    if ($is_first) {
                        $list_item[] = $plan_name;
                        $list_item[] = '';
                        $is_first = false;
                    }
                    $list_item[] = number_format($item->min_price) . ' ' . $item->shisetsu_name;
                }
            }
            if ($is_first === false) {
                $list_item[] = '';
            }
        }
    }
    error_log(print_r($list_item, true));
}
