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
    
    $data1 = [];
    $index = 0;
    // kudari
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
            $data1[] = $tmp;
        }
        $index += 2;
    }
    
    $index = 0;
    // kudari
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
            $data1[] = $tmp;
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
                        'labels' => $labels,
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-1',
                        'display' => false,
                        'labels' => $labels0,
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => false,
                        'ticks' => ['stepSize' => 1,
                                    'max' => $max_y + 1,
                                   ],
                       ];
    
    $data = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data,
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-0',
                                        'yAxisID' => 'y-axis-0',
                                       ],
                                       ['type' => 'line',
                                        'data' => $data1,
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => 90,
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
                                                               'scaleID' => 'u-axis-0',
                                                               'value' => $max_y + 1,
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
    header('Content-Type: image/png');
    echo $res;
}

