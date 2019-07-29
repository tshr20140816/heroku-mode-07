<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$rc = check_train($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function check_train($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

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
            // $description .= "\n\n" . $tmp;
            if (strpos($tmp, '【芸備線】 西日本豪雨に伴う 運転見合わせ') === false) {
                $description .= "\n\n" . $tmp;
            }
        }
    }

    $hatena_id = $mu_->get_env('HATENA_ID', true);
    $hatena_blog_id = $mu_->get_env('HATENA_BLOG_ID', true);
    $hatena_api_key = $mu_->get_env('HATENA_API_KEY', true);

    $url = "https://blog.hatena.ne.jp/${hatena_id}/${hatena_blog_id}/atom/entry";

    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "${hatena_id}:${hatena_api_key}",
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => ['Expect:',],
    ];

    for ($i = 0; $i < 10; $i++) {
        $res = $mu_->get_contents($url, $options);
        // error_log($res);

        $entrys = explode('<entry>', $res);
        array_shift($entrys);
        foreach ($entrys as $entry) {
            $rc = preg_match('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ TRAIN</', $entry, $match);
            error_log($log_prefix . $rc);

            if ($rc === 1) {
                $rc = preg_match('/<link rel="edit" href="(.+?)"/', $entry, $match);
                error_log($log_prefix . $match[1]);
                $url = $match[1];
                
                $options = [
                    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                    CURLOPT_USERPWD => "${hatena_id}:${hatena_api_key}",
                    CURLOPT_CUSTOMREQUEST => 'DELETE',
                    CURLOPT_HEADER => true,
                    CURLOPT_HTTPHEADER => ['Expect:',],
                ];

                $res = $mu_->get_contents($url, $options);
                error_log($log_prefix . $res);
                break 2;
            }
        }
        $rc = preg_match('/<link rel="next" href="(.+?)"/', $res, $match);
        $url = $match[1];
    }
    
    // $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2_st.json';
    // $sanyo2_st = $mu_->get_contents($url, null, true);

    // $url = 'https://www.train-guide.westjr.co.jp/api/v3/sanyo2.json';
    // $sanyo2 = $mu_->get_contents($url);

    // error_log($log_prefix . print_r(json_decode($res_sanyo2_st, true), true));
    // error_log($log_prefix . print_r(json_decode($res_sanyo2, true), true));

    $res_kudari = get_train_sanyo2_image3($mu_, $res_sanyo2_st, $res_sanyo2, '1');
    if ($res_kudari != '400') {
        $res_nobori = get_train_sanyo2_image3($mu_, $res_sanyo2_st, $res_sanyo2, '0');
    }

    if ($res_kudari != '400' && $res_nobori != '400') {
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
    }

    // $mu_->post_blog_livedoor('TRAIN', $description);
    $mu_->post_blog_hatena('TRAIN', $description);

    error_log($log_prefix . 'start exec');
    exec('php -d apc.enable_cli=1 -d include_path=.:/app/.heroku/php/lib/php:/app/lib ../scripts/update_ttrss.php >/dev/null &');
    error_log($log_prefix . 'finish exec');
}
    
function get_train_sanyo2_image3($mu_, $sanyo2_st_, $sanyo2_, $direction_ = '0') // $direction_ : '0' nobori / '1' kudari
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
                   'pointRadius' => 12,
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
                       'pointRadius' => 12,
                       'pointRotation' => $pointRotation,
                       'pointBackgroundColor' => 'lightgray',
                       'pointBorderColor' => 'cyan',
                       'pointBorderWidth' => 3,
                      ];
    }

    if (count($data['ontime_etc']) > 0) {
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
    }

    if (count($data['delay_etc']) > 0) {
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

    if ($res != '400') {
        $im1 = imagecreatefromstring($res);
        error_log($log_prefix . imagesx($im1) . ' ' . imagesy($im1));
        $im2 = imagecreatetruecolor(imagesx($im1) / 3, imagesy($im1) / 3);
        imagefill($im2, 0, 0, imagecolorallocate($im1, 255, 255, 255));
        imagecopyresampled($im2, $im1, 0, 0, 0, 0, imagesx($im1) / 3, imagesy($im1) / 3, imagesx($im1), imagesy($im1));
        imagedestroy($im1);
        $file = tempnam('/tmp', 'png_' . md5(microtime(true)));
        imagepng($im2, $file, 9);
        imagedestroy($im2);
        $res = file_get_contents($file);
        unlink($file);
    }

    return $res;
}
