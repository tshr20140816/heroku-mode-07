<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190732g($mu, '/tmp/dummy20190732');

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190732g($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $results = [];
    $dic_results = [];
    $ymd = '';
    for ($i = 3; $i < 11; $i++) {
        $url = 'http://npb.jp/games/2019/schedule_' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_detail.html';
        $res = $mu_->get_contents($url, null, true);

        $tmp = explode('<tr id="date', $res);
        foreach ($tmp as $item) {
            // error_log(substr($item, 0, 4));
            $rc = preg_match('/<div class="team1">(.+?)<.+?<div class="score1">(\d+)<.+?<div class="score2">(\d+)<.+?<div class="team2">(.+?)</s', $item, $match);
            // error_log(print_r($match, true));
            if ($rc === 1) {
                $ymd = '2019' . substr($item, 0, 4);
                $results[] = substr($item, 0, 4) . ' ' . $match[1] . ' ' . $match[2] . ' - ' . $match[3] . ' ' . $match[4];
                if (array_key_exists($match[1], $dic_results) === false) {
                    $dic_results[$match[1]]['win'] = 0;
                    $dic_results[$match[1]]['lose'] = 0;
                    $dic_results[$match[1]]['draw'] = 0;
                }
                if (array_key_exists($match[4], $dic_results) === false) {
                    $dic_results[$match[4]]['win'] = 0;
                    $dic_results[$match[4]]['lose'] = 0;
                    $dic_results[$match[4]]['draw'] = 0;
                }
                if ((int)$match[2] > (int)$match[3]) {
                    $dic_results[$match[1]]['win']++;
                    $dic_results[$match[4]]['lose']++;
                } else if ((int)$match[2] < (int)$match[3]) {
                    $dic_results[$match[1]]['lose']++;
                    $dic_results[$match[4]]['win']++;
                } else {
                    $dic_results[$match[1]]['draw']++;
                    $dic_results[$match[4]]['draw']++;
                }
            }
        }
    }
    error_log(print_r($results, true));
    error_log(print_r($dic_results, true));
}

function func_20190732f($mu_, $file_name_rss_items_, $pattern_ = 1)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    for ($i = 0; $i < (int)date('t'); $i++) {
        $labels[] = $i + 1;
    }

    $datasets = [];

    switch ($pattern_) {
        case 1:
            $list = [['target' => 'toodledo',
                      'color' => 'green',
                      'size_color' => 'red',
                     ],
                     ['target' => 'redmine',
                      'color' => 'blue',
                      'size_color' => 'yellow',
                     ],
                     ['target' => 'ttrss',
                      'color' => 'deepskyblue',
                      'size_color' => 'orange',
                     ],
                    ];
            break;
        case 2:
            $list = [['target' => 'ttrss',
                      'color' => 'deepskyblue',
                      'size_color' => 'orange',
                     ],
                    ];
            break;
    }

    $annotations = [];
    $level = 10000;
    foreach ($list as $one_data) {
        error_log(print_r($one_data, true));
        $keyword = strtolower($one_data['target']);
        for ($i = 0; $i < strlen($keyword); $i++) {
            $keyword[$i] = chr(ord($keyword[$i]) + 1);
        }

        $res = $mu_->search_blog($keyword . 'sfdpsedpvou');

        $data2 = [];
        foreach (explode(' ', $res) as $item) {
            $tmp1 = explode(',', $item);
            $tmp2 = new stdClass();
            $tmp2->x = (int)$tmp1[0];
            $tmp2->y = (int)$tmp1[1];
            $data2[] = $tmp2;
        }

        if (count($data2) < 2) {
            return 0;
        }

        $level -= 1000;
        $annotations[] = ['type' => 'line',
                          'mode' => 'horizontal',
                          'scaleID' => 'y-axis-0',
                          'value' => $level,
                          'borderColor' => 'rgba(0,0,0,0)',
                          // 'borderWidth' => 1,
                          'label' => ['enabled' => true,
                                      'content' => number_format(end($data2)->y),
                                      'position' => 'left',
                                      'backgroundColor' => $one_data['color'],
                                     ],
                         ];

        $datasets[] = ['data' => $data2,
                       'fill' => false,
                       'lineTension' => 0,
                       'pointStyle' => 'circle',
                       'backgroundColor' => $one_data['color'],
                       'borderColor' => $one_data['color'],
                       'borderWidth' => 3,
                       'pointRadius' => 4,
                       'pointBorderWidth' => 0,
                       'label' => $one_data['target'] . ' record',
                       'yAxisID' => 'y-axis-0',
                      ];

        $res = $mu_->search_blog($keyword . 'ebubcbtftjaf');

        $data3 = [];
        foreach (explode(' ', $res) as $item) {
            $tmp1 = explode(',', $item);
            $tmp2 = new stdClass();
            $tmp2->x = (int)$tmp1[0];
            $tmp2->y = ceil((int)$tmp1[1] / 1024 / 1024);
            $data3[] = $tmp2;
        }

        $annotations[] = ['type' => 'line',
                          'mode' => 'horizontal',
                          'scaleID' => 'y-axis-0',
                          'value' => $level,
                          'borderColor' => 'rgba(0,0,0,0)',
                          // 'borderWidth' => 1,
                          'label' => ['enabled' => true,
                                      'content' => number_format(end($data3)->y),
                                      'position' => 'right',
                                      'backgroundColor' => $one_data['size_color'],
                                      'fontColor' => 'black',
                                     ],
                         ];

        $datasets[] = ['data' => $data3,
                       'fill' => false,
                       'lineTension' => 0,
                       'pointStyle' => 'star',
                       'backgroundColor' => $one_data['size_color'],
                       'borderColor' => $one_data['size_color'],
                       'borderWidth' => 2,
                       'pointRadius' => 3,
                       'pointBorderWidth' => 0,
                       'label' => 'size',
                       'yAxisID' => 'y-axis-1',
                      ];
    }

    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                        'ticks' => ['callback' => 'function(value){return value.toLocaleString();}',],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        'ticks' => ['callback' => "function(value){return value.toLocaleString() + 'MB';}",],
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'ticks' => ['autoSkip' => false,
                                   ],
                       ];

    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => 0,
                      'borderColor' => 'rgba(0,0,0,0)',
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => 10000,
                      'borderColor' => 'red',
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-1',
                      'value' => 0,
                      'borderColor' => 'rgba(0,0,0,0)',
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-1',
                      'value' => 1000,
                      'borderColor' => 'rgba(0,0,0,0)',
                     ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['labels' => ['usePointStyle' => true,
                                                     'fontColor' => 'black',
                                                    ],
                                       ],
                           'animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                           'annotation' => ['annotations' => $annotations,
                                           ],
                          ],
            ];

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 360 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);
    
    header('Content-Type: image/png');
    echo $res;
}

function func_20190732e($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $sql = <<< __HEREDOC__
SELECT to_char(T1.check_time, 'YYYY/MM/DD') check_date
      ,MIN(T1.balance) balance
  FROM t_waon_history T1
 GROUP BY to_char(T1.check_time, 'YYYY/MM/DD')
 ORDER BY to_char(T1.check_time, 'YYYY/MM/DD') DESC
 LIMIT 20
;
__HEREDOC__;

    $pdo = $mu_->get_pdo();

    $labels = [];
    $data1 = [];
    foreach ($pdo->query($sql) as $row) {
        $labels[$row['check_date']] = date('m/d', strtotime($row['check_date']));
        $tmp = new stdClass();
        $tmp->x = date('m/d', strtotime($row['check_date']));
        $tmp->y = $row['balance'];
        $data1[] = $tmp;
    }
    $pdo = null;

    ksort($labels);
    $labels = array_values($labels);

    $datasets = [];

    $datasets[] = ['data' => $data1,
                   'fill' => false,
                   'lineTension' => 0,
                   'pointStyle' => 'circle',
                   'backgroundColor' => 'deepskyblue',
                   'borderColor' => 'deepskyblue',
                   'borderWidth' => 3,
                   'pointRadius' => 4,
                   'pointBorderWidth' => 0,
                  ];

    $scales = new stdClass();
    $scales->yAxes[] = ['display' => true,
                        'ticks' => ['callback' => 'function(value){return value.toLocaleString();}',],
                       ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['display' => false,
                                       ],
                           'animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'horizontal',
                                                               'scaleID' => 'y-axis-0',
                                                               'value' => $data1[0]->y,
                                                               'borderColor' => 'rgba(0,0,0,0)',
                                                               'borderWidth' => 1,
                                                               'label' => ['enabled' => true,
                                                                           'content' => number_format($data1[0]->y),
                                                                           'position' => 'left',
                                                                          ],
                                                              ],
                                                             ],
                                           ],
                           'scales' => $scales,
                          ],
            ];

    // $json = str_replace('"__CALLBACK__"', '"function(value){return value.toLocaleString();}"', json_encode($json));
    // error_log($json);

    /*
    $url = 'https://quickchart.io/chart?width=600&height=360&c=' . urlencode($tmp);
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

function func_20190732d($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $sql = <<< __HEREDOC__
SELECT T1.yyyymmdd
      ,T1.post_count
  FROM t_blog_post T1
 WHERE T1.blog_site = 'hatena'
 ORDER BY T1.yyyymmdd DESC
 LIMIT 25
;
__HEREDOC__;

    $pdo = $mu_->get_pdo();

    $labels = [];
    $data1 = [];
    foreach ($pdo->query($sql) as $row) {
        $labels[$row['yyyymmdd']] = substr($row['yyyymmdd'], -2);
        $tmp = new stdClass();
        $tmp->x = substr($row['yyyymmdd'], -2);
        $tmp->y = (int)$row['post_count'];
        $data1[] = $tmp;
    }
    $pdo = null;

    ksort($labels);
    $labels = array_values($labels);

    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                        'ticks' => ['beginAtZero' => true,
                                    // 'max' => 100,
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        'ticks' => ['beginAtZero' => true,
                                    // 'max' => 100,
                                   ],
                       ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data1,
                                        'fill' => false,
                                        'lineTension' => 0,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                        'yAxisID' => 'y-axis-0',
                                       ],
                                       ['data' => $data1,
                                        'fill' => false,
                                        'lineTension' => 0,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
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

function func_20190732c($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://' . $mu_->get_env('WORDPRESS_USERNAME', true) . '.wordpress.com/?s=daily020.php';
    $res = $mu_->get_contents($url);

    $rc = preg_match_all('/rel="bookmark">.+?\/(.+?) .+? \/daily020\.php&nbsp;\[(.+?)s\]/', $res, $matches, PREG_SET_ORDER);
    // error_log(print_r($matches, true));

    $labels = [];
    $data = [];
    foreach ($matches as $match) {
        $labels[] = $match[1];
        $tmp = new stdClass();
        $tmp->x = $match[1];
        $tmp->y = $match[2];
        $data[] = $tmp;
    }
    $labels = array_reverse($labels);

    $datasets[] = ['data' => $data,
                   'fill' => false,
                   'lineTension' => 0,
                   'pointStyle' => 'circle',
                   'backgroundColor' => 'black',
                   'borderColor' => 'black',
                   'borderWidth' => 3,
                   'pointRadius' => 4,
                   'pointBorderWidth' => 0,
                   'label' => 'daily020',
                  ];

    $url = 'https://' . $mu_->get_env('WORDPRESS_USERNAME', true) . '.wordpress.com/?s=make_graph.php';
    $res = $mu_->get_contents($url);

    $rc = preg_match_all('/rel="bookmark">.+?\/(.+?) .+? \/make_graph\.php&nbsp;\[(.+?)s\]/', $res, $matches, PREG_SET_ORDER);

    $data = [];
    foreach ($matches as $match) {
        $tmp = new stdClass();
        $tmp->x = $match[1];
        if (in_array($tmp->x, $labels)) {
            $tmp->y = $match[2];
            $data[] = $tmp;
        }
    }

    $datasets[] = ['data' => $data,
                   'fill' => false,
                   'lineTension' => 0,
                   'pointStyle' => 'circle',
                   'backgroundColor' => 'red',
                   'borderColor' => 'red',
                   'borderWidth' => 3,
                   'pointRadius' => 4,
                   'pointBorderWidth' => 0,
                   'label' => 'make_graph',
                  ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                          ],
            ];

    /*
    $url = 'https://quickchart.io/chart?w=600&h=360&c=' . urlencode(json_encode($chart_data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);

    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 360 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);
    
    header('Content-Type: image/png');
    echo $res;
}

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
