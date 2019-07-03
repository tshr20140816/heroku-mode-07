<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190621($mu, '/tmp/20190621dummy');
@unlink('/tmp/20190621dummy');

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621b($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $cookie = tempnam("/tmp", 'cookie_' . md5(microtime(true)));
    
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
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ];
    
    $url = 'https://jr-central.co.jp/';
    $res = $mu_->get_contents($url, $options);
    
    /*
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/pc/ja/ti08.html';
    $res = $mu_->get_contents($url, $options);
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/common/data/common_ja.json';
    $res = $mu_->get_contents($url, $options);
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json';
    $res = $mu_->get_contents($url, $options);
    */
    unlink($cookie);
}

function func_20190621($mu_, $file_name_blog_)
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
    
    $url = $mu_->get_env('URL_RIVER_YAHOO_2');
    $res = $mu_->get_contents($url, $options);
    
    $rc = preg_match("/common.riverData = JSON.parse\('(.+)'/", $res, $match);
    $json = json_decode($match[1], true);
    error_log(print_r($json, true));
    $title = $json['RiverName'];
    
    $rc = preg_match("/common.obsData = JSON.parse\('(.+)'/", $res, $match);
    $json = json_decode($match[1], true);
    error_log(print_r($json, true));

    $target = null;
    foreach ($json as $item) {
        if ($item['ObsrvtnName'] == $mu_->get_env('RIVER_POINT_2')) {
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
    if (count($data3) > 0) {
        $datasets[] = ['data' => $data3,
                       'fill' => false,
                       'pointStyle' => 'line',
                       'backgroundColor' => 'green',
                       'borderColor' => 'green',
                       'borderWidth' => 1,
                       'pointRadius' => 0,
                       'yAxisID' => 'y-axis-1',
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
    
    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => false,
                        'position' => 'right',
                       ];
    
    $chart_data = ['type' => 'line',
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
    
    $url = 'https://quickchart.io/chart?width=300&height=160&c=' . urlencode(json_encode($chart_data));
    
    $res = $mu_->get_contents($url);
    
    header('Content-Type: image/png');
    echo $res;
}
