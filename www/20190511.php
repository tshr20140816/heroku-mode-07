<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190511a($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190511a($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $cookie = tempnam('/tmp', md5(microtime(true)));
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
    ];
    
    $url = $mu_->get_env('URL_LOGGLY_USAGE');
    $res = $mu_->get_contents($url, $options);

    $rc = preg_match('/location: (.+)/i', $res, $match);

    $query = parse_url(trim($match[1]), PHP_URL_QUERY);
    
    $url = 'https://my.solarwinds.cloud/v1/login';
    
    $json = ['email' => $mu_->get_env('LOGGLY_ID', true),
             'loginQueryParams' => $query,
             'password' => $mu_->get_env('LOGGLY_PASSWORD', true),
            ];
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['content-type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($json),
    ];
    
    $res = $mu_->get_contents($url, $options);

    $url = json_decode($res)->redirectUrl;
    
    $options = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ];
    
    $res = $mu_->get_contents($url, $options);
    // error_log($log_prefix . $res);
    // error_log(print_r(json_decode($res)->total, true));
    
    foreach(json_decode($res)->total as $item) {
        error_log($log_prefix . date('m/d', $item[0] / 1000) . ' ' . round($item[1] / 1024 / 1024) . 'MB');
        $labels[] = date('m/d', $item[0] / 1000);
        $data[] = round($item[1] / 1024 / 1024);
    }
    array_pop($labels);
    array_pop($data);
    
    $data = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data,
                                        'fill' => false,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['display' => false,
                                       ],
                           'animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'horizontal',
                                                               'scaleID' => 'y-axis-0',
                                                               'value' => 200,
                                                               'borderColor' => 'red',
                                                               'borderWidth' => 1,
                                                              ],
                                                             ],
                                           ],
                          ],
            ];
    
    $url = 'https://quickchart.io/chart?width=900&height=480&c=' . json_encode($data);
    $res = $mu_->get_contents($url);

    header('Content-Type: image/png');
    echo $res;
}

function func_20190511b($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $livedoor_id = $mu_->get_env('LIVEDOOR_ID', true);
    $title = $mu_->get_env('TARGET_NAME_TITLE');
    $url = "http://blog.livedoor.jp/${livedoor_id}/search?q=" . str_replace(' ', '+', $title) . '+' . date('Y');
    $res = $mu_->get_contents($url);

    $rc = preg_match('/<div class="article-body-inner">(.+?)<\/div>/s', $res, $match);
    $base_record = trim(strip_tags($match[1]));
    error_log($log_prefix . $base_record);

    $name = $mu_->get_env('TARGET_NAME');
    $timestamp = strtotime('-13 hours');
    // $timestamp = mktime(0, 0, 0, 4, 21, 2019);

    if (strpos($base_record, date('Y/m/d', $timestamp)) != false) {
        return;
    }

    $ymd = date('Ymd', $timestamp);
    $url = 'https://baseball.yahoo.co.jp/npb/schedule/?date=' . $ymd;

    $res = $mu_->get_contents($url);

    $pattern = '<table border="0" cellspacing="0" cellpadding="0" class="teams">.+?';
    $pattern .= '<table border="0" cellspacing="0" cellpadding="0" class="score">.+?';
    $pattern .= '<a href="https:\/\/baseball.yahoo.co.jp\/npb\/game\/(\d+)\/".+?<\/table>.+?<\/table>';
    $rc = preg_match_all('/' . $pattern . '/s', $res, $matches, PREG_SET_ORDER);

    $url = '';
    foreach ($matches as $match) {
        if (strpos($match[0], '広島') != false) {
            $url = 'https://baseball.yahoo.co.jp/npb/game/' . $match[1] . '/stats';
            break;
        }
    }

    if ($url == '') {
        return;
    }
    $res = $mu_->get_contents($url);

    $description = '';
    foreach (explode('</table>', $res) as $data) {
        if (strpos($data, $name) != false) {
            $rc = preg_match_all('/<tr.*?>(.+?)<\/tr>/s', $data, $matches);
            foreach ($matches[1] as $item) {
                if (strpos($item, $name) != false) {
                    $tmp = str_replace("\n", '', $item);
                    $tmp = preg_replace('/<.+?>/s', ' ', $tmp);
                    $tmp = str_replace($name, '', $tmp);
                    $tmp = date('Y/m/d', $timestamp) . ' ' . trim(preg_replace('/ +/', ' ', $tmp));
                    $description = $tmp . "\n" . $base_record;
                    error_log($log_prefix . $description);
                    // $mu_->post_blog_wordpress($title, $description);
                    break 2;
                }
            }
        }
    }

    if ($description === '') {
        return;
    }

    $rc = preg_match_all('/(.+?) .+? (.+?) .+/', $description, $matches);
    $record_count = count($matches[0]);
    $labels = [];
    $data = [];
    $min_value = 1001;
    for ($i = 0; $i < $record_count; $i++) {
        error_log($log_prefix . $matches[1][$record_count - $i - 1] . ' ' . $matches[2][$record_count - $i - 1]);
        $labels[] = substr($matches[1][$record_count - $i - 1], 5);
        $data[] = $matches[2][$record_count - $i - 1] * 1000;
        if ($min_value > $data[$i]) {
            $min_value = $data[$i];
        }
    }

    $scales->yAxes[] = ['display' => true,
                        'bottom' => $min_value,
                       ];
    
    $data = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data,
                                        'fill' => false,
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['display' => false,
                                       ],
                           'animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                          ],
            ];
    $url = 'https://quickchart.io/chart?width=600&height=320&c=' . json_encode($data);
    $res = $mu_->get_contents($url);

    header('Content-Type: image/png');
    echo $res;
}
