<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
error_log("${pid} START ${requesturi}");

$mu = new MyUtils();

// Access Token

$access_token = $mu->get_access_token();

// Last-Modified

$url = 'https://fukkou-shuyu.jp/';
$res = $mu->get_contents($url . '?' . md5(uniqid()), [CURLOPT_HEADER => 1]);

$rc = preg_match('/Last-Modified: (.+)/', $res, $matches);

error_log($pid . ' ' . print_r($matches, TRUE));

$tmp = strtotime($matches[1]);

error_log($pid . ' ' . date('Y/m/d H:i:s', $tmp));
error_log($pid . ' ' . date('Y/m/d H:i:s', strtotime('+9 hours', $tmp)));

// add task

$tmp = '[{"title":"' . date('Y/m/d H:i:s', strtotime('+9 hours', $tmp)) . ' ' . $url . ' ' . date('m/d H:i:s', strtotime('+9 hours'))
  . '","duedate":"' . mktime(0, 0, 0, 1, 2, 2018). '"}]';
$post_data = ['access_token' => $access_token, 'tasks' => $tmp];

$res = $mu->get_contents(
  'https://api.toodledo.com/3/tasks/add.php',
  [CURLOPT_POST => TRUE,
   CURLOPT_POSTFIELDS => http_build_query($post_data),
  ]);
error_log("${pid} add.php RESPONSE : ${res}");

error_log("${pid} FINISH");

exit();

function get_contents($url_, $options_ = NULL) {
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url_,
    CURLOPT_USERAGENT => getenv('USER_AGENT'),
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_ENCODING => '',
    CURLOPT_FOLLOWLOCATION => 1,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_SSL_FALSESTART => TRUE,
    ]);
  if (is_null($options_) == FALSE) {
    curl_setopt_array($ch, $options_);
  }
  $res = curl_exec($ch);
  curl_close($ch);
  
  return $res;
}

?>
