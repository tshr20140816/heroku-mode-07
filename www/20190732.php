<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190732b($mu, '/tmp/dummy20190732');

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190732b($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $res = $mu_->get_contents('https://github.com/tshr20140816');

    $rc = preg_match_all('/<rect class="day" .+?data-count="(.+?)".*?data-date="(.+?)"/', $res, $matches, PREG_SET_ORDER);

    error_log(print_r($matches, true));

    $labels = [];
    $data1 = [];
    $data2 = [];
    $data3 = [];
    $data4 = [];
    foreach (array_slice($matches, -28) as $match) {
        if (date('w', strtotime($match[2])) == '0') {
            $tmp = new stdClass();
            $tmp->x = substr($match[2], -2);
            $tmp->y = 0;
            $data2[] = $tmp;
        } else if (date('w', strtotime($match[2])) == '6') {
            $tmp = new stdClass();
            $tmp->x = substr($match[2], -2);
            $tmp->y = 0;
            $data3[] = $tmp;
        }
        $tmp = new stdClass();
        $tmp->x = substr($match[2], -2);
        $tmp->y = (int)$match[1];
        $data1[] = $tmp;
        $labels[] = substr($match[2], -2);

        if (count($data4) == 0) {
            $data4[] = $tmp;
        } else {
            if ($data4[0]->y < $tmp->y) {
                $data4[0] = $tmp;
            }
        }
    }

    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                        'ticks' => ['beginAtZero' => true,
                                    'fontColor' => 'black',
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        'ticks' => ['beginAtZero' => true,
                                    'fontColor' => 'black',
                                   ],
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'ticks' => ['fontColor' => 'black',
                                    'autoSkip' => false,
                                   ],
                       ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data2,
                                        'fill' => false,
                                        'showLine' => false,
                                        'pointBackgroundColor' => 'red',
                                        'pointRadius' => 4,
                                       ],
                                       ['data' => $data3,
                                        'fill' => false,
                                        'showLine' => false,
                                        'pointBackgroundColor' => 'blue',
                                        'pointRadius' => 4,
                                       ],
                                       ['data' => $data1,
                                        'fill' => false,
                                        'lineTension' => 0,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                        'yAxisID' => 'y-axis-0',
                                       ],
                                       ['data' => $data4,
                                        'fill' => false,
                                        // 'lineTension' => 0,
                                        // 'pointBackgroundColor' => 'black',
                                        'pointRadius' => 0,
                                        'yAxisID' => 'y-axis-1',
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                          ],
            ];

    /*
    $url = 'https://quickchart.io/chart?width=600&height=320&c=' . urlencode(json_encode($data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 320 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);
    
    header('Content-Type: image/png');
    echo $res;
}

function func_20190732a($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $cookie = tempnam('/tmp', md5(microtime(true)));

    for ($i = 0; $i < 5; $i++) {
        $options = [
            CURLOPT_COOKIEJAR => $cookie,
            CURLOPT_COOKIEFILE => $cookie,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ];

        $url = $mu_->get_env('URL_LOGGLY_USAGE');
        $res = $mu_->get_contents($url, $options);

        $rc = preg_match('/location: (.+)/i', $res, $match);

        if ($rc != 1) {
            continue;
        }

        $url = 'https://my.solarwinds.cloud/v1/login';

        $json = ['email' => $mu_->get_env('LOGGLY_ID', true),
                 'loginQueryParams' => parse_url(trim($match[1]), PHP_URL_QUERY),
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

        if ($res == '500') {
            continue;
        }

        $url = json_decode($res)->redirectUrl;

        $options = [
            CURLOPT_COOKIEJAR => $cookie,
            CURLOPT_COOKIEFILE => $cookie,
        ];

        $res = $mu_->get_contents($url, $options);
        // error_log($log_prefix . print_r(json_decode($res)->total, true));

        if (strlen($res) > 3) {
            break;
        }
    }
    unlink($cookie);

    if (strlen($res) == 3) {
        return 0;
    }

    foreach (json_decode($res)->total as $item) {
        error_log($log_prefix . date('m/d', $item[0] / 1000) . ' ' . round($item[1] / 1024 / 1024) . 'MB');
        $labels[] = date('d', $item[0] / 1000);
        $data[] = round($item[1] / 1024 / 1024);
    }

    $scales = new stdClass();
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'ticks' => ['fontColor' => 'black',
                                    'autoSkip' => false,
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'ticks' => ['fontColor' => 'black',
                                   ],
                       ];
    
    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data,
                                        'fill' => false,
                                        'lineTension' => 0,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'horizontal',
                                                               'scaleID' => 'y-axis-0',
                                                               'value' => 200,
                                                               'borderColor' => 'red',
                                                               'borderWidth' => 1,
                                                              ],
                                                             ],
                                           ],
                           'scales' => $scales,
                          ],
            ];

    /*
    $url = 'https://quickchart.io/chart?width=600&height=320&c=' . urlencode(json_encode($data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file, true);

    unlink($file);
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 320 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);
    
    header('Content-Type: image/png');
    echo $res;
}
