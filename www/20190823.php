<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190823c($mu, '/tmp/dummy');
@unlink('/tmp/dummy');

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190823c($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $color_index['広島'] = 'red,red';
    $color_index['ヤクルト'] = 'cyan,yellowgreen';
    $color_index['巨人'] = 'black,orange';
    $color_index['ＤｅＮＡ'] = 'blue,blue';
    $color_index['中日'] = 'dodgerblue,dodgerblue';
    $color_index['阪神'] = 'yellow,yellow';
    $color_index['西武'] = 'navy,navy';
    $color_index['ソフトバンク'] = 'gold,black';
    $color_index['日本ハム'] = 'darkgray,steelblue';
    $color_index['オリックス'] = 'sandybrown,darkslategray';
    $color_index['ロッテ'] = 'black,silver';
    $color_index['楽天'] = 'darkred,orange';

    // $url = 'https://baseball.yahoo.co.jp/npb/standings/';
    $url = 'https://baseball.yahoo.co.jp/npb/standings/?4nocache' . date('Ymd', strtotime('+9 hours'));;
    $res = $mu_->get_contents($url, null, true);

    $tmp = explode('<table class="NpbPlSt yjM">', $res);

    $rc = preg_match_all('/title="(.+?)"/', $tmp[1] . $tmp[2], $matches);

    $list_team = $matches[1];

    $rc = preg_match_all('/<td>(.+?)</', $tmp[1] . $tmp[2], $matches);

    $gain_sum = 0;
    $gain_min_value = 9999;
    $gain_max_value = 0;
    $loss_sum = 0;
    $loss_min_value = 9999;
    $loss_max_value = 0;
    for ($i = 0; $i < 12; $i++) {
        $gain = (int)$matches[1][$i * 13 + 7];
        $loss = (int)$matches[1][$i * 13 + 8];

        $gain_sum += $gain;
        if ($gain_max_value < $gain) {
            $gain_max_value = $gain;
        }
        if ($gain_min_value > $gain) {
            $gain_min_value = $gain;
        }

        $loss_sum += $loss;
        if ($loss_max_value < $loss) {
            $loss_max_value = $loss;
        }
        if ($loss_min_value > $loss) {
            $loss_min_value = $loss;
        }
    }
    $loss_avg = round($loss_sum / 12);
    $gain_avg = round($gain_sum / 12);
    for ($i = 0; $i < 12; $i++) {
        $tmp1 = new stdClass();
        $tmp1->x = $matches[1][$i * 13 + 7];
        $tmp1->y = $matches[1][$i * 13 + 8];
        $tmp1->r = 7;
        $tmp2 = [];
        $tmp2[] = $tmp1;
        $tmp3 = new stdClass();
        $tmp3->label = $list_team[$i];
        $tmp3->data = $tmp2;
        $tmp3->backgroundColor = explode(',', $color_index[$list_team[$i]])[0];
        $tmp3->borderWidth = 3;
        $tmp3->borderColor = explode(',', $color_index[$list_team[$i]])[1];
        $datasets[] = $tmp3;
    }

    $data2 = [];
    $tmp1 = new stdClass();
    $tmp1->x = $gain_min_value - ($gain_max_value - $gain_min_value);
    $tmp1->y = $tmp1->x;
    $data2[] = $tmp1;
    $tmp1 = new stdClass();
    $tmp1->x = $gain_max_value;
    $tmp1->y = $tmp1->x;
    $data2[] = $tmp1;

    $datasets[] = ['type' => 'scatter',
                   'data' => $data2,
                   'showLine' => true,
                   'borderColor' => 'black',
                   'borderWidth' => 1,
                   'fill' => false,
                   'pointRadius' => 0,
                   'label' => '',
                  ];

    // error_log($log_prefix . print_r($datasets, true));

    $scales = new stdClass();
    $scales->xAxes[] = ['display' => true,
                        'scaleLabel' => ['display' => true,
                                         'labelString' => '得点',
                                         'fontColor' => 'black',
                                        ],
                       ];
    $scales->yAxes[] = ['display' => true,
                        'bottom' => $loss_min_value,
                        'scaleLabel' => ['display' => true,
                                         'labelString' => '失点',
                                         'fontColor' => 'black',
                                        ],
                       ];
    $json = ['type' => 'bubble',
             'data' => ['datasets' => $datasets],
             'options' => ['legend' => ['position' => 'bottom',
                                        'labels' => ['fontSize' => 10,
                                                     'fontColor' => 'black',
                                                     'padding' => 18,
                                                    ],
                                       ],
                           'scales' => $scales,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'vertical',
                                                               'scaleID' => 'x-axis-0',
                                                               'value' => $gain_avg,
                                                               'borderColor' => 'black',
                                                               'borderWidth' => 1,
                                                              ],
                                                              ['type' => 'line',
                                                               'mode' => 'horizontal',
                                                               'scaleID' => 'y-axis-0',
                                                               'value' => $loss_avg,
                                                               'borderColor' => 'black',
                                                               'borderWidth' => 1,
                                                              ],
                                                             ],
                                           ],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                          ],
            ];
    
    
    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 640 360 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    header('Content-Type: image/png');
    echo $res;
    unlink($file);
    
    /*
    $url = 'https://quickchart.io/chart?width=600&height=345&c=' . urlencode(json_encode($data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2 - 25);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2 - 25, imagesx($im1), imagesy($im1) - 50);
    imagedestroy($im1);

    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';

    $mu_->post_blog_hatena('Score Map', $description);
    $mu_->post_blog_fc2_async('Score Map', $description);

    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>Score Map</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);

    error_log($log_prefix . 'END');
    return $url_length;
    */
}

function func_20190823b($mu_, $file_name_rss_items_)
{
    $log_prefix = $mu_->logging_function_begin(__METHOD__);

    for ($i = 0; $i < (int)date('t'); $i++) {
        $labels[] = $i + 1;
        $tmp = new stdClass();
        $tmp->x = $i + 1;
        $tmp->y = ((int)date('t') - $i) * 24;
        $data1[] = $tmp;
    }

    $datasets = [];
    $datasets[] = ['data' => $data1,
                   'fill' => false,
                   'lineTension' => 0,
                   'pointStyle' => 'line',
                   'backgroundColor' => 'black',
                   'borderColor' => 'black',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'label' => 'max',
                  ];

    $list = [['target' => 'toodledo',
              'color' => 'green',
             ],
             ['target' => 'ttrss',
              'color' => 'deepskyblue',
             ],
             ['target' => 'redmine',
              'color' => 'blue',
             ],
             ['target' => 'first',
              'color' => 'red',
             ],
             ['target' => 'kyoto',
              'color' => 'orange',
             ],
             ['target' => 'toodledo2',
              'color' => 'deeppink',
             ],
            ];

    $sql = <<< __HEREDOC__
SELECT T1.value
  FROM t_data_log T1
 WHERE T1.key = :b_key
__HEREDOC__;

    $pdo = $mu_->get_pdo();
    $statement = $pdo->prepare($sql);

    foreach ($list as $one_data) {
        error_log(print_r($one_data, true));
        $statement->execute([':b_key' => strtoupper($one_data['target'])]);
        $result = $statement->fetchAll();
        $quotas = json_decode($result[0]['value'], true);
        error_log(print_r($quotas, true));

        $data2 = [];
        foreach ($quotas as $key => $value) {
            $tmp = new stdClass();
            $tmp->x = (int)substr($key, -2) - 1;
            $tmp->y = (int)($value / 3600);
            $data2[] = $tmp;
        }

        if (count($data2) < 3) {
            return 0;
        }
        if ($data2[0]->x == 0) {
            array_shift($data2);
            $tmp = new stdClass();
            $tmp->x = 1;
            $tmp->y = 550;
            $data2[0] = $tmp;
        }

        $datasets[] = ['data' => $data2,
                       'fill' => false,
                       'lineTension' => 0,
                       'pointStyle' => 'circle',
                       'backgroundColor' => $one_data['color'],
                       'borderColor' => $one_data['color'],
                       'borderWidth' => 2,
                       'pointRadius' => 3,
                       'pointBorderWidth' => 0,
                       'label' => $one_data['target'],
                      ];

        $data3 = [];
        $tmp = new stdClass();
        $tmp->x = 1;
        $tmp->y = 550;
        $data3[] = $tmp;
        $tmp = new stdClass();
        $tmp->x = (int)date('t');
        $tmp->y = 550 - (int)((550 - end($data2)->y) / end($data2)->x + 1) * (int)date('t');
        $data3[] = $tmp;

        $datasets[] = ['data' => $data3,
                       'fill' => false,
                       'lineTension' => 0,
                       'backgroundColor' => $one_data['color'],
                       'borderWidth' => 1,
                       'borderColor' => $one_data['color'],
                       'pointRadius' => 0,
                       // 'label' => 'plan',
                       'label' => '',
                      ];
    }

    $pdo = null;

    $scales = new stdClass();
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'ticks' => ['autoSkip' => false,
                                    'fontSize' => 10,
                                   ],
                       ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['display' => true,
                                        'labels' => ['boxWidth' => 6,
                                                     'fontColor' => 'black',
                                                    ],
                                       ],
                           'animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'vertical',
                                                               'scaleID' => 'x-axis-0',
                                                               'value' => count($datasets[1]['data']),
                                                              ],
                                                             ],
                                           ],
                           'scales' => $scales,
                          ],
            ];
    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 720 320 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    header('content-type: image/png');
    echo $res;

    error_log($log_prefix . 'END');
    return 0;
}

function func_20190823a($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $urls = [];
    for ($i = 0; $i < 20; $i++) {
        $url = $mu_->get_env('URL_RAKUTEN_TRAVEL_' . str_pad($i, 2, '0', STR_PAD_LEFT));
        if (strlen($url) < 10) {
            continue;
        }
        $urls[] = $url;
    }
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAXCONNECTS => 8,
    ];
    $results = $mu_->get_contents_proxy_multi($urls, $multi_options);
    error_log(count($results));

    return;
    
    foreach ($results as $url => $result) {
        $hash_url = 'url' . hash('sha512', $url);
        error_log($log_prefix . "url hash : ${hash_url}");

        parse_str(parse_url($url, PHP_URL_QUERY), $tmp);

        $y = $tmp['f_nen1'];
        $m = $tmp['f_tuki1'];
        $d = $tmp['f_hi1'];

        $info = "\n\n${y}/${m}/${d}\n";

        $tmp = explode('<dl class="htlGnrlInfo">', $result);
        array_shift($tmp);

        foreach ($tmp as $hotel_info) {
            $rc = preg_match('/<a id.+>(.+?)</', $hotel_info, $match);
            // error_log($match[1]);
            $info .= $match[1];
            $rc = preg_match('/<span class="vPrice".*?>(.+)/', $hotel_info, $match);
            // error_log(strip_tags($match[1]));
            $info .= ' ' . strip_tags($match[1]) . "\n";
        }

        $hash_info = hash('sha512', $info);
        error_log($log_prefix . "info hash : ${hash_info}");

        $res = $mu_->search_blog($hash_url);
        if ($res != $hash_info) {
            $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
            $description = '<div class="' . $hash_url . '">' . "${hash_info}</div>${info}";
            $mu_->post_blog_wordpress($hash_url, $description, 'hotel');
        }
    }
    $results = null;
}
