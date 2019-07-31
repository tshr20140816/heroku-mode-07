<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = func20190731($mu);
$rc = func20190731_2($mu, $file_name_rss_items, $mu->get_env('URL_RIVER_YAHOO_1'), $mu->get_env('RIVER_POINT_1'));

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func20190731_2($mu_, $file_name_rss_items_, $url_, $point_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

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

    $url = $url_;
    $res = $mu_->get_contents($url, $options);

    $rc = preg_match("/common.riverData = JSON.parse\('(.+)'/", $res, $match);
    $json = json_decode($match[1], true);
    error_log($log_prefix . print_r($json, true));
    $title = $json['RiverName'];

    $rc = preg_match("/common.obsData = JSON.parse\('(.+)'/", $res, $match);
    $json = json_decode($match[1], true);
    error_log($log_prefix . print_r($json, true));

    $target = null;
    foreach ($json as $item) {
        if ($item['ObsrvtnName'] == $point_) {
            $target = $item;
            break;
        }
    }

    $title .= ' ' . $target['ObsrvtnName'] . ' ' . $target['ObsrvtnTime'];

    $annotations = [];

    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => $target['WaterValue'],
                      'borderColor' => 'rgba(0,0,0,0)',
                      'label' => ['enabled' => true,
                                  'content' => $target['WaterValue'],
                                  'position' => 'center',
                                  'backgroundColor' => 'cyan',
                                  'fontColor' => 'black',
                                 ],
                     ];

    if ($target['StageGnl'] != '') {
        $annotations[] = ['type' => 'line',
                          'mode' => 'horizontal',
                          'scaleID' => 'y-axis-0',
                          'value' => $target['StageGnl'],
                          'borderColor' => 'rgba(0,0,0,0)',
                          'label' => ['enabled' => true,
                                      'content' => $target['StageGnl'],
                                      'position' => 'right',
                                      'backgroundColor' => 'green',
                                      'fontColor' => 'black',
                                     ],
                         ];
    }

    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => $target['StageWarn'],
                      'borderColor' => 'rgba(0,0,0,0)',
                      'label' => ['enabled' => true,
                                  'content' => $target['StageWarn'],
                                  'position' => 'left',
                                  'backgroundColor' => 'yellow',
                                  'fontColor' => 'black',
                                 ],
                     ];

    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => $target['StageSpcl'],
                      'borderColor' => 'rgba(0,0,0,0)',
                      'label' => ['enabled' => true,
                                  'content' => $target['StageSpcl'],
                                  'position' => 'center',
                                  'backgroundColor' => 'orange',
                                  'fontColor' => 'black',
                                 ],
                     ];

    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => $target['StageDng'],
                      'borderColor' => 'rgba(0,0,0,0)',
                      'label' => ['enabled' => true,
                                  'content' => $target['StageDng'],
                                  'position' => 'right',
                                  'backgroundColor' => 'red',
                                  'fontColor' => 'black',
                                 ],
                     ];

    $data1 = [];
    $data1[] = $target['WaterValue'];
    $data1[] = $target['WaterValue'];

    $data2 = [];
    $data2[] = $target['WaterValue'] + 10.0;
    $data2[] = $target['WaterValue'] + 10.0;

    $data3 = [];
    if ($target['StageGnl'] != '') {
        $data3[] = $target['StageGnl'];
        $data3[] = $target['StageGnl'];
    }

    $data4 = [];
    $data4[] = $target['StageWarn'];
    $data4[] = $target['StageWarn'];

    $data5 = [];
    $data5[] = $target['StageSpcl'];
    $data5[] = $target['StageSpcl'];

    $data6 = [];
    $data6[] = $target['StageDng'];
    $data6[] = $target['StageDng'];

    $data7 = [];
    $data7[] = $target['StageDng'] + 10.0;
    $data7[] = $target['StageDng'] + 10.0;

    $datasets = [];
    if (count($data3) > 0) {
        $datasets[] = ['data' => $data3,
                       'fill' => false,
                       'pointStyle' => 'line',
                       'backgroundColor' => 'green',
                       'borderColor' => 'green',
                       'borderWidth' => 1,
                       'pointRadius' => 0,
                       'yAxisID' => 'y-axis-0',
                      ];
    }
    $datasets[] = ['data' => $data4,
                   'fill' => false,
                   'pointStyle' => 'line',
                   'backgroundColor' => 'yellow',
                   'borderColor' => 'yellow',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'yAxisID' => 'y-axis-0',
                  ];
    $datasets[] = ['data' => $data5,
                   'fill' => false,
                   'pointStyle' => 'line',
                   'backgroundColor' => 'orange',
                   'borderColor' => 'orange',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'yAxisID' => 'y-axis-0',
                  ];
    $datasets[] = ['data' => $data6,
                   'fill' => false,
                   'pointStyle' => 'line',
                   'backgroundColor' => 'red',
                   'borderColor' => 'red',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'yAxisID' => 'y-axis-0',
                  ];
    $datasets[] = ['data' => $data7,
                   'fill' => false,
                   'pointStyle' => 'line',
                   'backgroundColor' => 'blue',
                   'borderColor' => 'blue',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'yAxisID' => 'y-axis-1',
                  ];
    $datasets[] = ['data' => $data1,
                   'fill' => false,
                   'pointStyle' => 'line',
                   'backgroundColor' => 'cyan',
                   'borderColor' => 'cyan',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'yAxisID' => 'y-axis-0',
                  ];
    $datasets[] = ['data' => $data2,
                   'fill' => true,
                   'pointStyle' => 'line',
                   'backgroundColor' => 'cyan',
                   'borderColor' => 'cyan',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'yAxisID' => 'y-axis-1',
                  ];

    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => false,
                       ];

    $json = ['type' => 'line',
             'data' => ['datasets' => $datasets,
                       ],
             'options' => ['legend' => ['display' => false,
                                       ],
                           'scales' => $scales,
                           'title' => ['display' => true,
                                       'text' => $title,
                                       'fontColor' => 'black',
                                      ],
                           'annotation' => ['annotations' => $annotations,
                                           ],
                          ],
            ];

    // $url = 'https://quickchart.io/chart?width=300&height=160&c=' . urlencode(json_encode($chart_data));
    // $res = $mu_->get_contents($url);
    
    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 300 160 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);
    
    header('Content-Type: image/png');
    echo $res;
}

function func20190731($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    $res_sanyo2_st = $mu_->get_contents($url, null, true);

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json?' . microtime(true);
    $res_sanyo2 = $mu_->get_contents($url);
    $json = json_decode($res_sanyo2, true);

    $res_kudari = get_train_sanyo2_image3x($mu_, $res_sanyo2_st, $res_sanyo2, '1');
    $res_nobori = get_train_sanyo2_image3x($mu_, $res_sanyo2_st, $res_sanyo2, '0');
    
    $im1 = imagecreatefromstring($res_kudari);
    $x = imagesx($im1);
    $y1 = imagesy($im1);
    imagedestroy($im1);

    $im1 = imagecreatefromstring($res_nobori);
    // $x = imagesx($im1);
    $y2 = imagesy($im1);
    imagedestroy($im1);

    $im1 = imagecreatetruecolor($x, $y1 + $y2);
    imagefill($im1, 0, 0, imagecolorallocate($im1, 255, 255, 255));

    $im2 = imagecreatefromstring($res_kudari);
    imagecopy($im1, $im2, 0, 0, 0, 0, $x, $y1);
    imagedestroy($im2);

    $im2 = imagecreatefromstring($res_nobori);
    imagecopy($im1, $im2, 0, $y1, 0, 0, $x, $y2);
    imagedestroy($im2);

    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im1, $file, 9);
    imagedestroy($im1);
    $res = file_get_contents($file);
    unlink($file);

    header('Content-Type: image/png');
    echo $res;
}
    
function get_train_sanyo2_image3x($mu_, $sanyo2_st_, $sanyo2_, $direction_ = '0') // $direction_ : '0' nobori / '1' kudari
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $stations = [];
    $index = 0;
    $labels['station'] = [];
    foreach (json_decode($sanyo2_st_, true)['stations'] as $station) {
        $stations[$station['info']['code']]['name'] = $station['info']['name'];
        $stations[$station['info']['code']]['index'] = $index;
        $index += 2;

        $labels['station'][] = '';
        $labels['station'][] = $station['info']['name'];
    }
    array_shift($labels['station']);

    $labels['real'] = [];
    $labels['dest'] = [];
    for ($i = 0; $i < count($labels['station']); $i++) {
        $labels['real'][] = (string)$i;
        $labels['dest'][] = '';
    }

    // error_log($log_prefix . print_r($labels, true));
    // error_log($log_prefix . print_r($stations, true));

    $json = json_decode($sanyo2_, true);

    $data = [];
    $data['ontime'] = [];
    $data['delay'] = [];
    $data['ontime_etc'] = [];
    $data['delay_etc'] = [];
    $y_max = 0;
    foreach ($json['trains'] as $train) {
        if ($train['direction'] == $direction_) {
            $tmp = new stdClass();
            $pos = explode('_', $train['pos']);
            if ($pos[1] === '####') {
                $tmp->x = (string)$stations[$pos[0]]['index'];
            } else {
                $tmp->x = (string)($stations[$pos[0]]['index'] + ($direction_ === '0' ? -1 : 1));
            }
            $y = 1;
            foreach ($data['ontime'] as $std) {
                if ($std->x === $tmp->x && $std->y >= $y) {
                    $y = $std->y + 1;
                }
            }
            foreach ($data['delay'] as $std) {
                if ($std->x === $tmp->x && $std->y >= $y) {
                    $y = $std->y + 1;
                }
            }
            foreach ($data['ontime_etc'] as $std) {
                if ($std->x === $tmp->x && $std->y >= $y) {
                    $y = $std->y + 1;
                }
            }
            foreach ($data['delay_etc'] as $std) {
                if ($std->x === $tmp->x && $std->y >= $y) {
                    $y = $std->y + 1;
                }
            }
            $tmp->y = $y;
            if ($y > $y_max) {
                $y_max = $y;
            }
            $dest = $train['dest'];
            if ($dest === ($direction_ === '0' ? '糸崎' : '岩国')) {
                $dest = '★';
            }
            if ((int)$tmp->x === 0) {
                $dest = str_repeat('　', mb_strlen($dest)) . $dest;
            } else if ((int)$tmp->x === (count($labels['station']) - 1)) {
                $dest .= str_repeat('　', mb_strlen($dest));
            }
            if ($train['delayMinutes'] != '0') {
                if ($train['notice'] != '' || $train['displayType'] != '普通') {
                    $data['delay_etc'][] = $tmp;
                } else {
                    $data['delay'][] = $tmp;
                }
                // $labels['dest'][(int)$tmp->x] .= "\n" . $dest . $train['delayMinutes'];
                $labels['dest'][(int)$tmp->x] = $dest . $train['delayMinutes'] . "\n" . $labels['dest'][(int)$tmp->x];
            } else {
                if ($train['notice'] != '' || $train['displayType'] != '普通') {
                    $data['ontime_etc'][] = $tmp;
                } else {
                    $data['ontime'][] = $tmp;
                }
                // $labels['dest'][(int)$tmp->x] .= "\n" . $dest;
                $labels['dest'][(int)$tmp->x] = $dest . "\n" . $labels['dest'][(int)$tmp->x];
            }
        }
    }
    // error_log($log_prefix . print_r($data, true));
    // error_log($log_prefix . print_r($labels, true));

    $pointRotation = $direction_ === '0' ? 270 : 90;

    $datasets[] = ['data' => $data['ontime'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 8,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'lightgray',
                   'pointBorderColor' => 'red',
                   'pointBorderWidth' => 2,
                  ];

    if (count($data['delay']) > 0) {
        $datasets[] = ['data' => $data['delay'],
                       'fill' => false,
                       'showLine' => false,
                       'xAxisID' => 'x-axis-0',
                       'showLine' => false,
                       'pointStyle' => 'triangle',
                       'pointRadius' => 8,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => 'lightgray',
                       'pointBorderColor' => 'cyan',
                       'pointBorderWidth' => 2,
                      ];
    }

    if (count($data['ontime_etc']) > 0) {
        $datasets[] = ['data' => $data['ontime_etc'],
                       'fill' => false,
                       'showLine' => false,
                       'xAxisID' => 'x-axis-0',
                       'showLine' => false,
                       'pointStyle' => 'triangle',
                       'pointRadius' => 8,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => 'yellow',
                       'pointBorderColor' => 'red',
                       'pointBorderWidth' => 2,
                      ];
    }

    if (count($data['delay_etc']) > 0) {
        $datasets[] = ['data' => $data['delay_etc'],
                       'fill' => false,
                       'showLine' => false,
                       'xAxisID' => 'x-axis-0',
                       'showLine' => false,
                       'pointStyle' => 'triangle',
                       'pointRadius' => 8,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => 'yellow',
                       'pointBorderColor' => 'cyan',
                       'pointBorderWidth' => 2,
                      ];
    }

    $tmp = new stdClass();
    $tmp->x = $direction_ === '0' ? '糸崎' : '岩国';
    $tmp->y = 0;

    $datasets[] = ['data' => [$tmp, ],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-1',
                   'showLine' => false,
                   'pointStyle' => 'circle',
                   'pointRadius' => 3,
                   'pointBackgroundColor' => 'black',
                   'pointBorderColor' => 'black',
                  ];

    $scales = new stdClass();
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'display' => false,
                        'labels' => $labels['real'],
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-1',
                        // 'display' => true,
                        'labels' => $labels['station'],
                        'ticks' => ['fontColor' => 'black',
                                    'fontSize' => 10,
                                    'autoSkip' => false,
                                    'minRotation' => 45,
                                    'maxRotation' => 45,
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => false,
                        'ticks' => ['max' => $y_max + 1,
                                    'min' => 0,
                                   ],
                       ];

    $annotations = [];
    for ($i = 0; $i < count($labels['dest']); $i++) {
        if ($labels['dest'][$i] !== '') {
            $tmp = explode("\n", trim($labels['dest'][$i], "\n"), 2);
            $annotations[] = ['type' => 'line',
                              'mode' => 'vertical',
                              'scaleID' => 'x-axis-0',
                              'value' => (string)$i,
                              'borderColor' => 'rgba(0,0,0,0)',
                              'label' => ['enabled' => true,
                                          'content' => $tmp[0],
                                          'position' => 'bottom',
                                          'backgroundColor' => 'rgba(0,0,0,0)',
                                          'fontColor' => 'black',
                                          'fontFamily' => 'IPAexGothic',
                                          'fontStyle' => 'normal',
                                          'fontSize' => 10,
                                         ],
                             ];
            if (count($tmp) > 1) {
                $tmp = explode("\n", trim($labels['dest'][$i], "\n"));
                array_shift($tmp);
                $annotations[] = ['type' => 'line',
                                  'mode' => 'vertical',
                                  'scaleID' => 'x-axis-0',
                                  'value' => (string)$i,
                                  'borderColor' => 'rgba(0,0,0,0)',
                                  'label' => ['enabled' => true,
                                              'content' => $tmp,
                                              'position' => 'top',
                                              'backgroundColor' => 'rgba(0,0,0,0)',
                                              'fontColor' => 'black',
                                              'fontFamily' => 'IPAexGothic',
                                              'fontStyle' => 'normal',
                                              'fontSize' => 10,
                                             ],
                                 ];
            }
        }
    }
    $annotations[] = ['type' => 'line',
                      'mode' => 'vertical',
                      'scaleID' => 'x-axis-1',
                      'value' => $direction_ === '0' ? '五日市' : '海田市',
                      'borderColor' => 'rgba(255,100,100,200)',
                      'borderWidth' => 3,
                     ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels['real'],
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                           'annotation' => ['annotations' => $annotations,
                                           ],
                          ],
            ];
    $height = 150;
    if ($y_max > 2) {
        $height = 210;
    }
    /*
    $url = "https://quickchart.io/chart?width=1500&height=${height}&c=" . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    error_log($log_prefix . 'URL length : ' . number_format(strlen($url)));
    */
    
    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    // exec('node ../scripts/chartjs_node.js 1500 210 ' . base64_encode(json_encode($json)) . ' ' . $file);
    exec('node ../scripts/chartjs_node.js 1000 ' . $height . ' ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    /*
    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 3, imagesy($im1) / 3);
    imagefill($im2, 0, 0, imagecolorallocate($im1, 255, 255, 255));
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 3, imagesy($im1) / 3, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);
    $res = file_get_contents($file);
    unlink($file);
    */

    return $res;
}
