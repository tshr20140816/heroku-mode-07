<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190823f($mu);
// @unlink('/tmp/dummy');

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190823f($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);
    
    $url = getenv('TEST_URL_01');
    $base_name = pathinfo($url)['basename'];
    $file_name = '/tmp/' . $base_name;
    
    if (file_exists($file_name) === false) {
        $line = 'curl -v -m 120 -o ' . "/tmp/${base_name}" . ' -u ' . "${user_hidrive}:${password_hidrive} " . $url;
        $mu_->cmd_execute($line);
    }
    
    $res = $mu_->get_contents('https://www.pakutaso.com/animal/cat/');
    // https://www.pakutaso.com/animal/cat/index_2.html
    // error_log($res);
    
    $rc = preg_match_all('/<a href="(https:\/\/www.pakutaso.com\/2.+?)"/', $res, $matches);
    
    // error_log(print_r($matches, true));
    
    $options = [
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
    ];
    
    $file = tempnam('/tmp', 'jpeg_' . md5(microtime(true))) . '.jpg';
    
    foreach ($matches[1] as $url) {
        // error_log($url);
        $res = $mu_->get_contents($url);
        // error_log($res);
        $rc = preg_match('/"thumbnailUrl":"(.+?)"/', $res, $match);
        // error_log(print_r($match, true));
        // $res = $mu_->get_contents($match[1], $options);
        $line = 'curl -v -o ' . $file . ' ' . $match[1] . ' 2>&1';
        $mu_->cmd_execute($line);
        error_log(filesize($file));
        break;
    }
    
    $line = 'exiftool -all= ' . $file;
    $mu_->cmd_execute($line);
    
    $line = 'outguess -k password -d ' . $file_name . ' ' . $file . ' ' . $file . '.jpg';
    $res = $mu_->cmd_execute($line);
    
    unlink($file);
    clearstatcache();
    rename($file . '.jpg', $file);
    
    error_log(filesize($file));
    
    $line = 'exiftool -artist="TEST" ' . $file;
    $mu_->cmd_execute($line);
    
    clearstatcache();
    error_log(filesize($file));
    
    /*
    $livedoor_id = $mu_->get_env('LIVEDOOR_ID', true);
    $livedoor_atom_password = $mu_->get_env('LIVEDOOR_ATOM_PASSWORD', true);
    
    $url = "https://livedoor.blogcms.jp/atompub/${livedoor_id}/image";
    
    $line = "curl -v -X POST -u ${livedoor_id}:${livedoor_atom_password} " . '-H "Expect:" -H "Content-Type: image/jpeg" '
        . "${url} --data-binary @${file}";
    $mu_->cmd_execute($line);
    */
    
    unlink($file);
    return;
    
    
    $line = 'outguess -k password -r ' . $file . '.jpg /tmp/composer.txt';
    $mu_->cmd_execute($line);
    
    error_log(file_get_contents('/tmp/composer.txt'));
    
}

function func_20190823e($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);
    
    $url = getenv('TEST_URL_01');
    $base_name = pathinfo($url)['basename'];
    $file_name = '/tmp/' . $base_name;
    
    if (file_exists($file_name) === false) {
        $line = 'curl -v -m 120 -o ' . "/tmp/${base_name}" . ' -u ' . "${user_hidrive}:${password_hidrive} " . $url;
        $mu_->cmd_execute($line);
    }
    
    $line = "lbzip2 -v -k ${file_name}";
    $mu_->cmd_execute($line);
    
    // $line = "pixz -9 < ${file_name} > ${file_name}.xz";
    // $mu_->cmd_execute($line);
    
    // $line = "pxz -v -k -9 ${file_name}";
    $line = "pxz -kvc < ${file_name} | pv | dd of=${file_name}.xz bs=16M";
    $mu_->cmd_execute($line);
    
    exec('ls -lang /tmp/', $res);
    error_log(print_r($res, true));
    
    // unlink($file_name);
    unlink($file_name . '.bz2');
    unlink($file_name . '.xz');
}

function func_20190823d($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    
    // $list_days = [3, 9, 10, 15, 16, 17, 18];
    // $list_days = [5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
    $list_days = [4, 5, 6, 7, 8, 9, 10, 11, 12];
    // $list_days = [7];
    $list_cookie = [];
    $urls = [];
    foreach ($list_days as $day) {
        $cookie = tempnam("/tmp", 'cookie_' .  md5(microtime(true)));
        $list_cookie[] = $cookie;

        $url = 'http://www1.jr.cyberstation.ne.jp/csws/Vacancy.do?' . $day;
        $post_data = [
            'month' => '10',
            'day' => $day,
            'hour' => '22',
            'minute' => '30',
            'train' => '5',
            'dep_stn' => mb_convert_encoding('岡山', 'SJIS', 'UTF-8'),
            'arr_stn' => mb_convert_encoding('東京', 'SJIS', 'UTF-8'),
            'dep_stnpb' => '',
            'arr_stnpb' => '',
            'script' => '1',
        ];
        $options = [
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                'Cache-Control: no-cache',
                'Connection: keep-alive',
                'DNT: 1',
                'Upgrade-Insecure-Requests: 1',
                'Referer: ' . $url,
                ],
            CURLOPT_COOKIEJAR => $cookie,
            CURLOPT_COOKIEFILE => $cookie,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data),
        ];
        // $res = $mu_->get_contents($url, $options);
        $urls[$url] = $options;
        // $res = mb_convert_encoding($res, 'UTF-8', 'SJIS');
        // error_log($res);
        // unlink($cookie);
    }

    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 8,
        CURLMOPT_MAXCONNECTS => 8,
    ];
    $results = $mu_->get_contents_multi($urls, null, $multi_options);
    $list_result = [];
    $result_string = '';
    foreach ($urls as $url => $value) {
        // error_log(mb_convert_encoding($results[$url], 'UTF-8', 'SJIS'));
        $res = mb_convert_encoding($results[$url], 'UTF-8', 'SJIS');
        $count_maru = substr_count($res, '<td align="center">○</td>');
        $count_sankaku = substr_count($res, '<td align="center">△</td>');
        $count_batsu = substr_count($res, '<td align="center">×</td>');
        $count_mada = substr_count($res, 'ご希望の乗車日の空席状況は照会できません。');
        $tmp = explode('?', $url);
        $list_result[$tmp[1]] = [$count_maru, $count_sankaku, $count_batsu, $count_mada];
        
        $result_string .= $tmp[1];
        if ($count_maru > 0) {
            $result_string .= str_repeat('○', $count_maru);
        }
        if ($count_sankaku > 0) {
            $result_string .= str_repeat('△', $count_sankaku);
        }
        if ($count_batsu > 0) {
            $result_string .= str_repeat('×', $count_batsu);
        }
        if ($count_mada > 0) {
            $result_string .= '-';
        }
        $result_string .= "\n";
    }
    error_log(print_r($list_result, true));
    error_log($result_string);
    foreach ($list_cookie as $cookie) {
        unlink($cookie);
    }
}

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
    $tmp1->x = $gain_max_value + ($gain_max_value - $gain_min_value);
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

    $xaxis_max_value = $gain_max_value + (10 - $gain_max_value % 10);
    $xaxis_max_value += $xaxis_max_value % 20 === 0 ? 20 : 10;

    $xaxis_min_value = $gain_min_value - ($gain_min_value % 10);
    $xaxis_min_value -= $xaxis_min_value % 20 === 0 ? 20 : 10;

    $loss_max_value = $loss_max_value + (10 - $loss_max_value % 10);
    $loss_max_value += $loss_max_value % 20 === 0 ? 20 : 10;

    $loss_min_value = $loss_min_value - ($loss_min_value % 10);
    $loss_min_value -= $loss_min_value % 20 === 0 ? 20 : 10;

    $scales->xAxes[] = ['display' => true,
                        'scaleLabel' => ['display' => true,
                                         'labelString' => '得点',
                                         'fontColor' => 'black',
                                        ],
                        'ticks' => ['max' => $xaxis_max_value,
                                    'min' => $xaxis_min_value,
                                    'stepSize' => 20,
                                    'autoSkip' => false,
                                   ],
                       ];
    $scales->yAxes[] = ['display' => true,
                        'bottom' => $loss_min_value,
                        'scaleLabel' => ['display' => true,
                                         'labelString' => '失点',
                                         'fontColor' => 'black',
                                        ],
                        'ticks' => ['max' => $loss_max_value,
                                    'min' => $loss_min_value,
                                    'stepSize' => 20,
                                    'autoSkip' => false,
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
    unlink($file);
    
    $im1 = imagecreatefromstring($res);
    
    $im2 = imagecreatetruecolor(imagesx($im1), imagesy($im1) - 25);
    imagefill($im2, 0, 0, imagecolorallocate($im2, 255, 255, 255));
    
    imagecopy($im2, $im1, 0, 0, 0, 0, imagesx($im1), imagesy($im1) - 25);
    imagedestroy($im1);
    
    $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
    imagepng($im2, $file, 9);
    imagedestroy($im2);
    
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
