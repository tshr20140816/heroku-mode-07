<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');
// require_once('Zend/XmlRpc/Client.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190823k($mu);
// @unlink('/tmp/dummy');

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190823k($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $res = file_get_contents('./daily010.php');
    
    for ($i = 0; $i < strlen($res); $i++) {
        error_log(hexdec(bin2hex($res[$i])) . ' ' . (int)$res[$i]);
    }
}

function func_20190823j($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $list = [];
    $list[] = ['10','19','14','30','3','盛岡','新函館北斗','2150','1007','はやぶさ　２１号',''];
    $list[] = ['10','19','14','30','3','盛岡','八戸','2150','2170','はやぶさ　２１号',''];
    $list[] = ['10','19','15','0','3','八戸','新青森','2170','2343','はやぶさ　２１号',''];
    $list[] = ['10','19','15','20','3','新青森','木古内','2343','1330','はやぶさ　２１号',''];
    $list[] = ['10','19','16','10','3','木古内','新函館北斗','1330','1007','はやぶさ　２１号',''];
    $list[] = ['10','20','16','50','4','富山','金沢','5280','5260','はくたか５６７号',''];
    $list[] = ['10','20','17','30','5','金沢','新大阪','5260','6100','４０号','<td.+?'];
    $list[] = ['10','20','20','10','1','新大阪','広島','6100','8100','さくら　５７３号','<td.+?'];
    
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
        CURLOPT_POSTFIELDS => http_build_query($post_data),
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

    /*
    $res = $mu_->search_blog($hash_url);
    if ($res != $hash_description) {
        $mu_->delete_blog_hatena('/<title>\d+\/\d+\/+\d+ \d+:\d+:\d+ ' . $hash_url . '</');
        $description = '<div class="' . $hash_url . '">' . "${hash_description}</div>${description}";
        $mu_->post_blog_wordpress($hash_url, $description, 'train_extra');
    }
    */
}

function func_20190823i($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $livedoor_id = $mu_->get_env('LIVEDOOR_ID', true);
    $livedoor_filemanager_password = getenv('TEST_PASSWORD');
    $url = "https://livedoor.blogcms.jp/blog/${livedoor_id}/file_manager/list";
    // $post_data = ['dir_id' => 1,];
    
    $options = [CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_HTTPHEADER => [
                    'X-LDBlog-Token: ' . $livedoor_filemanager_password,
                    'Expect:'
                    ],
                CURLOPT_HEADER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post_data),
               ];
    $res = $mu_->get_contents($url, $options);
    error_log($res);
}

function func_20190823h($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $file = tempnam('/tmp', 'jpeg_' . md5(microtime(true))) . '.jpg';
    
    $url = 'https://www.pakutaso.com/animal/cat/';
    $res = $mu_->get_contents($url, null, true);
    
    $rc = preg_match('/<p class="align -right" style="margin-top:10px"><small>(\d+)/', $res, $match);
    
    // error_log($match[1]);
    
    $page = rand(1, $match[1]);
    
    if ($page !== 1) {
        $url .= 'index_' . $page . '.html';
    }
    
    $res = $mu_->get_contents($url, null, true);
    $rc = preg_match_all('/<a href="(https:\/\/www.pakutaso.com\/2.+?)"/', $res, $matches);
    // error_log(print_r($matches, true));
    
    $url = $matches[1][rand(0, count($matches[1]))];
    $res = $mu_->get_contents($url, null, true);
    $rc = preg_match('/"thumbnailUrl":"(.+?)"/', $res, $match);
    $line = 'curl -v -o ' . $file . ' ' . $match[1] . ' 2>&1';
    $mu_->cmd_execute($line);
    error_log(filesize($file));
    
    /*
    $livedoor_id = $mu_->get_env('LIVEDOOR_ID', true);
    $livedoor_atom_password = $mu_->get_env('LIVEDOOR_ATOM_PASSWORD', true);
    
    $url = "https://livedoor.blogcms.jp/atompub/${livedoor_id}/image";
    
    $line = "curl -v -X POST -u ${livedoor_id}:${livedoor_atom_password} " . '-H "Expect:" -H "Content-Type: image/jpeg" '
        . "${url} --data-binary @${file}";
    $mu_->cmd_execute($line);
    */
    
    $livedoor_id = $mu_->get_env('LIVEDOOR_ID', true);
    $livedoor_filemanager_password = getenv('TEST_PASSWORD');
    $url = "https://livedoor.blogcms.jp/blog/${livedoor_id}/file_manager/upload";
    /*
    $post_data = ['dir_id' => 86924,
                 ];
    
    $options = [CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_HTTPHEADER => [
                    'X-LDBlog-Token: ' . $livedoor_filemanager_password,
                    'Expect:'
                    ],
                CURLOPT_HEADER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post_data),
               ];
    $res = $mu_->get_contents($url, $options);
    */
    $line = "curl -v -X POST " . '-H "Expect:" -H "X-LDBlog-Token: ' . $livedoor_filemanager_password . '" '
        . "${url} -F 'dir_id=1' -F 'name=test0010.jpg' -F 'upload_data=@" . $file . "'";
    $mu_->cmd_execute($line);
    
    unlink($file);
}

function func_20190823g($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');

    $cookie = tempnam('/tmp', 'cookie_' . md5(microtime(true)));
    
    $options = [CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                    'Cache-Control: no-cache',
                    'Connection: keep-alive',
                    'DNT: 1',
                    'Upgrade-Insecure-Requests: 1',
                    ],
                CURLOPT_COOKIEJAR => $cookie,
                CURLOPT_COOKIEFILE => $cookie,
                CURLOPT_HEADER => true,
               ];
    
    $url_base = 'https://www.accuweather.com/ja/jp/hiroshima-shi/223955/daily-weather-forecast/223955';
    
    $urls = [];
    $urls[] = $url_base;
    $urls[] = $url_base . '?page=1';
    $urls[] = $url_base . '?page=2';
    $urls[] = $url_base . '?page=3';
    $urls[] = $url_base . '?page=4';
    
    $list_base = [];
    foreach ($urls as $url) {
        $res = $mu_->get_contents($url, $options, true);
        $rc = preg_match('/var dailyForecast =(.+);/', $res, $match);
        $json = json_decode($match[1]);
        // error_log(print_r($json, true));
        foreach ($json as $item) {
            $list_base[$item->date] = $item->day->phrase . ' ' . $item->day->precip . ' '
                . (int)(($item->day->temp - 32) * 5 / 9) . '/' . (int)(($item->night->temp - 32) * 5 / 9);
        }
    }
    unlink($cookie);
    error_log(print_r($list_base, true));
    /*
    $rc = preg_match('/var dailyForecast =(.+);/', $res, $match);
    $json = json_decode($match[1]);
    error_log(print_r($json, true));
    */
    
    /*
    $res = $mu_->get_contents('https://www.pakutaso.com/animal/cat/', null, true);
    // error_log($res);
    
    // <p class="align -right" style="margin-top:10px"><small>(\d+)
    $rc = preg_match('/<p class="align -right" style="margin-top:10px"><small>(\d+)/', $res, $match);
    
    error_log($match[1]);
    */
}

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
    
    $res = $mu_->get_contents('https://www.pakutaso.com/animal/cat/', null, true);
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
        $res = $mu_->get_contents($url, null, true);
        // error_log($res);
        $rc = preg_match('/"thumbnailUrl":"(.+?)"/', $res, $match);
        // error_log(print_r($match, true));
        // $res = $mu_->get_contents($match[1], $options);
        $line = 'curl -v -o ' . $file . ' ' . $match[1] . ' 2>&1';
        // $mu_->cmd_execute($line);
        // error_log(filesize($file));
        break;
    }
    
    $line = 'curl -o ' . $file . ' https://farm8.staticflickr.com/7151/6760135001_14c59a1490_o.jpg';
    $mu_->cmd_execute($line);
    error_log(filesize($file));
    
    $line = 'exiftool -all= ' . $file;
    $mu_->cmd_execute($line);
    
    /*
    $line = 'convert -geometry "450%" ' . $file . ' ' . $file . '.jpg';
    $mu_->cmd_execute($line);
    
    unlink($file);
    clearstatcache();
    rename($file . '.jpg', $file);
    */
    
    error_log(filesize($file));
    
    $line = 'outguess -p 100 -k password -d ' . $file_name . ' ' . $file . ' ' . $file . '.jpg';
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
