<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$mu = new MyUtils();

$url = 'http://the-outlets-hiroshima.com/static/detail/car';
$res = $mu->get_contents($url);

$rc = preg_match('/<p id="parkingnow"><img src="(.+?)"/s', $res, $matches);
$res = $mu->get_contents($matches[1]);

$im = imagecreatefromstring($res);

// $im3 = imagecrop($im, ['x' => 0, 'y' => 95, 'width' => imagesx($im), 'height' => imagesy($im) - 145]);
$im3 = imagecrop($im, ['x' => 100, 'y' => 95, 'width' => imagesx($im) - 200, 'height' => imagesy($im) - 145]);

$canvas = imagecreatetruecolor(imagesx($im3) / 4, imagesy($im3) / 4);
imagecopyresampled($canvas, $im3, 0, 0, 0, 0, imagesx($im3) / 4, imagesy($im3) / 4, imagesx($im3), imagesy($im3));

$file = '/tmp/sample.png';
imagepng($canvas, $file);
// header('Content-Type: image/png');
// echo file_get_contents($file);

$im4 = imagecreatefrompng($file);
$x = imagesx($im4);
$y = imagesy($im4);

$check_point = 0;
for ($x = 0; $x < imagesx($im4); $x++) {
  $count = 0;
  for ($y = 0; $y < imagesy($im4); $y++) {
    $rgb = imagecolorat($im4, $x, $y);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b =  $rgb & 0xFF;
    // error_log($x . ' ' . $y . ' ' . $r . ' ' . $g . ' ' . $b);
    if ($r > 200 && $g > 200 && $b > 200) {
      $count++;
    }
  }
  error_log($x . ' ' . $count);
  if ($check_point == 0 && $count < 15) {
    $check_point = 1;
  } else if ($check_point == 1 && $count > 15) {
    $check_point = $x;
    break;
  }
}

$im5 = imagecrop($im4, ['x' => $check_point, 'y' => 0, 'width' => imagesx($im4) - $check_point, 'height' => imagesy($im4)]);

header('Content-Type: image/png');
imagepng($im5, $file);
echo file_get_contents($file);

$url = 'https://api.ocr.space/parse/image';

$post_data = ['base64image' => 'data:image/jpg;base64,' . base64_encode(file_get_contents($file))];

$options = [
  CURLOPT_POST => TRUE,
  CURLOPT_HTTPHEADER => ['apiKey: ' . getenv('OCRSPACE_API_KEY')],
  CURLOPT_POSTFIELDS => http_build_query($post_data),
  CURLOPT_TIMEOUT => 20,
  ];

$res = $mu->get_contents($url, $options);

$data = json_decode($res);
error_log(print_r($data, TRUE));
error_log(trim($data->ParsedResults[0]->ParsedText));

imagedestroy($im);
imagedestroy($im3);
imagedestroy($im4);
imagedestroy($im5);
?>
