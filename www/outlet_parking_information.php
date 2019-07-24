<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$url = $mu->get_env('URL_OUTLET');
$res = $mu->get_contents($url);

$rc = preg_match('/<p id="parkingnow"><img src="(.+?)"/s', $res, $matches);
$res = $mu->get_contents($matches[1]);
$hash_text = hash('sha512', $res);

$pdo = $mu->get_pdo();

$sql = <<< __HEREDOC__
SELECT T1.parse_text
  FROM t_imageparsehash T1
 WHERE T1.group_id = 1
   AND T1.hash_text = :b_hash_text;
__HEREDOC__;

$statement = $pdo->prepare($sql);
$rc = $statement->execute([':b_hash_text' => $hash_text]);
error_log("${pid} SELECT RESULT : ${rc}");
$results = $statement->fetchAll();
error_log($pid . ' $results : ' . print_r($results, true));

$parse_text = '';
foreach ($results as $row) {
    $parse_text = $row['parse_text'];
}

$pdo = null;

if (strlen($parse_text) > 0) {
    file_put_contents('/tmp/outlet_parking_information.txt', $parse_text);
    error_log("${pid} (CACHE HIT)PARSE TEXT ${parse_text}");
    $time_finish = microtime(true);
    error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's');
    exit();
}

// error_log($pid . ' NEW IMAGE (BASE64) : ' . base64_encode($res));
error_log($pid . ' NEW IMAGE (GZIP BASE64) : ' . gzencode(base64_encode($res), 9));

/*
$im1 : original
$im2 : 上段、下段カット 左右も少しカット
$im3 : サイズ 1/4
$im4 : Pマーク 除去 → png
*/
$im1 = imagecreatefromstring($res);

$im2 = imagecrop($im1, ['x' => 100, 'y' => 95, 'width' => imagesx($im1) - 200, 'height' => imagesy($im1) - 145]);
imagedestroy($im1);

$im3 = imagecreatetruecolor(imagesx($im2) / 4, imagesy($im2) / 4);
imagecopyresampled($im3, $im2, 0, 0, 0, 0, imagesx($im2) / 4, imagesy($im2) / 4, imagesx($im2), imagesy($im2));
imagedestroy($im2);

$check_point = 0;
for ($x = 0; $x < imagesx($im3); $x++) {
    $count = 0;
    for ($y = 0; $y < imagesy($im3); $y++) {
        $rgb = imagecolorat($im3, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b =  $rgb & 0xFF;
        if ($r > 200 && $g > 200 && $b > 200) {
            $count++;
        }
    }
    error_log($pid . ' $x $count : ' . $x . ' ' . $count);
    if ($check_point == 0 && $count < 15) {
        $check_point = 1;
    } elseif ($check_point == 1 && $count > 15) {
        $check_point = $x;
        break;
    }
}

$im4 = imagecrop($im3, ['x' => $check_point, 'y' => 0, 'width' => imagesx($im3) - $check_point, 'height' => imagesy($im3)]);
imagedestroy($im3);

$file = '/tmp/outlet_parking_information.png';
imagepng($im4, $file);
imagedestroy($im4);

$url = 'https://api.cloudmersive.com/ocr/image/toText';

$post_data = ['imageFile' => new CURLFile($file)];

$options = [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Apikey: ' . $mu->get_env('CLOUDMERSIVE_API_KEY', true)),
                           'Accept: application/json'],
    CURLOPT_POSTFIELDS => $post_data,
    CURLOPT_TIMEOUT => 20,
];

$res = $mu->get_contents($url, $options);

$data = json_decode($res);
error_log($pid . ' $data : ' . print_r($data, true));

$parse_text = str_replace('0/0', '%', trim($data->TextResult));
file_put_contents('/tmp/outlet_parking_information.txt', $parse_text);
error_log("${pid} PARSE TEXT ${parse_text}");

if (strlen($parse_text) > 0) {
    $pdo = $mu->get_pdo();

    $sql = <<< __HEREDOC__
INSERT INTO t_imageparsehash
(
 group_id
,hash_text
,parse_text
) VALUES (
 1
,:b_hash_text
,:b_parse_text
);
__HEREDOC__;

    $statement = $pdo->prepare($sql);
    $rc = $statement->execute([':b_hash_text' => $hash_text,
                             ':b_parse_text' => $parse_text]);
  
    error_log("${pid} INSERT RESULT : ${rc}");
  
    $pdo = null;
}
$time_finish = microtime(true);
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's');
