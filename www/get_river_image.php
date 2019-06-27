<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

get_river_image($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function get_river_image($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $url = $mu_->get_env('URL_RIVER_IMAGE_1') . '?' . hash('md5', microtime(true));
    $res = $mu_->get_contents($url);

    // error_log($res);
    $rc = preg_match('/<img alt="最新監視カメラ画像".+? src="(.+?)"/s', $res, $match);
    error_log($log_prefix . print_r($match, true));
    $url = 'http://' . parse_url($url, PHP_URL_HOST) . $match[1];
    $res = $mu_->get_contents($url);
    $description = '<img src="data:image/jpeg;base64,' . base64_encode($res) . '" />';
    $description = '<![CDATA[' . $description . ']]>';

    $xml_text = <<< __HEREDOC__
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">
<channel>
<title>River Image</title>
<link>http://dummy.local/</link>
<description>River Image</description>
<item>
<guid isPermaLink="false">__HASH__</guid>
<pubDate>__PUBDATE__</pubDate>
<title>River Image</title>
<link>http://dummy.local/</link>
<description>__DESCRIPTION__</description>
</item>
</channel>
</rss>
__HEREDOC__;

    $xml_text = str_replace('__DESCRIPTION__', $description, $xml_text);
    $xml_text = str_replace('__PUBDATE__', date('D, j M Y G:i:s +0900', strtotime('+9 hours')), $xml_text);
    $xml_text = str_replace('__HASH__', hash('sha256', $description), $xml_text);
    $file_name = '/tmp/' . getenv('FC2_RSS_05') . '.xml';
    file_put_contents($file_name, $xml_text);
    $mu_->upload_fc2($file_name);
    unlink($file_name);
}
