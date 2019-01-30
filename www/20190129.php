<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

check_lib($mu);

$time_finish = microtime(true);
error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's');

function check_lib($mu_, $order_ = 0) {

    $sql = <<< __HEREDOC__
SELECT M1.lib_id
      ,M1.lib_password
      ,M1.symbol
  FROM m_lib_account M1
 ORDER BY M1.symbol
;
__HEREDOC__;
    
    $pdo = $mu_->get_pdo();
    $list_lib_id = [];
    $tmp = $pdo->query($sql);
    error_log(print_r($tmp, true));
    // foreach ($pdo->query($sql) as $row) {
    foreach ($tmp as $row) {
        $list_lib_id[] = base64_decode($row['lib_id']) . ',' . base64_decode($row['lib_password']) . ',' . base64_decode($row['symbol']);
    }
    $pdo = null;

    if (count($list_lib_id) === 0 || count($list_lib_id) <= $order_) {
        return;
    }
    
    $tmp = explode(',', $list_lib_id[$order_]);
    $lib_id = $tmp[0];
    $lib_password = $tmp[1];
    $symbol = $tmp[2];
    
    $cookie = $tmpfname = tempnam("/tmp", time());

    $options1 = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ];
    
    $url = getenv('LIB_URL');
    $res = $mu_->get_contents($url, $options1);
    
    error_log($res);

    $rc = preg_match('/<form name="LoginForm" method="post" action="(.+?)"/', $res, $match);
    
    error_log(print_r($match, true));
    
    $url = 'https://' . parse_url(getenv('LIB_URL'))['host'] . $match[1];
    
    $post_data = [
        'txt_usercd' => $lib_id,
        'txt_password' => $lib_password,
        'submit_btn_login' => 'ログイン',
        ];
    
    $options2 = [
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
    ];
    
    $res = $mu_->get_contents($url, $options2);
    
    error_log($res);
        $rc = preg_match('/<LI style="float:none;">利用可能な資料があります。（(\d+)冊）<\/LI>/s', $res, $match);
    error_log($rc);
    error_log(print_r($match, true));
    
    $rc = preg_match('/<dd>現在、借受中の資料です。<.*?<p class="number"><span>(\d+?)</s', $res, $match);
    error_log($rc);
    error_log(print_r($match, true));
    
    $rc = preg_match('/<dd>予約状況を確認できます。<.*?<p class="number"><span>(\d+?)</s', $res, $match);
    error_log($rc);
    error_log(print_r($match, true));
    
    $rc = preg_match('/<dd>予約かごに入れた資料を確認できます。<.*?<p class="number"><span>(\d+?)</s', $res, $match);
    error_log($rc);
    error_log(print_r($match, true));
    
    /*
    $url = 'https://' . parse_url(getenv('LIB_URL'))['host'] . '/winj/opac/reserve-list.do';
    $res = $mu_->get_contents($url, $options1);
    
    error_log($res);
    */
    
    unlink($cookie);
}
