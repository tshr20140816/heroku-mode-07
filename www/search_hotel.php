<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

search_hotel($mu);
// search_jtb_tour($mu);
search_jtb_tour2($mu);

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/get_twitter_jaxa.php';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/lib_info.php?n=0';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

$time_finish = microtime(true);

$mu->post_blog_wordpress_async("${requesturi} [" . substr(($time_finish - $time_start), 0, 6) . 's]');
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');

function search_hotel($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $urls = [];
    for ($i = 0; $i < 10; $i++) {
        $url = $mu_->get_env('URL_RAKUTEN_TRAVEL_0' . $i);
        if (strlen($url) < 10) {
            continue;
        }
        $urls[] = $url;
    }
    $results = $mu_->get_contents_proxy_multi($urls);

    foreach ($results as $url => $result) {
        $hash_url = 'url' . hash('sha512', $url);
        error_log($log_prefix . "url hash : ${hash_url}");

        parse_str(parse_url($url, PHP_URL_QUERY), $tmp);

        $y = $tmp['f_nen1'];
        $m = $tmp['f_tuki1'];
        $d = $tmp['f_hi1'];

        $info = "\n\n${y}/${m}/${d}\n";

        $tmp = explode('<dl class="htlGnrlInfo">', $result);
        array_shift($tmp);

        foreach ($tmp as $hotel_info) {
            $rc = preg_match('/<a id.+>(.+?)</', $hotel_info, $match);
            // error_log($match[1]);
            $info .= $match[1];
            $rc = preg_match('/<span class="vPrice".*?>(.+)/', $hotel_info, $match);
            // error_log(strip_tags($match[1]));
            $info .= ' ' . strip_tags($match[1]) . "\n";
        }

        $hash_info = hash('sha512', $info);
        error_log($log_prefix . "info hash : ${hash_info}");

        $res = $mu_->search_blog($hash_url);
        if ($res != $hash_info) {
            $description = '<div class="' . $hash_url . '">' . "${hash_info}</div>${info}";
            $mu_->post_blog_wordpress($hash_url, $description);
        }
    }
    $results = null;
}

function search_jtb_tour($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $hash_url = 'url' . hash('sha512', 'https://www.jtb.co.jp/');

    $list_item = [];
    $list_item[] = '';
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
            $rc = preg_match('/<h3 class="domtour-tour-list__name"><a .*?href=".+?\?(.+?)".*?>(.+?)</s', $tour, $match);
            array_shift($match);

            $plan_name = $match[1];

            $url = 'https://www.jtb.co.jp/kokunai_tour/spookserver?Command=TourShouhinListData&hotelsort=low&page=1&rating=5-4&'
                . str_replace('&amp;', '&', $match[0]);
            $res = $mu_->get_contents($url);

            $json = json_decode($res);

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
    error_log($log_prefix . print_r($list_item, true));

    $info = implode("\n", $list_item);

    $hash_info = hash('sha512', $info);
    error_log($log_prefix . "info hash : ${hash_info}");
    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_info) {
        $description = '<div class="' . $hash_url . '">' . "${hash_info}</div>${info}";
        $mu_->post_blog_wordpress_async($hash_url, $description);
    }
}

function search_jtb_tour2($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $hash_url = 'url' . hash('sha512', 'https://www.jtb.co.jp/');

    $sql_delete = <<< __HEREDOC__
DELETE
  FROM t_webcache
 WHERE url_base64 = :b_url_base64
__HEREDOC__;

    $list_item = [];
    $list_item[] = '';
    $limit = 30000;

    $urls_jtb = [];
    for ($i = 0; $i < 10; $i++) {
        $url = $mu_->get_env('URL_JTB_' . $i);
        if (strlen($url) < 10) {
            continue;
        }
        $urls_jtb[] = $url;
    }

    $multi_options = [
        CURLMOPT_PIPELINING => 5,
        CURLMOPT_MAX_HOST_CONNECTIONS => 5,
    ];

    $pdo = $mu_->get_pdo();
    $statement_delete = $pdo->prepare($sql_delete);

    foreach ($urls_jtb as $url) {
        $res = $mu_->get_contents($url);

        $tmp = explode('<article class="', $res);
        array_shift($tmp);

        $urls = [];
        $index = 0;
        foreach ($tmp as $tour) {
            $rc = preg_match('/<h3 class="domtour-tour-list__name"><a .*?href=".+?\?(.+?)".*?>(.+?)</s', $tour, $match);
            array_shift($match);

            $plan_name = $match[1];

            $url = 'https://www.jtb.co.jp/kokunai_tour/spookserver?Command=TourShouhinListData&hotelsort=low&page=1&rating=5-4&'
                . str_replace('&amp;', '&', $match[0]);

            $rc = $statement_delete->execute([':b_url_base64' => base64_encode($url),]);

            $urls[] = $url;
            $index++;
            if ($index == 5) {
                $dummy = $mu_->get_contents_multi([], $urls, $multi_options);
                $dummy = null;
                $urls = [];
            }
        }
        if (count($urls) > 0) {
            $dummy = $mu_->get_contents_multi([], $urls, $multi_options);
            $dummy = null;
            $urls = [];
        }

        foreach ($tmp as $tour) {
            $rc = preg_match('/<h3 class="domtour-tour-list__name"><a .*?href=".+?\?(.+?)".*?>(.+?)</s', $tour, $match);
            array_shift($match);

            $plan_name = $match[1];

            $url = 'https://www.jtb.co.jp/kokunai_tour/spookserver?Command=TourShouhinListData&hotelsort=low&page=1&rating=5-4&'
                . str_replace('&amp;', '&', $match[0]);
            $res = $mu_->get_contents($url, null, true);

            $json = json_decode($res);

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
    error_log($log_prefix . print_r($list_item, true));
    $pdo = null;

    $info = implode("\n", $list_item);

    $hash_info = hash('sha512', $info);
    error_log($log_prefix . "info hash : ${hash_info}");
    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_info) {
        $description = '<div class="' . $hash_url . '">' . "${hash_info}</div>${info}";
        $mu_->post_blog_wordpress_async($hash_url, $description);
    }
}
