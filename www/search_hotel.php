<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s') . ' ' . $_SERVER['HTTP_USER_AGENT']);

$mu = new MyUtils();

$is_curl = substr($_SERVER['HTTP_USER_AGENT'], 0, 4) === 'curl';

if ($is_curl === true) {
    search_hotel($mu);
}
// search_jtb_tour($mu);
search_hotel_sancoinn($mu);
search_hotel_grandcourt($mu);
search_hotel_greenhotels($mu);

if ($is_curl === true) {
    $url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/get_twitter_jaxa.php';
    exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

    $url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/lib_info.php?n=0';
    exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");
}
$time_finish = microtime(true);

if ($is_curl === true) {
    $mu->post_blog_wordpress_async("${requesturi} [" . substr(($time_finish - $time_start), 0, 6) . 's]');
}
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

        $hotels = [];
        foreach ($tmp as $hotel_info) {
            $rc = preg_match('/<a id.+>(.+?)</', $hotel_info, $match);
            // error_log($match[1]);
            $hotel_name = $match[1];
            $rc = preg_match('/<span class="vPrice".*?>合計(.+?)円/', $hotel_info, $match);
            // error_log(strip_tags($match[1]));
            $price = strip_tags($match[1]);
            $hotels[$price . ' ' . $hotel_name] = (int)str_replace(',', '', $price);
        }
        asort($hotels);
        $hotels = array_chunk($hotels, 28, true)[0];
        $info .= implode("\n", array_keys($hotels));
        // error_log($log_prefix. $info);

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

    $url_base = $mu_->get_env('URL_HOTEL_01')
        . '?cond=or&dt_tbd=0&le=1&rc=1&pmin=0&ra=&pa=&cl_tbd=0&mc=2&rt=&st=0&pmax=2147483647&cc=&smc_id='
        . '&hi_id=__HI_ID__&dt=__DATE__&lang=ja-JP';
    $hash_url = 'url' . hash('sha512', $url_base);
    error_log($log_prefix . "url hash : ${hash_url}");

    $list_hotel = [];
    $list_hotel[] = '4';
    $list_hotel[] = '6';
    $list_hotel[] = '10';
    $list_hotel[] = '11';

    $list_date = [];
    // $list_date[] = '2019/10/11';
    // $list_date[] = '2019/10/12';
    // $list_date[] = '2020/07/30';
    // $list_date[] = '2020/07/31';
    // $list_date[] = '2020/08/09';
    // $list_date[] = '2020/09/09';
    // $list_date[] = '2020/09/29';
    // $list_date[] = '2020/09/30';
    $list_date[] = '2020/10/01';
    $list_date[] = '2020/10/08';
    $list_date[] = '2020/10/09';
    $list_date[] = '2020/10/10';

    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 100,
        CURLMOPT_MAXCONNECTS => 100,
    ];

    $results = [];
    for ($i = 0; $i < 2; $i++) {
        $urls = [];
        foreach ($list_date as $date) {
            foreach ($list_hotel as $hotel_id) {
                $url = str_replace('__HI_ID__', $hotel_id, $url_base);
                $url = str_replace('__DATE__', $date, $url);
                if (array_key_exists($url, $results) === false) {
                    $urls[$url] = null;
                }
            }
        }
        if (count($urls) === 0) {
            break;
        }
        $results = array_merge($results, $mu_->get_contents_multi($urls, null, $multi_options));
    }

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
        $mu_->post_blog_wordpress($hash_url, $description, $mu_->to_next_word('sancoinn'));
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

function search_hotel_grandcourt($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url_base = $mu_->get_env('URL_HOTEL_02');
    $hash_url = 'url' . hash('sha512', $url_base);
    error_log($log_prefix . "url hash : ${hash_url}");

    $list_date = [];
    // $list_date[] = '2019/10/11';
    // $list_date[] = '2019/10/12';
    // $list_date[] = '2020/02/29';
    // $list_date[] = '2020/03/02';
    $list_date[] = '2020/03/15';
    $list_date[] = '2020/04/15';
    $list_date[] = '2020/05/15';
    $list_date[] = '2020/10/01';
    $list_date[] = '2020/10/08';
    $list_date[] = '2020/10/09';
    $list_date[] = '2020/10/10';

    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 8,
        CURLMOPT_MAXCONNECTS => 8,
    ];

    $post_data = [
        'yearmonth' => '2019-10',
        'day' => '',
        'stay_num' => '1',
        'room_num' => '1',
        'room_id' => '',
        'capacity' => '2',
        's_charge' => '0',
        'e_charge' => '0',
        'adult' => '2',
        'upper' => '0',
        'lower' => '0',
        'baby_meakandbed' => '0',
        'baby_meal' => '0',
        'baby_bed' => '0',
        'baby' => '0',
        'cat_search_con' => '0',
        'hotel_id' => '76',
        'detail' => 'off',
        'sp_id' => '',
        'viainn_card_flg' => '0',
        'member_id' => '',
        'stpoflg' => '1',
    ];
    $results = [];
    for ($i = 0; $i < 2; $i++) {
        $urls = [];
        foreach ($list_date as $date) {
            $tmp = explode('/', $date);
            $post_data['yearmonth'] = $tmp[0] . '-' . $tmp[1];
            $post_data['day'] = $tmp[2];
            $options = [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post_data),
            ];
            $url = $url_base . '?' . urlencode($date);
            if (array_key_exists($url, $results) === false) {
                $urls[$url_base . '?' . urlencode($date)] = $options;
            }
        }
        if (count($urls) === 0) {
            break;
        }
        $results = array_merge($results, $mu_->get_contents_multi($urls, null, $multi_options));
    }

    $description = '';
    foreach ($list_date as $date) {
        $description .= "\n${date}\n";
        $url = $url_base . '?' . urlencode($date);
        $res = $results[$url];
        $tmp = explode('</form>', $res);
        $tmp = explode('<table class="tbl02" cellpadding="0" cellspacing="0" border="0">', $tmp[1]);
        foreach ($tmp as $item) {
            $price = 99999;
            $rc = preg_match('/<span class="em">(.+?)</', $item, $match);
            if ($rc === 0) {
                continue;
            }
            $room_name = $match[1];
            $rc = preg_match_all('/<td style="border-bottom:1px dotted #cccccc;" align="center">￥(.+?) /', $item, $matches);
            foreach ($matches[1] as $item) {
                $item = str_replace(',', '', $item);
                if ((int)$item < $price) {
                    $price = (int)$item;
                }
            }
            $description .= $room_name . ' ' . number_format($price) . "\n";
        }
    }
    $mu_->logging_object($description, $log_prefix);
    $hash_description = hash('sha512', $description);
    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_description) {
        $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
        $description = '<div class="' . $hash_url . '">' . "${hash_description}</div>${description}";
        $mu_->post_blog_wordpress($hash_url, $description, $mu_->to_next_word('grandcourt'));
    }
}

function search_hotel_greenhotels($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url_base = $mu_->get_env('URL_HOTEL_03');
    $hash_url = 'url' . hash('sha512', $url_base);
    error_log($log_prefix . "url hash : ${hash_url}");

    $list_hotel = [];
    $list_hotel[] = '736';
    $list_hotel[] = '9211';

    $list_date = [];
    // $list_date[] = '2019/10/11';
    // $list_date[] = '2019/10/12';
    // $list_date[] = '2020/02/29';
    // $list_date[] = '2020/03/02';
    $list_date[] = '2020/03/15';
    $list_date[] = '2020/04/15';
    $list_date[] = '2020/05/15';
    $list_date[] = '2020/10/01';
    $list_date[] = '2020/10/08';
    $list_date[] = '2020/10/09';
    $list_date[] = '2020/10/10';
    
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 100,
        CURLMOPT_MAXCONNECTS => 100,
    ];

    $results = [];
    for ($i = 0; $i < 2; $i++) {
        $urls = [];
        foreach ($list_date as $date) {
            foreach ($list_hotel as $hotel_no) {
                $target_date = strtotime($date);
                $target_next_date = strtotime('+1day', $target_date);

                $url = str_replace('__HOTEL_NO__', $hotel_no, $url_base);
                $url = str_replace('__YEAR1__', date('Y', $target_date), $url);
                $url = str_replace('__MONTH1__', date('m', $target_date), $url);
                $url = str_replace('__DAY1__', date('d', $target_date), $url);
                $url = str_replace('__YEAR2__', date('Y', $target_next_date), $url);
                $url = str_replace('__MONTH2__', date('m', $target_next_date), $url);
                $url = str_replace('__DAY2__', date('d', $target_next_date), $url);
                // error_log($log_prefix . $url);
                if (array_key_exists($url, $results) === false) {
                    $urls[$url] = null;
                }
            }
        }
        if (count($urls) === 0) {
            break;
        }
        $results = array_merge($results, $mu_->get_contents_multi($urls, null, $multi_options));
    }

    $description = '';
    foreach ($list_date as $date) {
        foreach ($list_hotel as $hotel_no) {
            $target_date = strtotime($date);
            $target_next_date = strtotime('+1day', $target_date);

            $url = str_replace('__HOTEL_NO__', $hotel_no, $url_base);
            $url = str_replace('__YEAR1__', date('Y', $target_date), $url);
            $url = str_replace('__MONTH1__', date('m', $target_date), $url);
            $url = str_replace('__DAY1__', date('d', $target_date), $url);
            $url = str_replace('__YEAR2__', date('Y', $target_next_date), $url);
            $url = str_replace('__MONTH2__', date('m', $target_next_date), $url);
            $url = str_replace('__DAY2__', date('d', $target_next_date), $url);
            $res = $results[$url];
            $rc = preg_match('/<h1>(.+?)－/', $res, $match);
            $description .= "\n${date} " . $match[1] . "\n";

            $tmp = explode('<dd class="planName">', $res);
            foreach ($tmp as $item) {
                $rc = preg_match('/<strong>(.+?)<.+?<B>(.+?)<.+?<td class="totalCharge">(.+?)</s', $item, $match);
                if ($rc === false) {
                    continue;
                }
                $description .= trim($match[3]) . ' ' . trim($match[2]) . ' ' . trim($match[1]) . "\n";
            }
        }
    }
    $mu_->logging_object($description, $log_prefix);
    $hash_description = hash('sha512', $description);

    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_description) {
        $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
        $description = '<div class="' . $hash_url . '">' . "${hash_description}</div>${description}";
        $mu_->post_blog_wordpress($hash_url, $description, $mu_->to_next_word('greenhotels'));
    }
}
