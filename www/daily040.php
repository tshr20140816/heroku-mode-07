<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$url = $mu->get_env('URL_FEED43');
$host_name = parse_url($url, PHP_URL_HOST);
$res = $mu->get_contents($url, null, true);

$rc = preg_match_all('/<a class="title" href="(.+?)">/s', $res, $matches);

$urls = [];
foreach ($matches[1] as $item) {
    $url = 'https://' . $host_name . $item;
    $res = $mu->get_contents($url, null, true);
    $rc = substr_count($res, '<item>');
    error_log("${pid} ${rc} ${url}");
    if ($rc == 0) {
        $urls[] = $url;
    }
}

$xml_text = <<< __HEREDOC__
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>feed43</title>
    <link>__LINK__</link>
    <description>none</description>
    <language>ja</language>
    <item>
       <guid isPermaLink="false">__GUID__</guid>
       <pubDate/>
       <title>__TITLE__</title>
       <link>__LINK__</link>
       <description>__DESCRIPTION__</description>
    </item>
  </channel>
</rss>
__HEREDOC__;

if (count($urls) != 0) {
    $description = htmlspecialchars(nl2br(implode("\n", $urls)));
    $title = date('Y/m/d H:i:s', strtotime('+9 hours'));
    $guid = hash('sha256', $title . $description);
    $link = $mu->get_env('URL_FEED43');
    $xml_text = str_replace('__DESCRIPTION__', $description, $xml_text);
    $xml_text = str_replace('__GUID__', $guid, $xml_text);
    $xml_text = str_replace('__LINK__', $link, $xml_text);
    $xml_text = str_replace('__TITLE__', $title, $xml_text);
}

error_log(print_r($urls, true));
error_log($xml_text);

/*
$pdo = $mu->get_pdo();

$sql = 'DELETE FROM t_rss WHERE rss_id = 1';
$statement = $pdo->prepare($sql);
$rc = $statement->execute();
error_log($pid . ' DELETE $rc : ' . $rc);

$sql = 'INSERT INTO t_rss (rss_id, rss_data) VALUES (1, :b_rss_data)';
$statement = $pdo->prepare($sql);
$rc = $statement->execute([':b_rss_data' => base64_encode(gzencode($xml_text, 9))]);
error_log($pid . ' INSERT $rc : ' . $rc);

$pdo = null;
*/

$file = '/tmp/' . getenv('FC2_RSS_06') . '.xml';
file_put_contents($file, $xml_text);
$filesize = filesize($file);
$mu->upload_fc2($file);
unlink($file);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
