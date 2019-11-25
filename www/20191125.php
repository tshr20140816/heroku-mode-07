<?php

include('/app/classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

get_river_water_level($mu, getenv('URL_RIVER_YAHOO_1'), getenv('RIVER_POINT_1'));

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function get_river_water_level($mu_, $url_, $point_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $options = [
        CURLOPT_ENCODING => 'gzip, deflate',
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
    // error_log($log_prefix . print_r($json, true));
    $mu_->logging_object($json, $log_prefix);
    $title = $json['RiverName'];

    $rc = preg_match("/common.obsData = JSON.parse\('(.+)'/", $res, $match);
    $json = json_decode($match[1], true);
    // error_log($log_prefix . print_r($json, true));
    $mu_->logging_object($json, $log_prefix);

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

    /*
    $url = 'https://quickchart.io/chart?width=300&height=160&c=' . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node /app/scripts/chartjs_node.js 300 160 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);
    
    header('Content-Type: image/png');
    echo $res;
}
