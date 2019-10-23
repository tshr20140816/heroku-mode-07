<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = check_train2($mu);
$hour = date('G', strtotime('+9 hours'));
$minute = ltrim(date('i', strtotime('+9 hours')), '0');

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function check_train2($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    $res_sanyo2_st = $mu_->get_contents($url, null, true);

    $stations = [];
    foreach (json_decode($res_sanyo2_st, true)['stations'] as $station) {
        $stations[$station['info']['code']] = $station['info']['name'];
    }

    $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json?' . microtime(true);
    $res_sanyo2 = $mu_->get_contents($url);
    $json = json_decode($res_sanyo2, true);

    $update_time = $json['update'];
    $delays_up = [];
    $delays_down = [];
    foreach ($json['trains'] as $train) {
        if ($train['delayMinutes'] != '0') {
            $tmp = explode('_', $train['pos']);
            $station_name = $stations[$tmp[0]];
            if ($train['direction'] == '0') {
                $delays_up[] = '上り ' . $station_name . ' ' . $train['dest'] . '行き ' . $train['displayType']
                    . ' ' . $train['delayMinutes'] . '分遅れ';
            } else {
                $delays_down[] = '下り ' . $station_name . ' ' . $train['dest'] . '行き ' . $train['displayType']
                    . ' ' . $train['delayMinutes'] . '分遅れ';
            }
        }
    }

    $description = '';
    if (count($delays_up) > 0) {
        $description = implode("\n", $delays_up);
    }
    if (count($delays_down) > 0) {
        $description .= "\n\n";
        $description .= implode("\n", $delays_down);
    }

    if (trim($description) == '') {
        return;
    }

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

    $url = 'https://trafficinfo.westjr.co.jp/chugoku.html';
    $res = $mu_->get_contents($url, $options);
    $res = mb_convert_encoding($res, 'UTF-8', 'SJIS');

    $rc = preg_match("/<div id='syosai_7'>(.+?)<!--#syosai_n-->/s", $res, $match);
    if ($rc == 1) {
        $rc = preg_match_all("/<div class='jisyo'>(.+?)<!-- \.jisyo-->/s", $match[1], $matches);
        if ($rc === false) {
            $rc = 0;
        }
    }

    $description = trim($description) . "\n\n-----";
    if ($rc > 0) {
        foreach ($matches[1] as $item) {
            $tmp = trim(strip_tags($item));
            $tmp = preg_replace('/\t+/', '', $tmp);
            $tmp = mb_convert_kana($tmp, 'as');
            /*
            if (strpos($tmp, '【芸備線】 西日本豪雨に伴う 運転見合わせ') === false) {
                $description .= "\n\n" . $tmp;
            }
            */
            $description .= "\n\n" . $tmp;
        }
    }

    $mu_->post_blog_wordpress('TRAIN', $description, 'train', true);
    
    $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ TRAIN</');

    $res_kudari = get_train_sanyo2_image3($mu_, $res_sanyo2_st, $res_sanyo2, '1');
    if ($res_kudari != '400') {
        $res_nobori = get_train_sanyo2_image3($mu_, $res_sanyo2_st, $res_sanyo2, '0');
    }

    $im1 = imagecreatefromstring($res_kudari);
    $x = imagesx($im1);
    $y1 = imagesy($im1);
    imagedestroy($im1);

    $im1 = imagecreatefromstring($res_nobori);
    // $x = imagesx($im1);
    $y2 = imagesy($im1);
    imagedestroy($im1);

    $im1 = imagecreatetruecolor($x, $y1 + $y2);
    imagefill($im1, 0, 0, imagecolorallocate($im1, 255, 255, 255));

    $im2 = imagecreatefromstring($res_kudari);
    imagecopy($im1, $im2, 0, 0, 0, 0, $x, $y1);
    imagedestroy($im2);

    $im2 = imagecreatefromstring($res_nobori);
    imagecopy($im1, $im2, 0, $y1, 0, 0, $x, $y2);
    imagedestroy($im2);

    $file = tempnam("/tmp", md5(microtime(true)));
    imagepng($im1, $file, 9);
    imagedestroy($im1);
    $res = file_get_contents($file);
    unlink($file);

    $description .= "\n" . '<img src="data:image/png;base64,' . base64_encode($res) . '" />';

    // $mu_->post_blog_livedoor('TRAIN', $description);
    // $mu_->post_blog_hatena('TRAIN', $description, 'train');

    error_log($log_prefix . 'start exec');
    // exec('php -d apc.enable_cli=1 -d include_path=.:/app/.heroku/php/lib/php:/app/lib ../scripts/update_ttrss.php >/dev/null &');
    error_log($log_prefix . 'finish exec');
}

function get_train_sanyo2_image3($mu_, $sanyo2_st_, $sanyo2_, $direction_ = '0') // $direction_ : '0' nobori / '1' kudari
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

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
            /*
            if ((int)$tmp->x === 0) {
                $dest = str_repeat('　', mb_strlen($dest)) . $dest;
            } else if ((int)$tmp->x === (count($labels['station']) - 1)) {
                $dest .= str_repeat('　', mb_strlen($dest));
            }
            */
            if ($train['delayMinutes'] != '0') {
                if ($train['notice'] != '' || $train['displayType'] != '普通') {
                    $data['delay_etc'][] = $tmp;
                } else {
                    $data['delay'][] = $tmp;
                }
                // $labels['dest'][(int)$tmp->x] .= "\n" . $dest . $train['delayMinutes'];
                $labels['dest'][(int)$tmp->x] = $dest . $train['delayMinutes'] . "\n" . $labels['dest'][(int)$tmp->x];
            } else {
                if ($train['notice'] != '' || $train['displayType'] != '普通') {
                    $data['ontime_etc'][] = $tmp;
                } else {
                    $data['ontime'][] = $tmp;
                }
                // $labels['dest'][(int)$tmp->x] .= "\n" . $dest;
                $labels['dest'][(int)$tmp->x] = $dest . "\n" . $labels['dest'][(int)$tmp->x];
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
                   'pointRadius' => 8,
                   'pointRotation' => $pointRotation,
                   'pointBackgroundColor' => 'lightgray',
                   'pointBorderColor' => 'red',
                   'pointBorderWidth' => 2,
                  ];

    if (count($data['delay']) > 0) {
        $datasets[] = ['data' => $data['delay'],
                       'fill' => false,
                       'showLine' => false,
                       'xAxisID' => 'x-axis-0',
                       'showLine' => false,
                       'pointStyle' => 'triangle',
                       'pointRadius' => 8,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => 'lightgray',
                       'pointBorderColor' => 'cyan',
                       'pointBorderWidth' => 2,
                      ];
    }

    if (count($data['ontime_etc']) > 0) {
        $datasets[] = ['data' => $data['ontime_etc'],
                       'fill' => false,
                       'showLine' => false,
                       'xAxisID' => 'x-axis-0',
                       'showLine' => false,
                       'pointStyle' => 'triangle',
                       'pointRadius' => 8,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => 'yellow',
                       'pointBorderColor' => 'red',
                       'pointBorderWidth' => 2,
                      ];
    }

    if (count($data['delay_etc']) > 0) {
        $datasets[] = ['data' => $data['delay_etc'],
                       'fill' => false,
                       'showLine' => false,
                       'xAxisID' => 'x-axis-0',
                       'showLine' => false,
                       'pointStyle' => 'triangle',
                       'pointRadius' => 8,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => 'yellow',
                       'pointBorderColor' => 'cyan',
                       'pointBorderWidth' => 2,
                      ];
    }

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
                        // 'display' => true,
                        'labels' => $labels['station'],
                        'ticks' => ['fontColor' => 'black',
                                    'fontSize' => 10,
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
            $tmp = explode("\n", trim($labels['dest'][$i], "\n"), 2);
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
                                          'fontFamily' => 'IPAexGothic',
                                          'fontStyle' => 'normal',
                                          'fontSize' => 10,
                                         ],
                             ];
            if (count($tmp) > 1) {
                $tmp = explode("\n", trim($labels['dest'][$i], "\n"));
                array_shift($tmp);
                $annotations[] = ['type' => 'line',
                                  'mode' => 'vertical',
                                  'scaleID' => 'x-axis-0',
                                  'value' => (string)$i,
                                  'borderColor' => 'rgba(0,0,0,0)',
                                  'label' => ['enabled' => true,
                                              'content' => $tmp,
                                              'position' => 'top',
                                              'backgroundColor' => 'rgba(0,0,0,0)',
                                              'fontColor' => 'black',
                                              'fontFamily' => 'IPAexGothic',
                                              'fontStyle' => 'normal',
                                              'fontSize' => 10,
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
    $height = 120;
    if ($y_max > 2) {
        $height = 160;
    }

    $file = tempnam('/tmp', 'chartjs_' . md5(microtime(true)));
    exec('node ../scripts/chartjs_node.js 1000 ' . $height . ' ' . base64_encode(json_encode($json)) . ' ' . $file);
    $res = file_get_contents($file);
    unlink($file);

    return $res;
}

function search_sunrize($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $url_base = 'http://www1.jr.cyberstation.ne.jp/csws/Vacancy.do';
    $hash_url = 'url' . hash('sha512', $url_base);

    // $list_days = [12, 13, 14, 15, 16, 17, 18];
    $list_days = [16, 17, 18];
    $list_cookie = [];
    $urls = [];
    foreach ($list_days as $day) {
        $cookie = tempnam("/tmp", 'cookie_' .  md5(microtime(true)));
        $list_cookie[] = $cookie;

        $url = $url_base . '?' . $day;
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
        $urls[$url] = $options;
    }

    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 8,
        CURLMOPT_MAXCONNECTS => 8,
    ];
    $results = $mu_->get_contents_multi($urls, null, $multi_options);
    $description = "\n";
    foreach ($urls as $url => $value) {
        // error_log(mb_convert_encoding($results[$url], 'UTF-8', 'SJIS'));
        $res = mb_convert_encoding($results[$url], 'UTF-8', 'SJIS');
        $count_maru = substr_count($res, '<td align="center">○</td>');
        $count_sankaku = substr_count($res, '<td align="center">△</td>');
        $count_batsu = substr_count($res, '<td align="center">×</td>');
        $count_mada = substr_count($res, 'ご希望の乗車日の空席状況は照会できません。');
        $tmp = explode('?', $url);

        $description .= $tmp[1] . ' ';
        if ($count_maru > 0) {
            $description .= str_repeat('○', $count_maru);
        }
        if ($count_sankaku > 0) {
            $description .= str_repeat('△', $count_sankaku);
        }
        if ($count_batsu > 0) {
            $description .= str_repeat('×', $count_batsu);
        }
        if ($count_mada > 0) {
            $description .= '-';
        }
        $description .= "\n";
    }
    foreach ($list_cookie as $cookie) {
        unlink($cookie);
    }

    // error_log($description);
    $mu_->logging_object($description, $log_prefix);
    $hash_description = hash('sha512', $description);

    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_description) {
        $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
        $description = '<div class="' . $hash_url . '">' . "${hash_description}</div>${description}";
        $mu_->post_blog_wordpress($hash_url, $description, 'sunrize');
    }
}

function search_extra($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $list = [];
    $list[] = ['10','19','14','30','3','盛岡','新函館北斗','2150','1007','はやぶさ　２１号',''];
    $list[] = ['10','19','14','30','3','盛岡','八戸','2150','2170','はやぶさ　２１号',''];
    $list[] = ['10','19','15','0','3','八戸','新青森','2170','2343','はやぶさ　２１号',''];
    $list[] = ['10','19','15','20','3','新青森','木古内','2343','1330','はやぶさ　２１号',''];
    $list[] = ['10','19','16','10','3','木古内','新函館北斗','1330','1007','はやぶさ　２１号',''];
    /*
    $list[] = ['10','20','16','50','4','富山','金沢','5280','5260','はくたか５６７号',''];
    $list[] = ['10','20','17','30','5','金沢','新大阪','5260','6100','４０号','<td.+?'];
    $list[] = ['10','20','20','10','1','新大阪','広島','6100','8100','さくら　５７３号','<td.+?'];
    */
    
    $description = '';

    $url = 'http://www1.jr.cyberstation.ne.jp/csws/Vacancy.do';
    $hash_url = 'url' . hash('sha512', $url . 'extra');

    $cookie = tempnam("/tmp", 'cookie_' .  md5(microtime(true)));

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
        CURLOPT_POSTFIELDS => '',
    ];

    foreach ($list as $item) {
        $post_data = [
            'month' => $item[0],
            'day' => $item[1],
            'hour' => $item[2],
            'minute' => $item[3],
            'train' => $item[4],
            'dep_stn' => mb_convert_encoding($item[5], 'SJIS', 'UTF-8'),
            'arr_stn' => mb_convert_encoding($item[6], 'SJIS', 'UTF-8'),
            'dep_stnpb' => $item[7],
            'arr_stnpb' => $item[8],
            'script' => '1',
        ];
        $options[CURLOPT_POSTFIELDS] = http_build_query($post_data);
        $res = $mu_->get_contents($url, $options);
        unlink($cookie);
        $res = mb_convert_encoding($res, 'UTF-8', 'SJIS');

        $rc = preg_match('/<td align="left">.*?' . $item[9] . '.+?<td.+?<td.+?<td.+?' . $item[10] . '<td.+?>(.+?)</s', $res, $match);

        $description .= "\n" . $item[5] . ' - ' . $item[6] . ' ' . $match[1];
    }

    $mu_->logging_object($description, $log_prefix);
    $hash_description = hash('sha512', $description);

    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_description) {
        $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
        $description = '<div class="' . $hash_url . '">' . "${hash_description}</div>${description}";
        $mu_->post_blog_wordpress($hash_url, $description, 'train_extra');
    }
}

function search_extra2($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $list = [];
    $list[] = ['10','20','21','30','2','新大阪','広島','6100','8100','こだま　７６７号',''];
    $list[] = ['10','20','21','30','2','新大阪','相生','6100','6190','こだま　７６７号',''];
    $list[] = ['10','20','21','30','2','新大阪','新神戸','6100','6155','こだま　７６７号',''];
    $list[] = ['10','20','21','30','2','新神戸','西明石','6155','6170','こだま　７６７号',''];
    $list[] = ['10','20','21','40','2','西明石','姫路','6170','6180','こだま　７６７号',''];
    $list[] = ['10','20','22','00','2','姫路','相生','6180','6190','こだま　７６７号',''];
    /*
    $list[] = ['10','20','16','50','4','富山','金沢','5280','5260','はくたか５６７号',''];
    $list[] = ['10','20','17','30','5','金沢','新大阪','5260','6100','４０号','<td.+?'];
    $list[] = ['10','20','20','10','1','新大阪','広島','6100','8100','さくら　５７３号','<td.+?'];
    */
    
    $description = '';

    $url = 'http://www1.jr.cyberstation.ne.jp/csws/Vacancy.do';
    $hash_url = 'url' . hash('sha512', $url . 'extra2');

    $cookie = tempnam("/tmp", 'cookie_' .  md5(microtime(true)));

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
        CURLOPT_POSTFIELDS => '',
    ];

    foreach ($list as $item) {
        $post_data = [
            'month' => $item[0],
            'day' => $item[1],
            'hour' => $item[2],
            'minute' => $item[3],
            'train' => $item[4],
            'dep_stn' => mb_convert_encoding($item[5], 'SJIS', 'UTF-8'),
            'arr_stn' => mb_convert_encoding($item[6], 'SJIS', 'UTF-8'),
            'dep_stnpb' => $item[7],
            'arr_stnpb' => $item[8],
            'script' => '1',
        ];
        $options[CURLOPT_POSTFIELDS] = http_build_query($post_data);
        $res = $mu_->get_contents($url, $options);
        unlink($cookie);
        $res = mb_convert_encoding($res, 'UTF-8', 'SJIS');

        $rc = preg_match('/<td align="left">.*?' . $item[9] . '.+?<td.+?<td.+?' . $item[10] . '<td.+?>(.+?)</s', $res, $match);

        $description .= "\n" . $item[5] . ' - ' . $item[6] . ' ' . $match[1];
    }

    $mu_->logging_object($description, $log_prefix);
    $hash_description = hash('sha512', $description);

    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_description) {
        $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
        $description = '<div class="' . $hash_url . '">' . "${hash_description}</div>${description}";
        $mu_->post_blog_wordpress($hash_url, $description, 'train_extra');
    }
}
