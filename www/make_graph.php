<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$file_name_rss_items = tempnam('/tmp', 'rss_' . md5(microtime(true)));
@unlink($file_name_rss_items);

$url_length = [];

$url_length['make_waon_balance'] = make_waon_balance($mu, $file_name_rss_items);
// $url_length['make_heroku_dyno_usage_graph'] = make_heroku_dyno_usage_graph($mu, $file_name_rss_items);
// $url_length['make_heroku_dyno_usage_graph2'] = make_heroku_dyno_usage_graph2($mu, $file_name_rss_items);
$url_length['make_heroku_dyno_usage_graph3'] = make_heroku_dyno_usage_graph3($mu, $file_name_rss_items);
// $url_length['make_database1'] = make_database($mu, $file_name_rss_items, 1);
// $url_length['make_database2'] = make_database($mu, $file_name_rss_items, 2);
$url_length['make_database3'] = make_database3($mu, $file_name_rss_items);
$url_length['make_process_time'] = make_process_time($mu, $file_name_rss_items);
$url_length['make_post_count'] = make_post_count($mu, $file_name_rss_items);
$url_length['make_github_contributions'] = make_github_contributions($mu, $file_name_rss_items);
$url_length['make_storage_usage'] = make_storage_usage($mu, $file_name_rss_items);
$url_length['make_loggly_usage'] = make_loggly_usage($mu, $file_name_rss_items);
$url_length['make_score_map'] = make_score_map($mu, $file_name_rss_items);
$url_length['npb_team_ranking'] = npb_team_ranking($mu, $file_name_rss_items);

$xml_text = <<< __HEREDOC__
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">
<channel>
<title>Graph</title>
<link>http://dummy.local/</link>
<description>Graph</description>
__ITEMS__
</channel>
</rss>
__HEREDOC__;

$file = '/tmp/' . getenv('FC2_RSS_03') . '.xml';
file_put_contents($file, str_replace('__ITEMS__', file_get_contents($file_name_rss_items), $xml_text));
$filesize = filesize($file);
$mu->upload_fc2($file);
unlink($file);
unlink($file_name_rss_items);

$description = '';
foreach ($url_length as $method_name => $length) {
    $description .= "${method_name} : " . number_format($length) . "\n";
}

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/backup_cloudapp.php';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

$time_finish = microtime(true);
$mu->post_blog_wordpress(
    "${requesturi} [" . substr(($time_finish - $time_start), 0, 6) . 's]',
    'file size : ' . number_format($filesize) . "byte\n\nLimit 1MB\n\n" . $description);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function make_score_map($mu_, $file_name_rss_items_)
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
    $tmp1->x = floor(($gain_min_value > $loss_min_value ? $gain_min_value : $loss_min_value) / 10) * 10;
    $tmp1->y = $tmp1->x;
    $data2[] = $tmp1;
    $tmp1 = new stdClass();
    $tmp1->x = ceil(($gain_max_value > $loss_max_value ? $gain_max_value : $loss_max_value) / 10) * 10;
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
    $data = ['type' => 'bubble',
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
}

function make_loggly_usage($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $cookie = tempnam('/tmp', md5(microtime(true)));

    for ($i = 0; $i < 5; $i++) {
        $options = [
            CURLOPT_COOKIEJAR => $cookie,
            CURLOPT_COOKIEFILE => $cookie,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true,
        ];

        $url = $mu_->get_env('URL_LOGGLY_USAGE');
        $res = $mu_->get_contents($url, $options);

        $rc = preg_match('/location: (.+)/i', $res, $match);

        if ($rc != 1) {
            continue;
        }

        $url = 'https://my.solarwinds.cloud/v1/login';

        $json = ['email' => $mu_->get_env('LOGGLY_ID', true),
                 'loginQueryParams' => parse_url(trim($match[1]), PHP_URL_QUERY),
                 'password' => $mu_->get_env('LOGGLY_PASSWORD', true),
                ];

        $options = [
            CURLOPT_COOKIEJAR => $cookie,
            CURLOPT_COOKIEFILE => $cookie,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['content-type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($json),
        ];

        $res = $mu_->get_contents($url, $options);

        if ($res == '500') {
            continue;
        }

        $url = json_decode($res)->redirectUrl;

        $options = [
            CURLOPT_COOKIEJAR => $cookie,
            CURLOPT_COOKIEFILE => $cookie,
        ];

        $res = $mu_->get_contents($url, $options);
        // error_log($log_prefix . print_r(json_decode($res)->total, true));

        if (strlen($res) > 3) {
            break;
        }
    }
    unlink($cookie);

    if (strlen($res) == 3) {
        return 0;
    }

    foreach (json_decode($res)->total as $item) {
        error_log($log_prefix . date('m/d', $item[0] / 1000) . ' ' . round($item[1] / 1024 / 1024) . 'MB');
        $labels[] = date('d', $item[0] / 1000);
        $data[] = round($item[1] / 1024 / 1024);
    }

    $scales = new stdClass();
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'ticks' => ['fontColor' => 'black',
                                    'autoSkip' => false,
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'ticks' => ['fontColor' => 'black',
                                   ],
                       ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data,
                                        'fill' => false,
                                        'lineTension' => 0,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'horizontal',
                                                               'scaleID' => 'y-axis-0',
                                                               'value' => 200,
                                                               'borderColor' => 'red',
                                                               'borderWidth' => 1,
                                                              ],
                                                             ],
                                           ],
                           'scales' => $scales,
                          ],
            ];

    /*
    $url = 'https://quickchart.io/chart?width=600&height=320&c=' . urlencode(json_encode($data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file, true);

    unlink($file);
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 320 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';

    $mu_->post_blog_hatena('Loggly usage', $description);
    $mu_->post_blog_fc2_async('Loggly usage', $description);

    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>Loggly usage</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);

    // return $url_length;
    error_log($log_prefix . 'END');
    return 0;
}

function make_heroku_dyno_usage_graph($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

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
                   'pointStyle' => 'line',
                   'backgroundColor' => 'black',
                   'borderColor' => 'black',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'label' => 'max',
                  ];

    $list = [['target' => 'toodledo',
              'color' => 'green',
              'planColor' => 'red',
             ],
             ['target' => 'ttrss',
              'color' => 'deepskyblue',
              'planColor' => 'orange',
             ],
             ['target' => 'redmine',
              'color' => 'blue',
              'planColor' => 'yellow',
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
                       'pointStyle' => 'circle',
                       'backgroundColor' => $one_data['color'],
                       'borderColor' => $one_data['color'],
                       'borderWidth' => 3,
                       'pointRadius' => 4,
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
                       'backgroundColor' => $one_data['planColor'],
                       'borderWidth' => 3,
                       'borderColor' => $one_data['planColor'],
                       'pointRadius' => 0,
                       'label' => $one_data['target'] . ' plan',
                      ];
    }

    $pdo = null;

    $chart_data = ['type' => 'line',
                   'data' => ['labels' => $labels,
                              'datasets' => $datasets,
                             ],
                   'options' => ['legend' => ['display' => true,
                                              'labels' => ['boxWidth' => 10,
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
                                ],
                  ];
    $url = 'https://quickchart.io/chart?width=900&height=480&c=' . urlencode(json_encode($chart_data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

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

    $res = $mu_->shrink_image($file);

    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    $mu_->post_blog_hatena('heroku dyno usage', $description);
    $mu_->post_blog_fc2_async('heroku dyno usage', $description);
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>heroku dyno usage</title>
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
}

function make_heroku_dyno_usage_graph2($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

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
                   'pointStyle' => 'line',
                   'backgroundColor' => 'black',
                   'borderColor' => 'black',
                   'borderWidth' => 1,
                   'pointRadius' => 0,
                   'label' => 'max',
                  ];

    $list = [['target' => 'first',
              'color' => 'green',
              'planColor' => 'red',
             ],
             ['target' => 'kyoto',
              'color' => 'deepskyblue',
              'planColor' => 'orange',
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
                       'pointStyle' => 'circle',
                       'backgroundColor' => $one_data['color'],
                       'borderColor' => $one_data['color'],
                       'borderWidth' => 3,
                       'pointRadius' => 4,
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
                       'backgroundColor' => $one_data['planColor'],
                       'borderWidth' => 3,
                       'borderColor' => $one_data['planColor'],
                       'pointRadius' => 0,
                       'label' => $one_data['target'] . ' plan',
                      ];
    }

    $pdo = null;

    $chart_data = ['type' => 'line',
                   'data' => ['labels' => $labels,
                              'datasets' => $datasets,
                             ],
                   'options' => ['legend' => ['display' => true,
                                              'labels' => ['boxWidth' => 10,
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
                                ],
                  ];
    $url = 'https://quickchart.io/chart?width=900&height=480&c=' . urlencode(json_encode($chart_data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

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

    $res = $mu_->shrink_image($file);

    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    // $mu_->post_blog_hatena('heroku dyno usage 2', $description);
    // $mu_->post_blog_fc2_async('heroku dyno usage 2', $description);
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>heroku dyno usage 2</title>
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
}

function make_waon_balance($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $sql = <<< __HEREDOC__
SELECT to_char(T1.check_time, 'YYYY/MM/DD') check_date
      ,MIN(T1.balance) balance
  FROM t_waon_history T1
 GROUP BY to_char(T1.check_time, 'YYYY/MM/DD')
 ORDER BY to_char(T1.check_time, 'YYYY/MM/DD') DESC
 LIMIT 20
;
__HEREDOC__;

    $pdo = $mu_->get_pdo();

    $labels = [];
    $data1 = [];
    foreach ($pdo->query($sql) as $row) {
        $labels[$row['check_date']] = date('m/d', strtotime($row['check_date']));
        $tmp = new stdClass();
        $tmp->x = date('m/d', strtotime($row['check_date']));
        $tmp->y = $row['balance'];
        $data1[] = $tmp;
    }
    $pdo = null;

    ksort($labels);
    $labels = array_values($labels);

    $datasets = [];

    $datasets[] = ['data' => $data1,
                   'fill' => false,
                   'lineTension' => 0,
                   'pointStyle' => 'circle',
                   'backgroundColor' => 'deepskyblue',
                   'borderColor' => 'deepskyblue',
                   'borderWidth' => 3,
                   'pointRadius' => 4,
                   'pointBorderWidth' => 0,
                  ];

    $scales = new stdClass();
    $scales->yAxes[] = ['display' => true,
                        'ticks' => ['callback' => 'function(value){return value.toLocaleString();}',],
                       ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['display' => false,
                                       ],
                           'animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                           'annotation' => ['annotations' => [['type' => 'line',
                                                               'mode' => 'horizontal',
                                                               'scaleID' => 'y-axis-0',
                                                               'value' => $data1[0]->y,
                                                               'borderColor' => 'rgba(0,0,0,0)',
                                                               'borderWidth' => 1,
                                                               'label' => ['enabled' => true,
                                                                           'content' => number_format($data1[0]->y),
                                                                           'position' => 'left',
                                                                          ],
                                                              ],
                                                             ],
                                           ],
                           'scales' => $scales,
                          ],
            ];

    // $json = str_replace('"__CALLBACK__"', '"function(value){return value.toLocaleString();}"', json_encode($json));

    /*
    $url = 'https://quickchart.io/chart?width=600&height=360&c=' . urlencode($tmp);
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);

    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 320 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    $mu_->post_blog_hatena('waon balance', $description);
    $mu_->post_blog_fc2_async('waon balance', $description);
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>waon balance</title>
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
}

function make_database($mu_, $file_name_rss_items_, $pattern_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    for ($i = 0; $i < (int)date('t'); $i++) {
        $labels[] = $i + 1;
    }

    $datasets = [];

    switch ($pattern_) {
        case 1:
            $list = [['target' => 'toodledo',
                      'color' => 'green',
                      'size_color' => 'red',
                     ],
                     ['target' => 'redmine',
                      'color' => 'blue',
                      'size_color' => 'yellow',
                     ],
                    ];
            break;
        case 2:
            $list = [['target' => 'ttrss',
                      'color' => 'deepskyblue',
                      'size_color' => 'orange',
                     ],
                    ];
            break;
    }

    $annotations = [];
    $level = 10000;
    foreach ($list as $one_data) {
        error_log(print_r($one_data, true));
        $keyword = strtolower($one_data['target']);
        for ($i = 0; $i < strlen($keyword); $i++) {
            $keyword[$i] = chr(ord($keyword[$i]) + 1);
        }

        $res = $mu_->search_blog($keyword . 'sfdpsedpvou');

        $data2 = [];
        foreach (explode(' ', $res) as $item) {
            $tmp1 = explode(',', $item);
            $tmp2 = new stdClass();
            $tmp2->x = (int)$tmp1[0];
            $tmp2->y = (int)$tmp1[1];
            $data2[] = $tmp2;
        }

        if (count($data2) < 2) {
            return 0;
        }

        $level -= 1000;
        $annotations[] = ['type' => 'line',
                          'mode' => 'horizontal',
                          'scaleID' => 'y-axis-0',
                          'value' => $level,
                          'borderColor' => 'rgba(0,0,0,0)',
                          // 'borderWidth' => 1,
                          'label' => ['enabled' => true,
                                      'content' => number_format(end($data2)->y),
                                      'position' => 'left',
                                      'backgroundColor' => $one_data['color'],
                                     ],
                         ];

        $datasets[] = ['data' => $data2,
                       'fill' => false,
                       'pointStyle' => 'circle',
                       'backgroundColor' => $one_data['color'],
                       'borderColor' => $one_data['color'],
                       'borderWidth' => 3,
                       'pointRadius' => 4,
                       'pointBorderWidth' => 0,
                       'label' => $one_data['target'] . ' record',
                       'yAxisID' => 'y-axis-0',
                      ];

        $res = $mu_->search_blog($keyword . 'ebubcbtftjaf');

        $data3 = [];
        foreach (explode(' ', $res) as $item) {
            $tmp1 = explode(',', $item);
            $tmp2 = new stdClass();
            $tmp2->x = (int)$tmp1[0];
            $tmp2->y = ceil((int)$tmp1[1] / 1024 / 1024);
            $data3[] = $tmp2;
        }

        $annotations[] = ['type' => 'line',
                          'mode' => 'horizontal',
                          'scaleID' => 'y-axis-0',
                          'value' => $level,
                          'borderColor' => 'rgba(0,0,0,0)',
                          // 'borderWidth' => 1,
                          'label' => ['enabled' => true,
                                      'content' => number_format(end($data3)->y),
                                      'position' => 'right',
                                      'backgroundColor' => $one_data['size_color'],
                                      'fontColor' => 'black',
                                     ],
                         ];

        $datasets[] = ['data' => $data3,
                       'fill' => false,
                       'pointStyle' => 'star',
                       'backgroundColor' => $one_data['size_color'],
                       'borderColor' => $one_data['size_color'],
                       'borderWidth' => 2,
                       'pointRadius' => 3,
                       'pointBorderWidth' => 0,
                       'label' => 'size',
                       'yAxisID' => 'y-axis-1',
                      ];
    }

    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                        // 'type' => 'linear',
                        'ticks' => ['callback' => '__CALLBACK_1__',],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        // 'type' => 'linear',
                        'ticks' => ['callback' => '__CALLBACK_2__',],
                       ];

    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => 0,
                      'borderColor' => 'rgba(0,0,0,0)',
                      // 'borderWidth' => 1,
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => 10000,
                      'borderColor' => 'red',
                      // 'borderWidth' => 1,
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-1',
                      'value' => 0,
                      'borderColor' => 'rgba(0,0,0,0)',
                      // 'borderWidth' => 1,
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-1',
                      'value' => 1000,
                      'borderColor' => 'rgba(0,0,0,0)',
                      // 'borderWidth' => 1,
                     ];

    $chart_data = ['type' => 'line',
                   'data' => ['labels' => $labels,
                              'datasets' => $datasets,
                             ],
                   'options' => ['legend' => [// 'display' => true,
                                              'labels' => ['usePointStyle' => true
                                                          ],
                                             ],
                                 /*
                                 'animation' => ['duration' => 0,
                                                ],
                                 'hover' => ['animationDuration' => 0,
                                            ],
                                 'responsiveAnimationDuration' => 0,
                                 */
                                 'scales' => $scales,
                                 'annotation' => ['annotations' => $annotations,
                                                 ],
                                ],
                  ];

    $tmp = str_replace('"__CALLBACK_1__"', "function(value){return value.toLocaleString();}", json_encode($chart_data));
    $tmp = str_replace('"__CALLBACK_2__"', "function(value){return value.toLocaleString() + 'MB';}", $tmp);

    $url = 'https://quickchart.io/chart?w=600&h=360&c=' . urlencode($tmp);
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);

    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    if ($pattern_ == 2) {
        $mu_->post_blog_hatena('database', $description);
        $mu_->post_blog_fc2_async('database', $description);
    }
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>database</title>
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
}

function make_process_time($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $url = 'https://' . $mu_->get_env('WORDPRESS_USERNAME', true) . '.wordpress.com/?s=daily020.php';
    $res = $mu_->get_contents($url);

    $rc = preg_match_all('/rel="bookmark">.+?\/(.+?) .+? \/daily020\.php&nbsp;\[(.+?)s\]/', $res, $matches, PREG_SET_ORDER);

    $labels = [];
    $data = [];
    foreach ($matches as $match) {
        $labels[] = $match[1];
        $tmp = new stdClass();
        $tmp->x = $match[1];
        $tmp->y = $match[2];
        $data[] = $tmp;
    }
    $labels = array_reverse($labels);

    $datasets[] = ['data' => $data,
                   'fill' => false,
                   'lineTension' => 0,
                   'pointStyle' => 'circle',
                   'backgroundColor' => 'black',
                   'borderColor' => 'black',
                   'borderWidth' => 3,
                   'pointRadius' => 4,
                   'pointBorderWidth' => 0,
                   'label' => 'daily020',
                  ];

    $url = 'https://' . $mu_->get_env('WORDPRESS_USERNAME', true) . '.wordpress.com/?s=make_graph.php';
    $res = $mu_->get_contents($url);

    $rc = preg_match_all('/rel="bookmark">.+?\/(.+?) .+? \/make_graph\.php&nbsp;\[(.+?)s\]/', $res, $matches, PREG_SET_ORDER);

    $data = [];
    foreach ($matches as $match) {
        $tmp = new stdClass();
        $tmp->x = $match[1];
        if (in_array($tmp->x, $labels)) {
            $tmp->y = $match[2];
            $data[] = $tmp;
        }
    }

    $datasets[] = ['data' => $data,
                   'fill' => false,
                   'lineTension' => 0,
                   'pointStyle' => 'circle',
                   'backgroundColor' => 'red',
                   'borderColor' => 'red',
                   'borderWidth' => 3,
                   'pointRadius' => 4,
                   'pointBorderWidth' => 0,
                   'label' => 'make_graph',
                  ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                          ],
            ];

    /*
    $url = 'https://quickchart.io/chart?w=600&h=360&c=' . urlencode(json_encode($chart_data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);

    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);
    */
    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 360 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    // $mu_->post_blog_hatena('process time', $description);
    // $mu_->post_blog_fc2_async('process time', $description);
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>process time</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);

    error_log($log_prefix . 'END');
    return 0;
}

function make_post_count($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $sql = <<< __HEREDOC__
SELECT T1.yyyymmdd
      ,T1.post_count
  FROM t_blog_post T1
 WHERE T1.blog_site = 'hatena'
 ORDER BY T1.yyyymmdd DESC
 LIMIT 25
;
__HEREDOC__;

    $pdo = $mu_->get_pdo();

    $labels = [];
    $data1 = [];
    foreach ($pdo->query($sql) as $row) {
        $labels[$row['yyyymmdd']] = substr($row['yyyymmdd'], -2);
        $tmp = new stdClass();
        $tmp->x = substr($row['yyyymmdd'], -2);
        $tmp->y = (int)$row['post_count'];
        $data1[] = $tmp;
    }
    $pdo = null;

    ksort($labels);
    $labels = array_values($labels);

    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                        'ticks' => ['beginAtZero' => true,
                                    // 'max' => 100,
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        'ticks' => ['beginAtZero' => true,
                                    // 'max' => 100,
                                   ],
                       ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data1,
                                        'fill' => false,
                                        'lineTension' => 0,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                        'yAxisID' => 'y-axis-0',
                                       ],
                                       ['data' => $data1,
                                        'fill' => false,
                                        'lineTension' => 0,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                        'yAxisID' => 'y-axis-1',
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                          ],
            ];

    /*
    $url = 'https://quickchart.io/chart?width=600&height=320&c=' . urlencode(json_encode($data));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 320 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    // $mu_->post_blog_hatena('post count', $description);
    // $mu_->post_blog_fc2_async('post count', $description);
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>post count</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);

    error_log($log_prefix . 'END');
    return 0;
}

function make_github_contributions($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $res = $mu_->get_contents('https://github.com/tshr20140816');

    $rc = preg_match_all('/<rect class="day" .+?data-count="(.+?)".*?data-date="(.+?)"/', $res, $matches, PREG_SET_ORDER);

    error_log(print_r($matches, true));

    $labels = [];
    $data1 = [];
    $data2 = [];
    $data3 = [];
    $data4 = [];
    foreach (array_slice($matches, -28) as $match) {
        if (date('w', strtotime($match[2])) == '0') {
            $tmp = new stdClass();
            $tmp->x = substr($match[2], -2);
            $tmp->y = 0;
            $data2[] = $tmp;
        } else if (date('w', strtotime($match[2])) == '6') {
            $tmp = new stdClass();
            $tmp->x = substr($match[2], -2);
            $tmp->y = 0;
            $data3[] = $tmp;
        }
        $tmp = new stdClass();
        $tmp->x = substr($match[2], -2);
        $tmp->y = (int)$match[1];
        $data1[] = $tmp;
        $labels[] = substr($match[2], -2);

        if (count($data4) == 0) {
            $data4[] = $tmp;
        } else {
            if ($data4[0]->y < $tmp->y) {
                $data4[0] = $tmp;
            }
        }
    }

    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                        'ticks' => ['beginAtZero' => true,
                                    'fontColor' => 'black',
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        'ticks' => ['beginAtZero' => true,
                                    'fontColor' => 'black',
                                   ],
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'ticks' => ['fontColor' => 'black',
                                    'autoSkip' => false,
                                   ],
                       ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => [['data' => $data2,
                                        'fill' => false,
                                        'showLine' => false,
                                        'pointBackgroundColor' => 'red',
                                        'pointRadius' => 4,
                                       ],
                                       ['data' => $data3,
                                        'fill' => false,
                                        'showLine' => false,
                                        'pointBackgroundColor' => 'blue',
                                        'pointRadius' => 4,
                                       ],
                                       ['data' => $data1,
                                        'fill' => false,
                                        'lineTension' => 0,
                                        'borderColor' => 'black',
                                        'borderWidth' => 1,
                                        'pointBackgroundColor' => 'black',
                                        'pointRadius' => 2,
                                        'yAxisID' => 'y-axis-0',
                                       ],
                                       ['data' => $data4,
                                        'fill' => false,
                                        'pointRadius' => 0,
                                        'yAxisID' => 'y-axis-1',
                                       ],
                                      ],
                       ],
             'options' => ['legend' => ['display' => false,],
                           'animation' => ['duration' => 0,],
                           'hover' => ['animationDuration' => 0,],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                          ],
            ];

    /*
    $url = 'https://quickchart.io/chart?width=600&height=320&c=' . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);
    */

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 320 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    // $mu_->post_blog_hatena('github contributions', $description);
    // $mu_->post_blog_fc2_async('github contributions', $description);
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>github contributions</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);

    error_log($log_prefix . 'END');
    return 0;
}

function make_storage_usage($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $keyword = 'ijesjwfvtbhf';

    $res = $mu_->search_blog($keyword);

    $data1 = [];
    $labels = [];
    foreach (explode(' ', $res) as $item) {
        $tmp1 = explode(',', $item);
        $tmp2 = new stdClass();
        $tmp2->x = (int)$tmp1[0];
        $tmp2->y = ceil((int)$tmp1[1] / 1024 / 1024);
        $data1[] = $tmp2;
        $labels[] = $tmp2->x;
    }

    $datasets[] = ['data' => $data1,
                   'fill' => false,
                   'pointStyle' => 'circle',
                   'backgroundColor' => 'black',
                   'borderColor' => 'black',
                   'borderWidth' => 1,
                   'pointRadius' => 2,
                   'pointBorderWidth' => 0,
                   'label' => 'hidrive',
                  ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['labels' => ['usePointStyle' => true
                                                    ],
                                       ],
                          ],
            ];

    $url = 'https://quickchart.io/chart?w=600&h=360&c=' . urlencode(json_encode($json));
    $res = $mu_->get_contents($url);
    $url_length = strlen($url);

    $im1 = imagecreatefromstring($res);
    error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
    $im2 = imagecreatetruecolor(imagesx($im1) / 2, imagesy($im1) / 2);
    imagealphablending($im2, false);
    imagesavealpha($im2, true);
    imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 2, imagesy($im1) / 2, imagesx($im1), imagesy($im1));
    imagedestroy($im1);

    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);

    $res = $mu_->shrink_image($file);

    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    // $mu_->post_blog_hatena('storage usage', $description);
    // $mu_->post_blog_fc2_async('storage usage', $description);
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>storage usage</title>
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
}

function make_heroku_dyno_usage_graph3($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

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
    exec('node ../scripts/chartjs_node.js 600 320 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    // $mu_->post_blog_hatena('github contributions', $description);
    // $mu_->post_blog_fc2_async('github contributions', $description);
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>dyno usage</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);

    error_log($log_prefix . 'END');
    return 0;
}

function make_database3($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    for ($i = 0; $i < (int)date('t'); $i++) {
        $labels[] = $i + 1;
    }

    $datasets = [];
    $list = [['target' => 'toodledo',
                'color' => 'green',
                'size_color' => 'red',
                ],
                ['target' => 'redmine',
                'color' => 'blue',
                'size_color' => 'yellow',
                ],
                ['target' => 'ttrss',
                'color' => 'deepskyblue',
                'size_color' => 'orange',
                ],
            ];

    $annotations = [];
    $level = 10000;
    foreach ($list as $one_data) {
        error_log($log_prefix . print_r($one_data, true));
        $keyword = strtolower($one_data['target']);
        for ($i = 0; $i < strlen($keyword); $i++) {
            $keyword[$i] = chr(ord($keyword[$i]) + 1);
        }

        $res = $mu_->search_blog($keyword . 'sfdpsedpvou');

        $data2 = [];
        foreach (explode(' ', $res) as $item) {
            $tmp1 = explode(',', $item);
            $tmp2 = new stdClass();
            $tmp2->x = (int)$tmp1[0];
            $tmp2->y = (int)$tmp1[1];
            $data2[] = $tmp2;
        }

        if (count($data2) < 2) {
            return 0;
        }

        $level -= 1000;
        $annotations[] = ['type' => 'line',
                          'mode' => 'horizontal',
                          'scaleID' => 'y-axis-0',
                          'value' => $level,
                          'borderColor' => 'rgba(0,0,0,0)',
                          'label' => ['enabled' => true,
                                      'content' => number_format(end($data2)->y),
                                      'position' => 'left',
                                      'backgroundColor' => $one_data['color'],
                                     ],
                         ];

        $datasets[] = ['data' => $data2,
                       'fill' => false,
                       'lineTension' => 0,
                       'pointStyle' => 'circle',
                       'backgroundColor' => $one_data['color'],
                       'borderColor' => $one_data['color'],
                       'borderWidth' => 3,
                       'pointRadius' => 4,
                       'pointBorderWidth' => 0,
                       'label' => $one_data['target'] . ' record',
                       'yAxisID' => 'y-axis-0',
                      ];

        $res = $mu_->search_blog($keyword . 'ebubcbtftjaf');

        $data3 = [];
        foreach (explode(' ', $res) as $item) {
            $tmp1 = explode(',', $item);
            $tmp2 = new stdClass();
            $tmp2->x = (int)$tmp1[0];
            $tmp2->y = ceil((int)$tmp1[1] / 1024 / 1024);
            $data3[] = $tmp2;
        }

        $annotations[] = ['type' => 'line',
                          'mode' => 'horizontal',
                          'scaleID' => 'y-axis-0',
                          'value' => $level,
                          'borderColor' => 'rgba(0,0,0,0)',
                          'label' => ['enabled' => true,
                                      'content' => number_format(end($data3)->y),
                                      'position' => 'right',
                                      'backgroundColor' => $one_data['size_color'],
                                      'fontColor' => 'black',
                                     ],
                         ];

        $datasets[] = ['data' => $data3,
                       'fill' => false,
                       'lineTension' => 0,
                       'pointStyle' => 'star',
                       'backgroundColor' => $one_data['size_color'],
                       'borderColor' => $one_data['size_color'],
                       'borderWidth' => 2,
                       'pointRadius' => 3,
                       'pointBorderWidth' => 0,
                       'label' => 'size',
                       'yAxisID' => 'y-axis-1',
                      ];
    }

    $scales = new stdClass();
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'display' => true,
                        'position' => 'left',
                        'ticks' => ['callback' => 'function(value){return value.toLocaleString();}',],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-1',
                        'display' => true,
                        'position' => 'right',
                        'ticks' => ['callback' => "function(value){return value.toLocaleString() + 'MB';}",],
                       ];
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'ticks' => ['autoSkip' => false,
                                   ],
                       ];

    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => 0,
                      'borderColor' => 'rgba(0,0,0,0)',
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-0',
                      'value' => 10000,
                      'borderColor' => 'red',
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-1',
                      'value' => 0,
                      'borderColor' => 'rgba(0,0,0,0)',
                     ];
    $annotations[] = ['type' => 'line',
                      'mode' => 'horizontal',
                      'scaleID' => 'y-axis-1',
                      'value' => 1000,
                      'borderColor' => 'rgba(0,0,0,0)',
                     ];

    $json = ['type' => 'line',
             'data' => ['labels' => $labels,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['labels' => ['usePointStyle' => true,
                                                     'fontColor' => 'black',
                                                    ],
                                       ],
                           'animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                           'annotation' => ['annotations' => $annotations,
                                           ],
                          ],
            ];

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 600 360 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>database</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);

    error_log($log_prefix . 'END');
    return 0;
}

function npb_team_ranking($mu_, $file_name_rss_items_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $urls = [];
    for ($i = 3; $i < 11; $i++) {
        $urls['http://npb.jp/games/' . date('Y') . '/schedule_' . str_pad($i, 2, '0', STR_PAD_LEFT) . '_detail.html'] = null;
    }
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 10,
    ];
    $list_contents = $mu_->get_contents_multi([], $urls, $multi_options);

    $results = [];
    $dic_results = [];
    $md = '';
    $labels = [];
    $data = [];
    foreach ($urls as $url) {
        $res = $list_contents[$url];

        $tmp = explode('<tr id="date', $res);
        foreach ($tmp as $item) {
            $rc = preg_match('/<div class="team1">(.+?)<.+?<div class="score1">(\d+)<.+?<div class="score2">(\d+)<.+?<div class="team2">(.+?)</s', $item, $match);
            if ($rc === 1) {
                $md = substr($item, 0, 4);
                if (in_array($md, $labels, true) !== true) {
                    $labels[] = $md;
                }
                $results[] = substr($item, 0, 4) . ' ' . $match[1] . ' ' . $match[2] . ' - ' . $match[3] . ' ' . $match[4];
                if (array_key_exists($match[1], $dic_results) === false) {
                    $dic_results[$match[1]]['win'] = 0;
                    $dic_results[$match[1]]['lose'] = 0;
                    $dic_results[$match[1]]['draw'] = 0;
                }
                if (array_key_exists($match[4], $dic_results) === false) {
                    $dic_results[$match[4]]['win'] = 0;
                    $dic_results[$match[4]]['lose'] = 0;
                    $dic_results[$match[4]]['draw'] = 0;
                }
                if ((int)$match[2] > (int)$match[3]) {
                    $dic_results[$match[1]]['win']++;
                    $dic_results[$match[4]]['lose']++;
                } else if ((int)$match[2] < (int)$match[3]) {
                    $dic_results[$match[1]]['lose']++;
                    $dic_results[$match[4]]['win']++;
                } else {
                    $dic_results[$match[1]]['draw']++;
                    $dic_results[$match[4]]['draw']++;
                }
                $tmp1 = new stdClass();
                $tmp1->x = $md;
                $tmp1->y = $dic_results[$match[1]]['win'] - $dic_results[$match[1]]['lose'];
                $data[$match[1]][] = $tmp1;

                $tmp1 = new stdClass();
                $tmp1->x = $md;
                $tmp1->y = $dic_results[$match[4]]['win'] - $dic_results[$match[4]]['lose'];
                $data[$match[4]][] = $tmp1;
            }
        }
    }

    $central = [];
    $central['広島'] = 'red';
    $central['巨人'] = 'orange';
    $central['阪神'] = 'yellow';
    $central['中日'] = 'blue';
    $central['DeNA'] = 'navy';
    $central['ヤクルト'] = 'green';

    foreach ($central as $key => $value) {
        $datasets[] = ['data' => $data[$key],
                       'fill' => false,
                       'lineTension' => 0,
                       'pointStyle' => 'circle',
                       'backgroundColor' => $value,
                       'borderColor' => $value,
                       'borderWidth' => 1,
                       'pointRadius' => 2,
                       'pointBorderWidth' => 0,
                       'yAxisID' => 'y-axis-0',
                       'label' => $key,
                      ];
    }

    $scales = new stdClass();
    $scales->xAxes[] = ['id' => 'x-axis-0',
                        'ticks' => ['autoSkip' => false,
                                    'fontSize' => 8,
                                   ],
                       ];
    $scales->yAxes[] = ['id' => 'y-axis-0',
                        'position' => 'right',
                        'ticks' => ['stepSize' => 2,
                                   ],
                       ];

    $labels_new = [];
    foreach ($labels as $label) {
        $is_exists = false;
        foreach ($central as $key => $value) {
            foreach ($data[$key] as $point) {
                if ($point->x === $label) {
                    $is_exists = true;
                    break 2;
                }
            }
        }
        if ($is_exists === true) {
            $labels_new[] = $label;
        }
    }

    $json = ['type' => 'line',
             'data' => ['labels' => $labels_new,
                        'datasets' => $datasets,
                       ],
             'options' => ['legend' => ['labels' => ['usePointStyle' => true,
                                                     'fontColor' => 'black',
                                                    ],
                                       ],
                           'animation' => ['duration' => 0,
                                          ],
                           'hover' => ['animationDuration' => 0,
                                      ],
                           'responsiveAnimationDuration' => 0,
                           'scales' => $scales,
                          ],
            ];

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 1200 600 ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = $mu_->shrink_image($file);
    unlink($file);

    $description = '<img src="data:image/png;base64,' . base64_encode($res) . '" />';
    $description = '<![CDATA[' . $description . ']]>';

    $rss_item_text = <<< __HEREDOC__
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>central league</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
__HEREDOC__;

    $rss_item_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $rss_item_text);
    $rss_item_text = str_replace('__DESCRIPTION__', $description, $rss_item_text);
    $rss_item_text = str_replace('__HASH__', hash('sha256', $description), $rss_item_text);
    file_put_contents($file_name_rss_items_, $rss_item_text, FILE_APPEND);

    error_log($log_prefix . 'END');
    return 0;
}
