<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$time_start = microtime(true);
error_log("${pid} START scripts/update_ttrss.php " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

update_ttrss($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function update_ttrss($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $sql_select = <<< __HEREDOC__
SELECT M1.value
  FROM m_env M1
 WHERE M1.key = 'URL_TTRSS_' || ( SELECT M2.value
                                    FROM m_env M2
                                   WHERE M2.key = 'TTRSS_SELECTED'
                                )
__HEREDOC__;

    $pdo = $mu_->get_pdo();

    foreach ($pdo->query($sql_select) as $row) {
        $url = $row['value'];
    }

    $pdo = null;

    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD'),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json',],
        CURLOPT_POST => true,
    ];

    $url .= 'api/';
    $login_user = $mu_->get_env('TTRSS_USER', true);
    $login_password = $mu_->get_env('TTRSS_PASSWORD', true);
    $json = '{"op":"login","user":"' . $login_user .'","password":"' . $login_password . '"}';
    $res = $mu_->get_contents($url, $options + [CURLOPT_POSTFIELDS => $json,]);
    $data = json_decode($res);
    $session_id = $data->content->session_id;

    $urls = [];

    $livedoor_id = $mu_->get_env('LIVEDOOR_ID', true);
    $urls[] = "http://blog.livedoor.jp/${livedoor_id}/atom.xml";

    $hatena_blog_id = $mu_->get_env('HATENA_BLOG_ID', true);
    $urls[] = "https://${hatena_blog_id}/feed";

    $fc2_fqdn = $mu_->get_env('FC2_FTP_SERVER', true);
    if ((int)date('H', strtotime('+9 hours')) < 19) {
        $urls[] = "https://${fc2_fqdn}/" . getenv('FC2_RSS_01') . '.xml';
        $urls[] = "https://${fc2_fqdn}/" . getenv('FC2_RSS_02') . '.xml';
        $urls[] = "https://${fc2_fqdn}/" . getenv('FC2_RSS_03') . '.xml';
        $urls[] = "https://${fc2_fqdn}/" . getenv('FC2_RSS_04') . '.xml';
        $urls[] = "https://${fc2_fqdn}/" . getenv('FC2_RSS_05') . '.xml';
    }
    // error_log($log_prefix . print_r($urls, true));
    $mu_->logging_object($urls, $log_prefix);

    $json = '{"sid":"' . $session_id . '","op":"getFeeds","cat_id":-3}';
    $res = $mu_->get_contents($url, $options + [CURLOPT_POSTFIELDS => $json,]);
    $data = json_decode($res);
    foreach ($data->content as $feed) {
        error_log($log_prefix . 'feed_url : ' . $feed->feed_url);
        error_log($log_prefix . 'feed_id : ' . $feed->id);
        if (in_array($feed->feed_url, $urls)) {
            $json = '{"sid":"' . $session_id . '","op":"updateFeed","feed_id":' . $feed->id . '}';
            $res = $mu_->get_contents($url, $options + [CURLOPT_POSTFIELDS => $json,]);
            // error_log($log_prefix . print_r(json_decode($res), true));
            $mu_->logging_object(json_decode($res), $log_prefix);
        }
    }
}
