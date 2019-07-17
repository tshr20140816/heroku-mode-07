<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = func_20190601b($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

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
    asort($list_yaxes, SORT_NUMERIC);
    error_log(print_r($list_yaxes, true));
    
    $index = 0;
    $yaxes = [];
    $yaxes['dummy'] = $index++;
    foreach (array_keys($list_yaxes) as $item) {
        $yaxes[$item] = $index++;
    }
    error_log(print_r($yaxes, true));
    
    $data = [];
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
            $data[] = $tmp;
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
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'ticks' => ['stepSize' => 1,
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
    $datasets[] = ['data' => $data,
                   'fill' => false,
                   'xAxisID' => 'x-axis-1',
                   // 'yAxisID' => 'y-axis-0',
                   'pointRadius' => 0,
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => 90,
                   'pointBorderColor' => 'black',
                  ];
    
    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['labels' => ['fontColor' => 'black',],],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                          ],
            ];
    
    // $url = 'https://quickchart.io/chart?width=1500&height=210&c=' . urlencode(json_encode($json));
    
    $case = '';
    $index = 0;
    foreach (array_keys($yaxes) as $item) {
        if ($item != 'dummy') {
            $index++;
            $case .= "case ${index}: s = '${item}'; break; ";
        }
    }
    $case .= " default: s = '';";
    
    $tmp = str_replace('"__CALLBACK__"', "function(value){var s = ''; switch (value) {" . $case . "} return s;}", json_encode($json));
    $url = 'https://quickchart.io/chart?width=1500&height=210&c=' . urlencode($tmp);
    $res = $mu_->get_contents($url);
    
    header('Content-Type: image/png');
    echo $res;
    
}

function func_20190601($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    $res = $mu_->get_contents($url, null, true);
    // error_log(print_r(json_decode($res, true), true));
    
    $stations = [];
    
    $data = [];
    $data['station'] = [];
    $tmp_labels = [];
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

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';
    $res = $mu_->get_contents($url);
    // error_log(print_r(json_decode($res, true), true));
    $json = json_decode($res, true);
    
    $data['nobori'] = [];
    
    $update_time = $json['update'];
    foreach ($json['trains'] as $train) {
        if ($train['direction'] == '0') {
            error_log(print_r($train, true));
            $pos = explode('_', $train['pos']);
            error_log('name : ' . $stations[$pos[0]]['name']);
            error_log('index : ' . $stations[$pos[0]]['index']);
            $tmp = new stdClass();
            if ($pos[1] === '####') {
                $tmp->x = (string)$stations[$pos[0]]['index'];
            } else {
                $tmp->x = (string)($stations[$pos[0]]['index'] - 1);
            }
            $tmp->y = 1;
            if ($train['delayMinutes'] === 0) {
                if ($train['displayType'] === '普通') {
                    $data['nobori']['ontime'][$train['dest']][] = $tmp;
                } else {
                    $data['nobori']['ontime'][$train['dest'] . ' ' . $train['displayType']][] = $tmp;
                }
            } else {
                if ($train['displayType'] === '普通') {
                    $data['nobori']['deley'][$train['dest']][] = $tmp;
                } else {
                    $data['nobori']['deley'][$train['dest'] . ' ' . $train['displayType']][] = $tmp;
                }
            }
        }
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
    
    $datasets = [];
    $datasets[] = ['data' => array_reverse($data['station']),
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
                   // 'label' => ($bound_ === 1 ? '<上り> ' : '<下り> ') . date('Y/m/d H:i', $dt),
                   'label' => date('Y/m/d H:i', strtotime($update_time) + 32400)
                  ];
    
    foreach ($data['nobori']['ontime'] as $key => $item) {
        $datasets[] = ['data' => array_reverse($item),
                       'fill' => false,
                       'xAxisID' => 'x-axis-1',
                       'showLine' => false,
                       // 'borderColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : 'black',
                       // 'backgroundColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : $defines[$item]['color'],
                       'pointStyle' => 'triangle',
                       'pointRadius' => 12,
                       // 'pointRotation' => $pointRotation,
                       'pointRotation' => 90,
                       // 'pointBackgroundColor' => $defines[$item]['color'],
                       // 'pointBackgroundColor' => 'red',
                       'pointBorderColor' => 'black',
                       // 'label' => $defines[$item]['label'] === '' ? '' : $defines[$item]['label'] . " ${count}",
                       'label' => $key,
                      ];
    }
    
    foreach ($data['nobori']['delay'] as $key => $item) {
        $datasets[] = ['data' => array_reverse($item),
                       'fill' => false,
                       'xAxisID' => 'x-axis-1',
                       'showLine' => false,
                       // 'borderColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : 'black',
                       // 'backgroundColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : $defines[$item]['color'],
                       'pointStyle' => 'triangle',
                       'pointRadius' => 12,
                       // 'pointRotation' => $pointRotation,
                       'pointRotation' => 90,
                       // 'pointBackgroundColor' => $defines[$item]['color'],
                       // 'pointBackgroundColor' => 'red',
                       'pointBorderColor' => 'cyan',
                       'pointBorderWidth' => 3,
                       // 'label' => $defines[$item]['label'] === '' ? '' : $defines[$item]['label'] . " ${count}",
                       'label' => $key,
                      ];
    }
    
    $json = ['type' => 'line',
             'data' => ['labels' => array_reverse($labels),
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['labels' => ['fontColor' => 'black',],],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                          ],
            ];
    
    $url = 'https://quickchart.io/chart?width=1500&height=210&c=' . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    
    header('Content-Type: image/png');
    echo $res;
}
