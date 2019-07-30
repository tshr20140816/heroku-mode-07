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

    $rc = preg_match('/"station": {(.+?)}/s', $tmp, $match);
    $stations = json_decode('{' . $match[1] . '}', true);
    error_log($log_prefix . print_r($stations, true));

    $rc = preg_match('/"train": {(.+?)}/s', $tmp, $match);
    $trains = json_decode('{' . $match[1] . '}', true);
    error_log($log_prefix . print_r($trains, true));

    $tmp = explode('</script>', $train_location_info_);
    $tmp = json_decode(trim(end($tmp)), true);
    $dt = $tmp['trainLocationInfo']['datetime'] + 32400; // +9 hours
    $atStations = $tmp['trainLocationInfo']['atStation']['bounds'];
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
        if (in_array($item, ['東京', '品川', '新横浜', '名古屋', '京都', '新大阪', '新神戸', '岡山', '広島', '小倉', '博多'], true)) {
            $tmp = new stdClass();
            $tmp->x = $item;
            $tmp->y = 0;
            $data['station'][] = $tmp;
        }
    }
    array_shift($tmp_labels);
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

    for ($i = 0; $i < 2; $i++) {
        $index = 0;
        if ($i === 0) {
            $target = $atStations[$bound_]; // eki
        } else {
            $target = $betweenStations[$bound_]; // ekikan
        }
        foreach ($target as $item) {
            error_log($log_prefix . $stations[$item['station']]);
            $level = 0;
            foreach ($item['trains'] as $train) {
                error_log($log_prefix . $trains[$train['train']] . ' ' . $train['trainNumber'] . ' ' . $train['delay']);
                if ($max_y < $level) {
                    $max_y = $level;
                }
                $tmp = new stdClass();
                $tmp->x = (string)($i === 0 ? $index : ($bound_ == 2 ? $index + 1 : $index - 1));
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
                                    'fontFamily' => 'IPAexGothic',
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
                   // 'yAxisID' => 'y-axis-0',
                   'pointRadius' => 0,
                   'showLine' => false,
                   'borderColor' => 'rgba(0,0,0,0)',
                   'backgroundColor' => 'rgba(0,0,0,0)',
                   'pointStyle' => 'circle',
                   'pointRadius' => 2,
                   'pointBackgroundColor' => 'black',
                   'pointBorderColor' => 'black',
                   'label' => ($bound_ === 1 ? '<上り> ' : '<下り> ') . date('Y/m/d H:i', $dt),
                  ];

    $pointRotation = $bound_ == 2 ? 270 : 90;
    foreach ($train_name as $item) {
        $count = count($data[$item]['ontime']) + count($data[$item]['delay']);
        $datasets[] = ['data' => array_reverse($data[$item]['ontime']),
                       'fill' => false,
                       'xAxisID' => 'x-axis-1',
                       // 'yAxisID' => 'y-axis-0',
                       'showLine' => false,
                       'borderColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : 'black',
                       'backgroundColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : $defines[$item]['color'],
                       'pointStyle' => 'triangle',
                       'pointRadius' => 12,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => $defines[$item]['color'],
                       'pointBorderColor' => 'black',
                       'label' => $defines[$item]['label'] === '' ? '' : $defines[$item]['label'] . " ${count}",
                      ];
    }
    foreach ($train_name as $item) {
        if (count($data[$item]['delay']) > 0) {
            $datasets[] = ['data' => array_reverse($data[$item]['delay']),
                           'fill' => false,
                           'xAxisID' => 'x-axis-1',
                           // 'yAxisID' => 'y-axis-0',
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

    $json = ['type' => 'line',
             'data' => ['labels' => array_reverse($labels),
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['labels' => ['fontColor' => 'black',
                                                     'fontFamily' => 'IPAexGothic',
                                                    ],
                                       ],
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

    /*
    $url = 'https://quickchart.io/chart?width=1500&height=210&c=' . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    error_log($log_prefix . strlen($url));
    */
    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/20190730.js 1500 210 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    /*
    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2 * 1.5, imagesy($im1) / 2 * 1.5);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2 * 1.5, imagesy($im1) / 2 * 1.5, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);
    $res = file_get_contents($file);
    unlink($file);
    */

    return $res;
}
