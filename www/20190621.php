<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190621($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/common/data/common_ja.json';
    $res = $mu_->get_contents_proxy($url);
    $tmp = explode('</script>', $res);
    $tmp = trim(end($tmp));
    // error_log($tmp);
    
    $rc = preg_match('/"station": {(.+?)}/s', $tmp, $match);
    $stations = json_decode('{' . $match[1] . '}', true);
    error_log(print_r($stations, true));
    
    $rc = preg_match('/"train": {(.+?)}/s', $tmp, $match);
    $trains = json_decode('{' . $match[1] . '}', true);
    error_log(print_r($trains, true));
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json';
    $res = $mu_->get_contents_proxy($url);
    $tmp = explode('</script>', $res);
    // error_log(trim(end($tmp)));
    $tmp = json_decode(trim(end($tmp)), true);
    // error_log(print_r($tmp, true));
    $atStations = $tmp['trainLocationInfo']['atStation']['bounds'];
    // error_log(print_r($atStations, true));
    $betweenStations = $tmp['trainLocationInfo']['betweenStation']['bounds'];
    
    $labels = [];
    // kudari
    foreach ($betweenStations[2] as $item) {
        $labels[] = $stations[$item['station']];
    }
    $data = [];
    $tmp_labels = [];
    foreach ($labels as $item) {
        $tmp_labels[] = '';
        $tmp_labels[] = $item;
        $data[] = 0;
        $data[] = 0;
    }
    array_shift($tmp_labels);
    array_shift($data);
    $labels = $tmp_labels;
    
    $max_y = 0;
    
    $data1 = []; // nozomi
    $data2 = []; // hikara
    $data3 = []; // nokori
    $data4 = []; // mizuho
    $index = 0;
    // kudari eki
    foreach ($atStations[2] as $item) {
        error_log($stations[$item['station']]);
        $level = 0;
        foreach ($item['trains'] as $train) {
            error_log('下り ' . $trains[$train['train']] . ' ' . $train['trainNumber']);
            if ($max_y < $level) {
                $max_y = $level;
            }
            $tmp = new stdClass();
            $tmp->x = (string)$index;
            $tmp->y = ++$level;
            if ($trains[$train['train']] == 'のぞみ') {
                $data1[] = $tmp;
            } else if ($trains[$train['train']] == 'ひかり') {
                $data2[] = $tmp;
            } else if ($trains[$train['train']] == 'みずほ') {
                $data3[] = $tmp;
            } else {
                $data4[] = $tmp;
            }
        }
        $index += 2;
    }
    
    $index = 0;
    // kudari ekikan
    foreach ($betweenStations[2] as $item) {
        error_log($stations[$item['station']]);
        $level = 0;
        foreach ($item['trains'] as $train) {
            error_log('下り ' . $trains[$train['train']] . ' ' . $train['trainNumber']);
            if ($max_y < $level) {
                $max_y = $level;
            }
            $tmp = new stdClass();
            $tmp->x = (string)($index + 1);
            $tmp->y = ++$level;
            if ($trains[$train['train']] == 'のぞみ') {
                $data1[] = $tmp;
            } else if ($trains[$train['train']] == 'ひかり') {
                $data2[] = $tmp;
            } else if ($trains[$train['train']] == 'みずほ') {
                $data3[] = $tmp;
            } else {
                $data4[] = $tmp;
            }
        }
        $index += 2;
    }
    
    $labels0 = [];
    for ($i = 0; $i < count($labels); $i++) {
        $labels0[] = (string)$i;
    }
    $scales = new stdClass();
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'display' => true,
                        'labels' => array_reverse($labels),
                        'ticks' => ['fontColor' => 'black',
                                   ],
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-1',
                        'display' => false,
                        'labels' => array_reverse($labels0),
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => false,
                        'ticks' => ['stepSize' => 1,
                                    'max' => $max_y + 2,
                                   ],
                       ];
    
    $data = ['type' => 'line',
             'data' => ['labels' => array_reverse($labels),
                        'datasets' => [['data' => array_reverse($data),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-0',
                                        'yAxisID' => 'y-axis-0',
                                        'pointRadius' => 0,
                                        'showLine' => false,
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data1),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => 270,
                                        'pointBackgroundColor' => 'yellow',
                                        'pointBorderColor' => 'black',
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data2),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => 270,
                                        'pointBackgroundColor' => 'red',
                                        'pointBorderColor' => 'red',
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data3),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => 270,
                                        'pointBackgroundColor' => 'orange',
                                        'pointBorderColor' => 'orange',
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data4),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => 270,
                                        'pointBackgroundColor' => 'blue',
                                        'pointBorderColor' => 'blue',
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'horizontal',
                                                               'scaleID' => 'y-axis-0',
                                                               'value' => $max_y + 2,
                                                               'borderColor' => 'black',
                                                               'borderWidth' => 1,
                                                              ],
                                                             ],
                                           ],
                          ],
            ];
    
    $url = 'https://quickchart.io/chart?width=1500&height=200&c=' . urlencode(json_encode($data));
    $res = $mu_->get_contents($url);
    error_log(strlen($url));
    error_log($max_y);
    header('Content-Type: image/png');
    echo $res;
}

