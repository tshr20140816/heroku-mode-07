<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = func_20190719($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190719($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'http://www1.river.go.jp/cgi-bin/DspDamData.exe?ID=1368080700010&KIND=3&PAGE=0';
    $res = $mu_->get_contents($url);
    
    // error_log($res);

    $rc = preg_match('/<IFRAME src="(.+?)"/', $res, $match);

    $url = 'http://www1.river.go.jp' . $match[1];
    $res = $mu_->get_contents($url);
    
    // error_log($res);
    
    $pattern = '/<TR>.+?<TD .+?<TD .+?>(.+?)<.+?<TD .+?<TD .+?<TD .+?<TD .+?><.+?>(.+?)<.+?<TD .+?>(.+?)</s';
    $rc = preg_match_all($pattern, $res, $matches, PREG_SET_ORDER);
    
    // error_log(print_r(array_chunk($matches, 100)[0], true));
    
    $data = [];
    $data['horyuryo'] = [];
    $data['chosui_ritsu'] = [];
    $labels = [];
    foreach (array_chunk($matches, 100)[0] as $item) {
        error_log($item[1] . ' ' . $item[2] . ' ' . strip_tags($item[3]));
        $labels[] = $item[1];
        $tmp = new stdClass();
        $tmp->x = $item[1];
        $tmp->y = $item[2];
        $data['horyuryo'][] = $tmp;
        if ($item[3] === '-') {
            continue;
        }
        $tmp = new stdClass();
        $tmp->x = $item[1];
        $tmp->y = strip_tags($item[3]);
        $data['chosui_ritsu'][] = $tmp;
    }
    
    
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                        'ticks' => ['beginAtZero' => true,
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        'ticks' => ['beginAtZero' => true,
                                    'max' => 120,
                                   ],
                       ];
    
    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data['horyuryo'],
                                        'fill' => false,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                        'yAxisID' => 'y-axis-0',
                                       ],
                                       ['data' => $data['chosui_ritsu'],
                                        'fill' => false,
                                        'borderColor' => 'blue',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'blue',
                                        'pointRadius' => 2,
                                        'yAxisID' => 'y-axis-1',
                                       ],
                                      ],
                       ],
             ];
    
    $url = 'https://quickchart.io/chart?width=600&height=320&c=' . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    
    header('Content-Type: image/png');
    echo $res;
}
