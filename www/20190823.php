<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');
// require_once('Zend/XmlRpc/Client.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190823j($mu);
// @unlink('/tmp/dummy');

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190823j($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    
    // $res = $mu_->get_contents(getenv('TEST_URL_01'));
    // error_log($res);
    
    $file_name = '/tmp/sample.xml';
    // file_put_contents($file_name, $res);
    $base_name = pathinfo($file_name)['basename'];
    
    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);
    
    $line = "curl -v -m 120 -X DELETE " .
        "-u ${user_hidrive}:${password_hidrive} https://webdav.hidrive.strato.com/users/${user_hidrive}/${base_name}";
    $mu_->cmd_execute($line);
    unlink($file_name);
}

function func_20190823k($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    error_log("${log_prefix}memory_get_usage : " . number_format(memory_get_usage()) . 'byte');
    
    // https://www.flickr.com/
    
    /*
    $res = file_get_contents('../bin/curl');
    $filesize = strlen($res);
    error_log('$filesize : ' . $filesize);
    */
    
    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);
    
    $url = getenv('TEST_URL_01');
    /*
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
    ];
    $res = $mu_->get_contents($url, $options, true);
    */
    $line = "curl -u ${user_hidrive}:${password_hidrive} -o /tmp/testdata ${url}";
    $mu_->cmd_execute($line);

    $filesize = filesize('/tmp/testdata');
    error_log('$filesize : ' . $filesize);
    error_log("${log_prefix}memory_get_usage : " . number_format(memory_get_usage()) . 'byte');
    
    $full_size = $filesize;
    $tmp = $filesize % 3;
    if ($tmp !== 0) {
        $full_size += 3 - $tmp;
    }
    $full_size /= 3;
    error_log('$full_size : ' . $full_size);
    
    error_log(ceil(sqrt($full_size)));
    
    $width = ceil(sqrt($full_size));
    $height = $width;
    error_log('$height : ' . $height);
    
    error_log("${log_prefix} im pre memory_get_usage : " . number_format(memory_get_usage()) . 'byte');
    $im = imagecreatetruecolor($width, $height);
    error_log("${log_prefix} im after memory_get_usage : " . number_format(memory_get_usage()) . 'byte');
    $fp = fopen('/tmp/testdata', 'rb');
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $r = 0;
            $g = 0;
            $b = 0;
            if (feof($fp) === false) {
                $r = hexdec(bin2hex(fread($fp, 1)));
            }
            if (feof($fp) === false) {
                $g = hexdec(bin2hex(fread($fp, 1)));
            }
            if (feof($fp) === false) {
                $b = hexdec(bin2hex(fread($fp, 1)));
            }
            $color = imagecolorallocate($im, $r, $g, $b);
            imagesetpixel($im, $x, $y, $color);
            $color = null;
        }
    }
    fclose($fp);
    unlink('/tmp/testdata');
    header('Content-Type: image/png');
    error_log("${log_prefix}memory_get_usage : " . number_format(memory_get_usage()) . 'byte');
    // imagepng($im);
    imagepng($im, '/tmp/testfile.png');
    imagedestroy($im);
    error_log(filesize('/tmp/testfile.png'));
    unlink('/tmp/testfile.png');
    /*
    imagepng($im, '/tmp/testfile.png');
    imagedestroy($im);
    
    $res = null;
    $im = null;
    
    $im = imagecreatefrompng('/tmp/testfile.png');
    
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($im, $x, $y);
            error_log(dechex(($rgb >> 16) & 0xFF) . ' ' . dechex(($rgb >> 8) & 0xFF) . ' ' . dechex($rgb & 0xFF));
        }
    }
    imagedestroy($im);
    unlink('/tmp/testfile.png');
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
