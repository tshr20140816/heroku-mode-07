<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

// $rc = func_20190601b($mu);
// $rc = func_20190601c($mu);
$rc = func_20190601d($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190601d($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    $res = $mu_->get_contents($url, null, true);

    $stations = [];
    $index = 0;
    $labels['station'] = [];
    foreach (json_decode($res, true)['stations'] as $station) {
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
    
    error_log(print_r($labels, true));
    error_log(print_r($stations, true));

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';
    $res = $mu_->get_contents($url);
    error_log(print_r(json_decode($res, true), true));
    $json = json_decode($res, true);

    $data = [];
    $data['ontime'] = [];
    $data['delay'] = [];
    $y_max = 0;
    foreach ($json['trains'] as $train) {
        if ($train['direction'] == '1') { // 1 : kudari
            $tmp = new stdClass();
            $pos = explode('_', $train['pos']);
            if ($pos[1] === '####') {
                $tmp->x = (string)$stations[$pos[0]]['index'];
            } else {
                $tmp->x = (string)($stations[$pos[0]]['index'] + 1);
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
            $tmp->y = $y;
            if ($y > $y_max) {
                $y_max = $y;
            }
            if ($train['delayMinutes'] != '0') {
                $data['delay'][] = $tmp;
                $labels['dest'][(int)$tmp->x] .= "\n" . $train['dest'] . $train['delayMinutes'];
            } else {
                $data['ontime'][] = $tmp;
                $labels['dest'][(int)$tmp->x] .= "\n" . $train['dest'];
            }
        }
    }
    error_log(print_r($data, true));
    error_log(print_r($labels, true));
    
    $datasets[] = ['data' => $data['ontime'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'pointRadius' => 0,
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => 90,
                   'pointBackgroundColor' => 'gray',
                   'pointBorderColor' => 'red',
                   'pointBorderWidth' => 2,
                  ];
    
    $datasets[] = ['data' => $data['delay'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'pointRadius' => 0,
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => 90,
                   'pointBackgroundColor' => 'gray',
                   'pointBorderColor' => 'cyan',
                   'pointBorderWidth' => 3,
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
            $tmp = explode("\n", trim($labels['dest'][$i]), 2);
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
    $url = 'https://quickchart.io/chart?width=1500&height=150&c=' . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    
    header('Content-Type: image/png');
    echo $res;
}

function func_20190601c($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    $res = $mu_->get_contents($url, null, true);
    // error_log(print_r(json_decode($res, true), true));

    $stations = [];
    foreach (array_reverse(json_decode($res, true)['stations'], true) as $station) {
        $stations[$station['info']['code']] = $station['info']['name'];
    }
    // error_log(print_r($stations, true));
    
    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';
    $res = $mu_->get_contents($url);
    error_log(print_r(json_decode($res, true), true));
    $json = json_decode($res, true);
    
    $list_y1 = [];
    $list_y2 = [];
    $y = 0;
    foreach ($stations as $station_code => $station_name) {
        $is_exists = false;
        foreach ($json['trains'] as $train) {
            if ($train['direction'] == '1') { // 1 : kudari
                $tmp_pos = explode('_', $train['pos']);
                if ($tmp_pos[1] === '####') {
                    if ($tmp_pos[0] == $station_code) {
                        $list_y1[] = $station_name;
                        $list_y2[(string)$y] = $train['dest'] . ' ' . $train['delayMinutes'];
                        $is_exists = true;
                        $y++;
                    }
                }
            }
        }
        if ($is_exists === false) {
            $list_y1[] = $station_name;
            $y++;
        }
        $is_exists = false;
        foreach ($json['trains'] as $train) {
            if ($train['direction'] == '1') { // 1 : kudari
                $tmp_pos = explode('_', $train['pos']);
                if ($tmp_pos[1] !== '####') {
                    if ($tmp_pos[0] == $station_code) {
                        $list_y1[] = '';
                        $list_y2[(string)$y] = $train['dest'] . ' ' . $train['delayMinutes'];
                        $is_exists = true;
                        $y++;
                    }
                }
            }
        }
        if ($is_exists === false) {
            $list_y1[] = '';
            $y++;
        }
    }
    array_pop($list_y1);
    array_pop($list_y2);
    error_log(print_r($list_y1, true));
    error_log(print_r($list_y2, true));
    
    $labels = [];
    $labels[] = '';
    $labels[] = '下り';
    $labels[] = '';
    
    $data = [];
    foreach ($list_y2 as $y => $train_info) {
        $tmp = new stdClass();
        $tmp->x = $labels[1];
        $tmp->y = (int)$y;
        $data[] = $tmp;
    }
    
    $datasets = [];
    $datasets[] = ['data' => $data,
                   'fill' => false,
                   'showLine' => false,
                  ];
    
    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'ticks' => ['max' => count($list_y1),
                                    'min' => 0,
                                    'fontSize' => 5,
                                    'callback' => '__CALLBACK__',
                                   ],
                       ];
    
    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                          ],
            ];
    // $url = 'https://quickchart.io/chart?c=' . urlencode(json_encode($json));
    // $res = $mu_->get_contents($url);
    
    $case = '';
    for ($i = 0; $i < count($list_y1); $i++) {
        if ($list_y1[$i] !== '') {
            $case .= "case ${i}: s = '" . $list_y1[$i] . "'; break; ";
        }
    }
    $case .= " default: s = '';";
    error_log($case);
    
    $tmp = str_replace('"__CALLBACK__"', "function(value){var s = ''; switch (value) {" . $case . "} return s;}", json_encode($json));
    $url = 'https://quickchart.io/chart?c=' . urlencode(json_encode($tmp));
    $res = $mu_->get_contents($url);
    
    header('Content-Type: image/png');
    echo $res;
}

function func_20190601b($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    $res = $mu_->get_contents($url, null, true);
    // error_log(print_r(json_decode($res, true), true));

    $stations = [];
    $index = 0;
    foreach (json_decode($res, true)['stations'] as $station) {
        $stations[$station['info']['code']]['name'] = $station['info']['name'];
        $stations[$station['info']['code']]['index'] = $index;
        $index += 2;
        
        $tmp_labels[] = '';
        $tmp_labels[] = $station['info']['name'];
    }
    array_shift($tmp_labels);
    $labels = $tmp_labels;
    
    error_log(print_r($labels, true));
    error_log(print_r($stations, true));

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';
    $res = $mu_->get_contents($url);
    error_log(print_r(json_decode($res, true), true));
    $json = json_decode($res, true);
    
    $list_yaxes = [];
    foreach ($json['trains'] as $train) {
        if ($train['direction'] == '1') {
            $list_yaxes[$train['dest'] . '_' . $train['displayType'] . '_' . $train['delayMinutes']] = $train['delayMinutes'];
        }
    }
    // asort($list_yaxes, SORT_NUMERIC);
    error_log(print_r($list_yaxes, true));
    
    $index = 0;
    $yaxes = [];
    $yaxes['dummy'] = $index++;
    foreach (array_keys($list_yaxes) as $item) {
        $yaxes[$item] = $index++;
    }
    error_log(print_r($yaxes, true));
    
    $data['普通_0'] = [];
    $data['快速_0'] = [];
    $data['その他_0'] = [];
    $data['普通_X'] = [];
    $data['快速_X'] = [];
    $data['その他_X'] = [];
    foreach ($json['trains'] as $train) {
        if ($train['direction'] == '1') {
            $pos = explode('_', $train['pos']);
            $tmp = new stdClass();
            if ($pos[1] === '####') {
                $tmp->x = (string)$stations[$pos[0]]['index'];
            } else {
                $tmp->x = (string)($stations[$pos[0]]['index'] + 1);
            }
            $tmp->y = $yaxes[$train['dest'] . '_' . $train['displayType'] . '_' . $train['delayMinutes']];
            $key = '';
            switch ($train['displayType']) {
                case '普通':
                case '快速':
                    $key = $train['displayType'];
                    break;
                default:
                    $key = 'その他';
            }
            if ((int)$train['delayMinutes'] === 0) {
                $key .= '_' . $train['delayMinutes'];
            } else {
                $key .= '_X';
            }
            $data[$key][] = $tmp;
        }
    }
    error_log(print_r($data, true));
    
    $labels0 = [];
    for ($i = 0; $i < count($labels); $i++) {
        $labels0[] = (string)$i;
    }
    
    $scales = new stdClass();
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'display' => true,
                        'labels' => $labels,
                        'ticks' => ['fontColor' => 'black',
                                   ],
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-1',
                        'display' => false,
                        'labels' => $labels0,
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-2',
                        'display' => true,
                        'labels' => $labels,
                        'position' => 'top',
                        'ticks' => ['fontColor' => 'black',
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'ticks' => ['fontColor' => 'black',
                                    'stepSize' => 1,
                                    'max' => count($yaxes),
                                    'min' => 0,
                                    'callback' => '__CALLBACK__',
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        'ticks' => ['fontColor' => 'black',
                                    'stepSize' => 1,
                                    'max' => count($yaxes),
                                    'min' => 0,
                                    'callback' => '__CALLBACK__',
                                   ],
                       ];
    
    $datasets = [];
    $datasets[] = ['data' => [],
                   'fill' => false,
                   'xAxisID' => 'x-axis-0',
                   'pointRadius' => 0,
                   'showLine' => false,
                   'borderColor' => 'rgba(0,0,0,0)',
                   'backgroundColor' => 'rgba(0,0,0,0)',
                   'pointStyle' => 'circle',
                   'pointRadius' => 1,
                   'pointBackgroundColor' => 'black',
                   'pointBorderColor' => 'black',
                   // 'label' => date('Y/m/d H:i', strtotime($update_time) + 32400)
                  ];
    foreach ($data as $key => $value) {
        if (count($value) === 0) {
            continue;
        }
        if (substr($key, -2) === '_0') {
            $pointBorderColor = 'black';
            $pointBorderWidth = 1;
            $pointBackgroundColor = 'green';
        } else {
            $pointBorderColor = 'cyan';
            $pointBorderWidth = 3;
            $pointBackgroundColor = 'green';
        }
        $datasets[] = ['data' => $value,
                       'fill' => false,
                       'xAxisID' => 'x-axis-1',
                       // 'yAxisID' => 'y-axis-0',
                       'pointRadius' => 0,
                       'showLine' => false,
                       'pointStyle' => 'triangle',
                       'pointRadius' => 12,
                       'pointRotation' => 90,
                       'pointBackgroundColor' => $pointBackgroundColor,
                       'pointBorderColor' => $pointBorderColor,
                       'pointBorderWidth' => $pointBorderWidth,
                      ];
    }
    for ($i = 0; $i < count($yaxes); $i++) {
        if ($i % 2 == 0) {
            continue;
        }
        $tmp_data = [];
        $tmp = new stdClass();
        $tmp->x = '0';
        $tmp->y = $i;
        $tmp_data[] = $tmp;
        $tmp = new stdClass();
        $tmp->x = (string)(count($labels) - 1);
        $tmp->y = $i;
        $tmp_data[] = $tmp;
        $datasets[] = ['data' => $tmp_data,
                       'fill' => false,
                       'xAxisID' => 'x-axis-1',
                       'pointRadius' => 0,
                       'showLine' => true,
                       'borderColor' => 'gray',
                       'borderWidth' => 2,
                      ];
    }

    $index = 0;
    foreach (array_keys($yaxes) as $item) {
        $station_name = explode('_', $item)[0];
        if (in_array($station_name, $labels, true)) {
            $tmp_data = [];
            $tmp = new stdClass();
            $tmp->x = $station_name;
            $tmp->y = $index;
            $tmp_data[] = $tmp;
            $datasets[] = ['data' => $tmp_data,
                           'fill' => false,
                           'xAxisID' => 'x-axis-0',
                           'pointStyle' => 'circle',
                           'pointRadius' => 5,
                           'showLine' => false,
                           'pointBackgroundColor' => 'gray',
                           'pointBorderColor' => 'gray',
                          ];
        }
        $index++;
    }
    
    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'vertical',
                                                               'scaleID' => 'x-axis-0',
                                                               'value' => '向洋',
                                                               'borderColor' => 'red',
                                                               'borderWidth' => 1,
                                                              ],
                                                              ['type' => 'line',
                                                               'mode' => 'vertical',
                                                               'scaleID' => 'x-axis-0',
                                                               'value' => '新井口',
                                                               'borderColor' => 'red',
                                                               'borderWidth' => 1,
                                                              ],
                                                             ],
                                           ],
                          ],
            ];
    
    error_log(print_r($json, true));
    
    // $url = 'https://quickchart.io/chart?width=1500&height=210&c=' . urlencode(json_encode($json));
    
    $case = '';
    $index = 0;
    foreach (array_keys($yaxes) as $item) {
        if ($item != 'dummy') {
            $index++;
            if (mb_substr($item, -5) === '_普通_0') {
                $case .= "case ${index}: s = '" . explode('_', $item)[0] . "'; break; ";
            } else if (substr($item, -2) === '_0') {
                $tmp = str_replace('_', ' ', substr($item, 0, strlen($item) - 2));
                $case .= "case ${index}: s = '${tmp}'; break; ";          
            } else {
                $tmp = str_replace('_普通_', ' ', $item);
                $tmp = str_replace('_', ' ', $tmp);
                $case .= "case ${index}: s = '${tmp}'; break; ";
            }
        }
    }
    $case .= " default: s = '';";
    
    $tmp = str_replace('"__CALLBACK__"', "function(value){var s = ''; switch (value) {" . $case . "} return s;}", json_encode($json));
    $url = 'https://quickchart.io/chart?width=1500&height=300&c=' . urlencode($tmp);
    $res = $mu_->get_contents($url);
    error_log(strlen($url));

    header('Content-Type: image/png');
    echo $res;
    
}
