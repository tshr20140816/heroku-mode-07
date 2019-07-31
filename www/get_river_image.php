<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$file_name_rss_items = tempnam('/tmp', 'rss_' . md5(microtime(true)));
@unlink($file_name_rss_items);

get_river_image($mu, $file_name_rss_items);
get_river_water_level($mu, $file_name_rss_items, $mu->get_env('URL_RIVER_YAHOO_1'), $mu->get_env('RIVER_POINT_1'));
get_river_water_level($mu, $file_name_rss_items, $mu->get_env('URL_RIVER_YAHOO_2'), $mu->get_env('RIVER_POINT_2'));
get_shinkansen_image($mu, $file_name_rss_items);
get_train_sanyo2_2($mu, $file_name_rss_items);

$xml_text = <<< __HEREDOC__
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">
<channel>
<title>River Now</title>
<link>http://dummy.local/</link>
<description>River Now</description>
__ITEMS__
</channel>
</rss>
__HEREDOC__;

$file = '/tmp/' . getenv('FC2_RSS_05') . '.xml';
file_put_contents($file, str_replace('__ITEMS__', file_get_contents($file_name_rss_items), $xml_text));
$mu->upload_fc2($file);
unlink($file);
unlink($file_name_rss_items);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function get_river_image($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = $mu_->get_env('URL_RIVER_IMAGE_1') . '?' . hash('md5', microtime(true));
    $res = $mu_->get_contents($url);

    // error_log($res);
    $rc = preg_match('/<img alt="最新監視カメラ画像".+? src="(.+?)"/s', $res, $match);
    error_log($log_prefix . print_r($match, true));
    $url = 'http://' . parse_url($url, PHP_URL_HOST) . $match[1];

    $rc = preg_match('/.+?(\d+\/\d+ \d+:\d+).+?<td>(.+?)<img alt="上昇率" /s', $res, $match);
    $description = trim(strip_tags($match[1])) . ' ' . trim(strip_tags($match[2])) . 'm<br />';

    $res = $mu_->get_contents($url);
    $description .= '<img src="data:image/jpeg;base64,' . base64_encode($res) . '" />';
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>River Image</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);
}

function get_river_water_level($mu_, $file_name_rss_items_, $url_, $point_)
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

    $url = $url_;
    $res = $mu_->get_contents($url, $options);

    $rc = preg_match("/common.riverData = JSON.parse\('(.+)'/", $res, $match);
    $json = json_decode($match[1], true);
    error_log($log_prefix . print_r($json, true));
    $title = $json['RiverName'];

    $rc = preg_match("/common.obsData = JSON.parse\('(.+)'/", $res, $match);
    $json = json_decode($match[1], true);
    error_log($log_prefix . print_r($json, true));

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
    exec('node ../scripts/chartjs_node.js 300 160 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);
    
    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>River Image</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);
}

function get_shinkansen_image($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://traininfo.jr-central.co.jp/shinkansen/common/data/common_ja.json';
    $res_common_ja = $mu_->get_contents_proxy($url);

    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json?' . microtime(true);
    $res_train_location_info = $mu_->get_contents_proxy($url);

    $res1 = get_shinkansen_info($mu_, $res_common_ja, $res_train_location_info, 1);
    $res2 = get_shinkansen_info($mu_, $res_common_ja, $res_train_location_info, 2);

    $im1 = imagecreatetruecolor(1000, 320);
    // imagealphablending($im1, false);
    // imagesavealpha($im1, true);
    imagefill($im1, 0, 0, imagecolorallocate($im1, 255, 255, 255));

    $im2 = imagecreatefromstring($res1);
    imagecopy($im1, $im2, 0, 0, 0, 0, 1000, 160);
    imagedestroy($im2);

    $im2 = imagecreatefromstring($res2);
    imagecopy($im1, $im2, 0, 160, 0, 0, 1000, 160);
    imagedestroy($im2);

    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im1, $file, 9);
    imagedestroy($im1);

    $res = file_get_contents($file);
    unlink($file);
    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>SHINKANSEN</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);
}

// $bound_ : 1 上り 2 下り
function get_shinkansen_info($mu_, $common_ja_, $train_location_info_, $bound_ = 2)
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
                                    'fontSize' => 9,
                                    'autoSkip' => false,
                                    'minRotation' => 45,
                                    'maxRotation' => 45,
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
    // on time
    foreach ($train_name as $item) {
        $count = count($data[$item]['ontime']) + count($data[$item]['delay']);
        $datasets[] = ['data' => array_reverse($data[$item]['ontime']),
                       'fill' => false,
                       'xAxisID' => 'x-axis-1',
                       'showLine' => false,
                       'borderColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : 'black',
                       'backgroundColor' => $defines[$item]['label'] === '' ? 'rgba(0,0,0,0)' : $defines[$item]['color'],
                       'pointStyle' => 'triangle',
                       'pointRadius' => 8,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => $defines[$item]['color'],
                       'pointBorderColor' => 'black',
                       'label' => $defines[$item]['label'] === '' ? '' : $defines[$item]['label'] . " ${count}",
                      ];
    }
    // delay
    foreach ($train_name as $item) {
        if (count($data[$item]['delay']) > 0) {
            $datasets[] = ['data' => array_reverse($data[$item]['delay']),
                           'fill' => false,
                           'xAxisID' => 'x-axis-1',
                           'showLine' => false,
                           'borderColor' => 'rgba(0,0,0,0)',
                           'backgroundColor' => 'rgba(0,0,0,0)',
                           'pointStyle' => 'triangle',
                           'pointRadius' => 8,
                           'pointRotation' => $pointRotation,
                           'pointBackgroundColor' => $defines[$item]['color'],
                           'pointBorderColor' => 'cyan',
                           'pointBorderWidth' => 2,
                           'label' => '',
                          ];
        }
    }

    $json = ['type' => 'line',
             'data' => ['labels' => array_reverse($labels),
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['labels' => ['fontColor' => 'black',
                                                     'fontSize' => 9,
                                                    ],],
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
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 1000 160 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);
    
    return $res;
}

function get_train_sanyo2_2($mu_, $file_name_rss_items_) {
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    $sanyo2_st = $mu_->get_contents($url, null, true);

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';
    $sanyo2 = $mu_->get_contents($url);

    // error_log($log_prefix . print_r(json_decode($sanyo2_st, true), true));
    // error_log($log_prefix . print_r(json_decode($sanyo2, true), true));

    $res1 = get_train_sanyo2_image2($mu_, $sanyo2_st, $sanyo2, '1');
    $res2 = get_train_sanyo2_image2($mu_, $sanyo2_st, $sanyo2, '0');

    $im1 = imagecreatefromstring($res1);
    $x = imagesx($im1);
    $y1 = imagesy($im1);
    imagedestroy($im1);

    $im1 = imagecreatefromstring($res2);
    // $x = imagesx($im1);
    $y2 = imagesy($im1);
    imagedestroy($im1);

    $im1 = imagecreatetruecolor($x, $y1 + $y2);
    imagefill($im1, 0, 0, imagecolorallocate($im1, 255, 255, 255));

    $im2 = imagecreatefromstring($res1);
    imagecopy($im1, $im2, 0, 0, 0, 0, $x, $y1);
    imagedestroy($im2);

    $im2 = imagecreatefromstring($res2);
    imagecopy($im1, $im2, 0, $y1, 0, 0, $x, $y2);
    imagedestroy($im2);

    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im1, $file, 9);
    imagedestroy($im1);
    $res = file_get_contents($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>SANYO2</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);
}

function get_train_sanyo2_image2($mu_, $sanyo2_st_, $sanyo2_, $direction_ = '0') // $direction_ : '0' nobori / '1' kudari
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $stations = [];
    $index = 0;
    $labels['station'] = [];
    foreach (json_decode($sanyo2_st_, true)['stations'] as $station) {
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

    // error_log($log_prefix . print_r($labels, true));
    // error_log($log_prefix . print_r($stations, true));

    $json = json_decode($sanyo2_, true);

    $data = [];
    $data['ontime'] = [];
    $data['delay'] = [];
    $data['ontime_etc'] = [];
    $data['delay_etc'] = [];
    $y_max = 0;
    foreach ($json['trains'] as $train) {
        if ($train['direction'] == $direction_) {
            $tmp = new stdClass();
            $pos = explode('_', $train['pos']);
            if ($pos[1] === '####') {
                $tmp->x = (string)$stations[$pos[0]]['index'];
            } else {
                $tmp->x = (string)($stations[$pos[0]]['index'] + ($direction_ === '0' ? -1 : 1));
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
            foreach ($data['ontime_etc'] as $std) {
                if ($std->x === $tmp->x && $std->y >= $y) {
                    $y = $std->y + 1;
                }
            }
            foreach ($data['delay_etc'] as $std) {
                if ($std->x === $tmp->x && $std->y >= $y) {
                    $y = $std->y + 1;
                }
            }
            $tmp->y = $y;
            if ($y > $y_max) {
                $y_max = $y;
            }
            $dest = $train['dest'];
            if ($dest === ($direction_ === '0' ? '糸崎' : '岩国')) {
                $dest = '★';
            }
            if ((int)$tmp->x === 0) {
                $dest = str_repeat('　', mb_strlen($dest)) . $dest;
            } else if ((int)$tmp->x === (count($labels['station']) - 1)) {
                $dest .= str_repeat('　', mb_strlen($dest));
            }
            if ($train['delayMinutes'] != '0') {
                if ($train['notice'] != '' || $train['displayType'] != '普通') {
                    $data['delay_etc'][] = $tmp;
                } else {
                    $data['delay'][] = $tmp;
                }
                $labels['dest'][(int)$tmp->x] .= "\n" . $dest . $train['delayMinutes'];
            } else {
                if ($train['notice'] != '' || $train['displayType'] != '普通') {
                    $data['ontime_etc'][] = $tmp;
                } else {
                    $data['ontime'][] = $tmp;
                }
                $labels['dest'][(int)$tmp->x] .= "\n" . $dest;
            }
        }
    }
    // error_log($log_prefix . print_r($data, true));
    // error_log($log_prefix . print_r($labels, true));

    $pointRotation = $direction_ === '0' ? 270 : 90;

    $datasets[] = ['data' => $data['ontime'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'lightgray',
                   'pointBorderColor' => 'red',
                   'pointBorderWidth' => 2,
                  ];

    $datasets[] = ['data' => $data['delay'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'lightgray',
                   'pointBorderColor' => 'cyan',
                   'pointBorderWidth' => 3,
                  ];

    $datasets[] = ['data' => $data['ontime_etc'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'yellow',
                   'pointBorderColor' => 'red',
                   'pointBorderWidth' => 2,
                  ];

    $datasets[] = ['data' => $data['delay_etc'],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-0',
                   'showLine' => false,
                   'pointStyle' => 'triangle',
                   'pointRadius' => 12,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'yellow',
                   'pointBorderColor' => 'cyan',
                   'pointBorderWidth' => 3,
                  ];

    $tmp = new stdClass();
    $tmp->x = $direction_ === '0' ? '糸崎' : '岩国';
    $tmp->y = 0;

    $datasets[] = ['data' => [$tmp, ],
                   'fill' => false,
                   'showLine' => false,
                   'xAxisID' => 'x-axis-1',
                   'showLine' => false,
                   'pointStyle' => 'circle',
                   'pointRadius' => 3,
                   'pointBackgroundColor' => 'black',
                   'pointBorderColor' => 'black',
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
                                    'autoSkip' => false,
                                    'minRotation' => 45,
                                    'maxRotation' => 45,
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => false,
                        'ticks' => ['max' => $y_max + 1,
                                    'min' => 0,
                                   ],
                       ];

    $annotations = [];
    for ($i = 0; $i < count($labels['dest']); $i++) {
        if ($labels['dest'][$i] !== '') {
            $tmp = explode("\n", ltrim($labels['dest'][$i]), 2);
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
    $annotations[] = ['type' => 'line',
                      'mode' => 'vertical',
                      'scaleID' => 'x-axis-1',
                      'value' => $direction_ === '0' ? '五日市' : '海田市',
                      'borderColor' => 'rgba(255,100,100,200)',
                      'borderWidth' => 3,
                     ];

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
    $height = 150;
    if ($y_max > 2) {
        $height = 210;
    }
    $url = "https://quickchart.io/chart?width=1500&height=${height}&c=" . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    error_log($log_prefix . 'URL length : ' . number_format(strlen($url)));

    $im1 = imagecreatefromstring($res);
    // error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 3, imagesy($im1) / 3);
    imagefill($im2, 0, 0, imagecolorallocate($im1, 255, 255, 255));
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 3, imagesy($im1) / 3, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);
    $res = file_get_contents($file);
    unlink($file);

    return $res;
}
