<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

if (substr($_SERVER['HTTP_USER_AGENT'], 0, 4) === 'curl') {
    search_hotel($mu);
}
// search_jtb_tour($mu);
search_hotel_sancoinn($mu);

if (substr($_SERVER['HTTP_USER_AGENT'], 0, 4) === 'curl') {
    $url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/get_twitter_jaxa.php';
    exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

    $url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/lib_info.php?n=0';
    exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");
}
$time_finish = microtime(true);

$mu->post_blog_wordpress_async("${requesturi} [" . substr(($time_finish - $time_start), 0, 6) . 's]');
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');

function search_hotel($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $urls = [];
    for ($i = 0; $i < 20; $i++) {
        $url = $mu_->get_env('URL_RAKUTEN_TRAVEL_' . str_pad($i, 2, '0', STR_PAD_LEFT));
        if (strlen($url) < 10) {
            continue;
        }
        $urls[] = $url;
    }
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAXCONNECTS => 8,
    ];
    $results = $mu_->get_contents_proxy_multi($urls, $multi_options);

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
            $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
            $description = '<div class="' . $hash_url . '">' . "${hash_info}</div>${info}";
            $mu_->post_blog_wordpress($hash_url, $description, 'hotel');
        }
    }
    $results = null;
}

function search_hotel_sancoinn($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url_base = 'https://secure.reservation.jp/sanco-inn/stay_pc/rsv/rsv_src_pln.aspx?'
        . 'cond=or&dt_tbd=0&le=1&rc=1&pmin=0&ra=&pa=&cl_tbd=0&mc=2&rt=&st=0&pmax=2147483647&cc=&smc_id='
        . '&hi_id=__HI_ID__&dt=__DATE__&lang=ja-JP';
    $hash_url = 'url' . hash('sha512', $url_base);
    error_log($log_prefix . "url hash : ${hash_url}");

    $list_hotel = [];
    $list_hotel[] = '4';
    $list_hotel[] = '6';
    $list_hotel[] = '10';
    $list_hotel[] = '11';
    
    $list_date = [];
    $list_date[] = '2019/10/11';
    $list_date[] = '2019/10/12';
    // $list_date[] = '2020/07/30';
    // $list_date[] = '2020/07/31';
    // $list_date[] = '2020/08/09';
    // $list_date[] = '2020/09/09';
    $list_date[] = '2020/09/29';
    $list_date[] = '2020/09/30';
    $list_date[] = '2020/10/01';
    $list_date[] = '2020/10/09';
    $list_date[] = '2020/10/10';

    $urls = [];
    foreach ($list_date as $date) {
        foreach ($list_hotel as $hotel_id) {
            $url = str_replace('__HI_ID__', $hotel_id, $url_base);
            $url = str_replace('__DATE__', $date, $url);
            $urls[$url] = null;
        }
    }
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 100,
        CURLMOPT_MAXCONNECTS => 100,
    ];
    $results = $mu_->get_contents_multi($urls, null, $multi_options);

    $keyword = '誠に申し訳ございませんが、この検索条件に該当する空室・プランが見つかりませんでした。';

    $description = '';
    foreach ($list_date as $date) {
        foreach ($list_hotel as $hotel_id) {
            $url = str_replace('__HI_ID__', $hotel_id, $url_base);
            $url = str_replace('__DATE__', $date, $url);
            $res = $results[$url];

            $rc = preg_match('/<title>(.+?) /s', $res, $match);
            $description .= "\n" . $date . ' ' . trim($match[1]) . "\n\n";

            if (strpos($res, $keyword) === false) {
                $rc = preg_match_all('/<h2 class="strong c-bd02 side">(.+?)<\/h2>.+?&yen;(.+?)\n/s', $res, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $tmp = trim(str_replace("\r\n", '', strip_tags($match[1])));
                    $tmp = preg_replace('/ +/', ' ', $tmp) . ' ' . $match[2] . "\n";
                    // error_log($log_prefix . $tmp);
                    $description .= $tmp;
                }
            } else {
                // error_log($log_prefix . 'NONE');
                $description .= "NONE\n";
            }
        }
    }

    // error_log($description);
    $mu_->logging_object($description, $log_prefix);
    $hash_description = hash('sha512', $description);

    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_description) {
        $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
        $description = '<div class="' . $hash_url . '">' . "${hash_description}</div>${description}";
        $mu_->post_blog_wordpress($hash_url, $description, 'hotel');
    }
}

function search_jtb_tour($mu_)
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
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 50,
        CURLMOPT_MAXCONNECTS => 50,
    ];

    $pdo = $mu_->get_pdo();
    $statement_delete = $pdo->prepare($sql_delete);

    foreach ($urls_jtb as $url) {
        $res = $mu_->get_contents($url);

        $tmp = explode('<article class="', $res);
        array_shift($tmp);

        $urls = [];
        foreach ($tmp as $tour) {
            $rc = preg_match('/<h3 class="domtour-tour-list__name"><a .*?href=".+?\?(.+?)".*?>(.+?)</s', $tour, $match);
            array_shift($match);

            $plan_name = $match[1];

            $url = 'https://www.jtb.co.jp/kokunai_tour/spookserver?Command=TourShouhinListData&hotelsort=low&page=1&rating=5-4&'
                . str_replace('&amp;', '&', $match[0]);

            $rc = $statement_delete->execute([':b_url_base64' => base64_encode($url),]);

            $urls[$url] = null;
            if (count($urls) % 50 === 0) {
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
        $mu_->post_blog_wordpress_async($hash_url, $description, 'jtb');
    }
}
