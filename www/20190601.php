<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = func_20190601f($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190601f($mu_) {
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    $sanyo2_st = $mu_->get_contents($url, null, true);
    
    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';
    $sanyo2 = $mu_->get_contents($url);
    
    error_log($log_prefix . print_r(json_decode($sanyo2_st, true), true));
    error_log($log_prefix . print_r(json_decode($sanyo2, true), true));

    $res1 = func_20190601e($mu_, $sanyo2_st, $sanyo2, '1');
    $res2 = func_20190601e($mu_, $sanyo2_st, $sanyo2, '0');
    
    $im1 = imagecreatefromstring($res1);
    $x = imagesx($im1);
    $y1 = imagesy($im1);
    imagedestroy($im1);
    
    $im1 = imagecreatefromstring($res2);
    // $x = imagesx($im1);
    $y2 = imagesy($im1);
    imagedestroy($im1);
    
    $im1 = imagecreatetruecolor($x, $y1 + $y2);
    imagefill($im1, 0, 0, imagecolorallocate($im1, 255, 255, 255));
    
    $im2 = imagecreatefromstring($res1);
    imagecopy($im1, $im2, 0, 0, 0, 0, $x, $y1);
    imagedestroy($im2);
    
    $im2 = imagecreatefromstring($res2);
    imagecopy($im1, $im2, 0, $y1, 0, 0, $x, $y2);
    imagedestroy($im2);
    
    $im2 = imagerotate($im1, 270, imagecolorallocate($im1, 0, 0, 0));
    imagedestroy($im1);
    
    $x = imagesx($im2);
    $y = imagesy($im2);
    
    $im1 = imagecreatetruecolor($x, $y / 3);
    imagefill($im1, 0, 0, imagecolorallocate($im1, 255, 255, 255));
    
    imagecopy($im1, $im2, 0, 0, 0, 0, $x, $y / 3);
    
    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im1, $file, 9);
    imagedestroy($im1);
    $res1 = file_get_contents($file);
    unlink($file);
    
    $im1 = imagecreatetruecolor($x, $y / 3 * 2);
    imagefill($im1, 0, 0, imagecolorallocate($im1, 255, 255, 255));
    
    imagecopy($im1, $im2, 0, 0, 0, $y / 3 * 2, $x, $y / 3 * 2);
    imagedestroy($im2);
    
    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im1, $file, 9);
    imagedestroy($im1);
    $res2 = file_get_contents($file);
    unlink($file);
    
    echo '<html><body>' . date('H:i:s', strtotime(json_decode($sanyo2, true)['update']) + 60 * 60 * 9)
        . '<br><img width="100%" src="data:image/png;base64,' . base64_encode($res1)
        . '"><br><img width="100%" src="data:image/png;base64,' . base64_encode($res2)
        . '"></body></html>';
}

function func_20190601e($mu_, $sanyo2_st_, $sanyo2_, $direction_ = '0') // $direction_ : '0' nobori / '1' kudari
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
    
    error_log($log_prefix . print_r($labels, true));
    error_log($log_prefix . print_r($stations, true));

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
                $labels['dest'][(int)$tmp->x] .= "\n" . $dest . $train['delayMinutes'];
            } else {
                if ($train['notice'] != '' || $train['displayType'] != '普通') {
                    $data['ontime_etc'][] = $tmp;
                } else {
                    $data['ontime'][] = $tmp;
                }
                $labels['dest'][(int)$tmp->x] .= "\n" . $dest;
            }
        }
    }
    error_log($log_prefix . print_r($data, true));
    error_log($log_prefix . print_r($labels, true));
    
    $pointRotation = $direction_ === '0' ? 270 : 90;
    
    $datasets[] = ['data' => $data['ontime'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'lightgray',
                   'pointBorderColor' => 'red',
                   'pointBorderWidth' => 2,
                  ];
    
    $datasets[] = ['data' => $data['delay'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'lightgray',
                   'pointBorderColor' => 'cyan',
                   'pointBorderWidth' => 3,
                  ];
    
    $datasets[] = ['data' => $data['ontime_etc'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'yellow',
                   'pointBorderColor' => 'red',
                   'pointBorderWidth' => 2,
                  ];
    
    $datasets[] = ['data' => $data['delay_etc'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'yellow',
                   'pointBorderColor' => 'cyan',
                   'pointBorderWidth' => 3,
                  ];
    
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
                   // 'pointBorderWidth' => 3,
                  ];
    
    $scales = new stdClass();
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'display' => false,
                        'labels' => $labels['real'],
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-1',
                        'display' => true,
                        'labels' => $labels['station'],
                        'ticks' => ['fontColor' => 'black',
                                    'autoSkip' => false,
                                    'minRotation' => 45,
                                    'maxRotation' => 45,
                                   ],
                       ];
    /*
    $scales->xAxes[] = ['id' => 'x-axis-2',
                        'display' => true,
                        'labels' => $labels['dest'],
                        'position' => 'top',
                        'ticks' => ['fontColor' => 'black',
                                   ],
                       ];
    */
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => false,
                        'ticks' => ['max' => $y_max + 1,
                                    'min' => 0,
                                   ],
                       ];
    
    $annotations = [];
    for ($i = 0; $i < count($labels['dest']); $i++) {
        if ($labels['dest'][$i] !== '') {
            $tmp = explode("\n", ltrim($labels['dest'][$i]), 2);
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
                                         ],
                             ];
            if (count($tmp) > 1) {
                $annotations[] = ['type' => 'line',
                                  'mode' => 'vertical',
                                  'scaleID' => 'x-axis-0',
                                  'value' => (string)$i,
                                  'borderColor' => 'rgba(0,0,0,0)',
                                  'label' => ['enabled' => true,
                                              'content' => $tmp[1],
                                              'position' => 'top',
                                              'backgroundColor' => 'rgba(0,0,0,0)',
                                              'fontColor' => 'black',
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
    $url = "https://quickchart.io/chart?width=1500&height=${height}&c=" . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    error_log($log_prefix . 'URL length : ' . number_format(strlen($url)));
    
    /*
    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 3, imagesy($im1) / 3);
    // imagealphablending($im2, false);
    // imagesavealpha($im2, true);
    imagefill($im2, 0, 0, imagecolorallocate($im1, 255, 255, 255));
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 3, imagesy($im1) / 3, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);
    error_log($log_prefix . 'file size : ' . number_format(filesize($file)));
    $res = file_get_contents($file);
    unlink($file);
    */
    
    return $res;
}
