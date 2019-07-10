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
    $urls[] = 'https://www.jtb.co.jp/kokunai_tour/list/1301/?departure=HIJ&capacity=2&godate=20190830&traveldays=2&room=1&transportation=2&samehm=1&toursort=low&&page=1&itemperpage=40';
    $urls[] = 'https://www.jtb.co.jp/kokunai_tour/list/130302/?departure=HIJ&capacity=2&godate=20190830&traveldays=2&room=1&transportation=2&samehm=1&toursort=low&page=1&itemperpage=40';
    $urls[] = 'https://www.jtb.co.jp/kokunai_tour/list/130301/?departure=HIJ&capacity=2&godate=20190830&traveldays=2&room=1&transportation=2&samehm=1&toursort=low&page=1&itemperpage=40';
    foreach ($urls as $url) {
        $res = $mu_->get_contents($url);

        $tmp = explode('<article class="', $res);

        for ($i = 0; $i < 5; $i++) {
            // error_log($tmp[$i + 1]);
            $rc = preg_match('/<h3 class="domtour-tour-list__name"><a .*?href=".+?\?(.+?)".*?>(.+?)</s', $tmp[$i + 1], $match);
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
                    $list_item[] = $item->shisetsu_name . ' '. number_format($item->min_price);
                }
            }
            if ($is_first === false) {
                $list_item[] = '';
            }
        }
    }
    error_log(print_r($list_item, true));
}
