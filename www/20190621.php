<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$res1 = func_20190621($mu, 1);
$res2 = func_20190621($mu, 2);

$im1 = imagecreatetruecolor(1000, 280);
imagealphablending($im1, false);
imagesavealpha($im1, true);

$im2 = imagecreatefromstring($res1);
imagecopy($im1, $im2, 0, 0, 0, 0, 1000, 140);
imagedestroy($im2);

$im2 = imagecreatefromstring($res2);
imagecopy($im1, $im2, 0, 140, 0, 0, 1000, 140);
imagedestroy($im2);

$file = tempnam("/tmp", md5(microtime(true)));
imagepng($im1, $file, 9);
imagedestroy($im1);

header('Content-Type: image/png');
echo file_get_contents($file);

unlink($file);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

// $bound_ : 1 上り 2 下り
function func_20190621($mu_, $bound_ = 2)
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
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json?' . microtime(true);
    $res = $mu_->get_contents_proxy($url);
    $tmp = explode('</script>', $res);
    // error_log(trim(end($tmp)));
    $tmp = json_decode(trim(end($tmp)), true);
    // error_log(print_r($tmp, true));
    $dt = $tmp['trainLocationInfo']['datetime'] + 32400; // +9 hours
    $atStations = $tmp['trainLocationInfo']['atStation']['bounds'];
    // error_log(print_r($atStations, true));
    $betweenStations = $tmp['trainLocationInfo']['betweenStation']['bounds'];
    
    $labels = [];
    // kudari
    foreach ($betweenStations[$bound_] as $item) {
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
    $data3 = []; // kodama
    $data4 = []; // mizuho
    $data5 = []; // sakura
    $data6 = []; // ???
    
    $index = 0;
    // kudari eki
    foreach ($atStations[$bound_] as $item) {
        error_log($stations[$item['station']]);
        $level = 0;
        foreach ($item['trains'] as $train) {
            error_log($trains[$train['train']] . ' ' . $train['trainNumber']);
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
            } else if ($trains[$train['train']] == 'こだま') {
                $data3[] = $tmp;
            } else if ($trains[$train['train']] == 'みずほ') {
                $data4[] = $tmp;
            } else if ($trains[$train['train']] == 'さくら') {
                $data5[] = $tmp;
            } else {
                $data6[] = $tmp;
            }
        }
        $index += 2;
    }
    
    $index = 0;
    // kudari ekikan
    foreach ($betweenStations[$bound_] as $item) {
        error_log($stations[$item['station']]);
        $level = 0;
        foreach ($item['trains'] as $train) {
            error_log($trains[$train['train']] . ' ' . $train['trainNumber']);
            if ($max_y < $level) {
                $max_y = $level;
            }
            $tmp = new stdClass();
            $tmp->x = (string)($bound_ == 2 ? $index + 1 : $index - 1);
            $tmp->y = ++$level;
            if ($trains[$train['train']] == 'のぞみ') {
                $data1[] = $tmp;
            } else if ($trains[$train['train']] == 'ひかり') {
                $data2[] = $tmp;
            } else if ($trains[$train['train']] == 'こだま') {
                $data3[] = $tmp;
            } else if ($trains[$train['train']] == 'みずほ') {
                $data4[] = $tmp;
            } else if ($trains[$train['train']] == 'さくら') {
                $data5[] = $tmp;
            } else {
                $data6[] = $tmp;
            }
        }
        $index += 2;
    }
    
    $data_nozomi_teishaeki = [];
    
    $tmp = new stdClass();
    $tmp->x = '名古屋';
    $tmp->y = 0;
    $data_nozomi_teishaeki[] = $tmp;
    
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
    
    $pointRotation = $bound_ == 2 ? 270 : 90;
    $data = ['type' => 'line',
             'data' => ['labels' => array_reverse($labels),
                        'datasets' => [['data' => array_reverse($data),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-0',
                                        'yAxisID' => 'y-axis-0',
                                        'pointRadius' => 0,
                                        'showLine' => false,
                                        'borderColor' => 'rgba(0,0,0,0)',
                                        'backgroundColor' => 'rgba(0,0,0,0)',
                                        'pointBackgroundColor' => 'rgba(0,0,0,0)',
                                        'pointBorderColor' => 'rgba(0,0,0,0)',
                                        'label' => ($bound_ === 1 ? '<上り> ' : '<下り> ') . date('Y/m/d H:i', $dt),
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data1),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'borderColor' => 'black',
                                        'backgroundColor' => 'yellow',
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => $pointRotation,
                                        'pointBackgroundColor' => 'yellow',
                                        'pointBorderColor' => 'black',
                                        'label' => 'のぞみ',
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data2),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'borderColor' => 'black',
                                        'backgroundColor' => 'red',
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => $pointRotation,
                                        'pointBackgroundColor' => 'red',
                                        'pointBorderColor' => 'black',
                                        'label' => 'ひかり',
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data3),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'borderColor' => 'black',
                                        'backgroundColor' => 'blue',
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => $pointRotation,
                                        'pointBackgroundColor' => 'blue',
                                        'pointBorderColor' => 'black',
                                        'label' => 'こだま',
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data4),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'borderColor' => 'black',
                                        'backgroundColor' => 'orange',
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => $pointRotation,
                                        'pointBackgroundColor' => 'orange',
                                        'pointBorderColor' => 'black',
                                        'label' => 'みずほ',
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data5),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'borderColor' => 'black',
                                        'backgroundColor' => 'pink',
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => $pointRotation,
                                        'pointBackgroundColor' => 'pink',
                                        'pointBorderColor' => 'black',
                                        'label' => 'さくら',
                                       ],
                                       ['type' => 'line',
                                        'data' => array_reverse($data6),
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-1',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'borderColor' => 'black',
                                        'backgroundColor' => 'black',
                                        'pointStyle' => 'triangle',
                                        'pointRadius' => 12,
                                        'pointRotation' => $pointRotation,
                                        'pointBackgroundColor' => 'black',
                                        'pointBorderColor' => 'black',
                                        'label' => 'その他',
                                       ],
                                       ['type' => 'line',
                                        'data' => $data_nozomi_teishaeki,
                                        'fill' => false,
                                        'xAxisID' => 'x-axis-0',
                                        'yAxisID' => 'y-axis-0',
                                        'showLine' => false,
                                        'borderColor' => 'rgba(0,0,0,0)',
                                        'backgroundColor' => 'rgba(0,0,0,0)',
                                        'pointStyle' => 'circle',
                                        'pointRadius' => 3,
                                        'pointBackgroundColor' => 'yellow',
                                        'pointBorderColor' => 'black',
                                        'label' => '',
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['labels' => ['fontColor' => 'black',],],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'horizontal',
                                                               'scaleID' => 'y-axis-0',
                                                               'value' => $max_y + 2,
                                                               'borderColor' => 'rgba(0,0,0,0)',
                                                               'borderWidth' => 1,
                                                              ],
                                                             ],
                                           ],
                          ],
            ];
    
    $url = 'https://quickchart.io/chart?width=1500&height=210&c=' . urlencode(json_encode($data));
    $res = $mu_->get_contents($url);
    error_log(strlen($url));
    
    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 3, imagesy($im1) / 3);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 3, imagesy($im1) / 3, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);
    $res = file_get_contents($file);
    unlink($file);
    
    // header('Content-Type: image/png');
    // echo $res;
    return $res;
}
