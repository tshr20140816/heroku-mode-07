<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$url = 'https://traininfo.jr-central.co.jp/shinkansen/common/data/common_ja.json';
$res_common_ja = $mu->get_contents_proxy($url);

$url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json?' . microtime(true);
$res_train_location_info = $mu->get_contents_proxy($url);

$res1 = func_20190621($mu, $res_common_ja, $res_train_location_info, 1);
$res2 = func_20190621($mu, $res_common_ja, $res_train_location_info, 2);

$im1 = imagecreatetruecolor(1000, 280);
// imagealphablending($im1, false);
// imagesavealpha($im1, true);

imagefill($im1, 0, 0, imagecolorallocate($im1, 255, 255, 255));

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
function func_20190621($mu_, $common_ja_, $train_location_info_, $bound_ = 2)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $tmp = explode('</script>', $common_ja_);
    $tmp = trim(end($tmp));
    // error_log($tmp);
    
    $rc = preg_match('/"station": {(.+?)}/s', $tmp, $match);
    $stations = json_decode('{' . $match[1] . '}', true);
    error_log(print_r($stations, true));
    
    $rc = preg_match('/"train": {(.+?)}/s', $tmp, $match);
    $trains = json_decode('{' . $match[1] . '}', true);
    error_log(print_r($trains, true));
    
    $tmp = explode('</script>', $train_location_info_);
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
    $data['station'] = [];
    $tmp_labels = [];
    foreach ($labels as $item) {
        $tmp_labels[] = '';
        $tmp_labels[] = $item;
        $data['station'][] = 0;
        $data['station'][] = 0;
    }
    array_shift($tmp_labels);
    array_shift($data['station']);
    $labels = $tmp_labels;
    
    $max_y = 0;
    
    $train_name = ['nozomi', 'hikari', 'kodama', 'mizuho', 'sakura', 'sonota'];
    
    $defines['nozomi']['color'] = 'yellow';
    $defines['hikari']['color'] = 'red';
    $defines['kodama']['color'] = 'blue';
    $defines['mizuho']['color'] = 'orange';
    $defines['sakura']['color'] = 'pink';
    $defines['sonota']['color'] = 'black';
    
    $defines['nozomi']['label'] = 'のぞみ';
    $defines['hikari']['label'] = 'ひかり';
    $defines['kodama']['label'] = 'こだま';
    $defines['mizuho']['label'] = 'みずほ';
    $defines['sakura']['label'] = 'さくら';
    $defines['sonota']['label'] = '';
    
    foreach ($train_name as $item) {
        $data[$item] = [];
        $data[$item]['ontime'] = [];
        $data[$item]['delay'] = [];
    }
    
    $index = 0;
    // kudari eki
    foreach ($atStations[$bound_] as $item) {
        error_log($stations[$item['station']]);
        $level = 0;
        foreach ($item['trains'] as $train) {
            error_log($trains[$train['train']] . ' ' . $train['trainNumber'] . ' ' . $train['delay']);
            if ($max_y < $level) {
                $max_y = $level;
            }
            $tmp = new stdClass();
            $tmp->x = (string)$index;
            $tmp->y = ++$level;
            if ((int)$train['delay'] === 0) {
                $key = 'ontime';
            } else {
                $key = 'delay';
            }
            if ($trains[$train['train']] == 'のぞみ') {
                $data['nozomi'][$key][] = $tmp;
            } else if ($trains[$train['train']] == 'ひかり') {
                $data['hikari'][$key][] = $tmp;
            } else if ($trains[$train['train']] == 'こだま') {
                $data['kodama'][$key][] = $tmp;
            } else if ($trains[$train['train']] == 'みずほ') {
                $data['mizuho'][$key][] = $tmp;
            } else if ($trains[$train['train']] == 'さくら') {
                $data['sakura'][$key][] = $tmp;
            } else {
                $data['sonota'][$key][] = $tmp;
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
            error_log($trains[$train['train']] . ' ' . $train['trainNumber'] . ' ' . $train['delay']);
            if ($max_y < $level) {
                $max_y = $level;
            }
            $tmp = new stdClass();
            $tmp->x = (string)($bound_ == 2 ? $index + 1 : $index - 1);
            $tmp->y = ++$level;
            if ((int)$train['delay'] === 0) {
                $key = 'ontime';
            } else {
                $key = 'delay';
            }
            if ($trains[$train['train']] == 'のぞみ') {
                $data['nozomi'][$key][] = $tmp;
            } else if ($trains[$train['train']] == 'ひかり') {
                $data['hikari'][$key][] = $tmp;
            } else if ($trains[$train['train']] == 'こだま') {
                $data['kodama'][$key][] = $tmp;
            } else if ($trains[$train['train']] == 'みずほ') {
                $data['mizuho'][$key][] = $tmp;
            } else if ($trains[$train['train']] == 'さくら') {
                $data['sakura'][$key][] = $tmp;
            } else {
                $data['sonota'][$key][] = $tmp;
            }
        }
        $index += 2;
    }
    
    $data['nozomi_teishaeki'] = [];
    
    foreach (['東京', '品川', '新横浜', '名古屋', '京都', '新大阪', '新神戸', '岡山', '広島', '小倉', '博多'] as $item) {
        $tmp = new stdClass();
        $tmp->x = $item;
        $tmp->y = 0;
        $data['nozomi_teishaeki'][] = $tmp;
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
    
    $datasets = [];

    $datasets[] = ['data' => array_reverse($data['station']),
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
                  ];
    
    $pointRotation = $bound_ == 2 ? 270 : 90;
    foreach ($train_name as $item) {
        $datasets[] = ['type' => 'line',
                       'data' => array_reverse($data[$item]['ontime']),
                       'fill' => false,
                       'xAxisID' => 'x-axis-1',
                       'yAxisID' => 'y-axis-0',
                       'showLine' => false,
                       'borderColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : 'black',
                       'backgroundColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : $defines[$item]['color'],
                       'pointStyle' => 'triangle',
                       'pointRadius' => 12,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => $defines[$item]['color'],
                       'pointBorderColor' => 'black',
                       'label' => $defines[$item]['label'] === '' ? '' : $defines[$item]['label'] . ' ' . (count($data[$item]['ontime']) + count($data[$item]['delay'])),
                      ];
    }
    foreach ($train_name as $item) {
        if (count($data[$item]['delay']) > 0) {
            $datasets[] = ['type' => 'line',
                           'data' => array_reverse($data[$item]['delay']),
                           'fill' => false,
                           'xAxisID' => 'x-axis-1',
                           'yAxisID' => 'y-axis-0',
                           'showLine' => false,
                           'borderColor' => 'rgba(0,0,0,0)',
                           'backgroundColor' => 'rgba(0,0,0,0)',
                           'pointStyle' => 'triangle',
                           'pointRadius' => 12,
                           'pointRotation' => $pointRotation,
                           'pointBackgroundColor' => $defines[$item]['color'],
                           'pointBorderColor' => 'cyan',
                           'pointBorderWidth' => 3,
                           'label' => '',
                          ];
        }
    }
    
    $datasets[] = ['type' => 'line',
                   'data' => $data['nozomi_teishaeki'],
                   'fill' => false,
                   'xAxisID' => 'x-axis-0',
                   'yAxisID' => 'y-axis-0',
                   'showLine' => false,
                   'borderColor' => 'rgba(0,0,0,0)',
                   'backgroundColor' => 'rgba(0,0,0,0)',
                   'pointStyle' => 'circle',
                   'pointRadius' => 2,
                   'pointBackgroundColor' => 'black',
                   'pointBorderColor' => 'black',
                   'label' => '',
                  ];
    
    $json = ['type' => 'line',
             'data' => ['labels' => array_reverse($labels),
                        'datasets' => $datasets,
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
    
    $url = 'https://quickchart.io/chart?width=1500&height=210&c=' . urlencode(json_encode($json));
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
