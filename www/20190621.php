<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190621c($mu);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190621c($mu_)
{
    $url = 'https://spocale.com/team_and_players/12';
    $res = $mu_->get_contents($url);
    // error_log($res);
    
    $rc = preg_match_all('/<a href="\/games\/(.+?)">/', $res, $matches);
    error_log(print_r($matches, true));
}

function func_20190621b($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $cookie = tempnam("/tmp", 'cookie_' . md5(microtime(true)));
    
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
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
    ];
    
    $url = 'https://jr-central.co.jp/';
    $res = $mu_->get_contents($url, $options);
    
    /*
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/pc/ja/ti08.html';
    $res = $mu_->get_contents($url, $options);
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/common/data/common_ja.json';
    $res = $mu_->get_contents($url, $options);
    
    $url = 'https://traininfo.jr-central.co.jp/shinkansen/var/train_info/train_location_info.json';
    $res = $mu_->get_contents($url, $options);
    */
    unlink($cookie);
}

function func_20190621($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);

    $url = "https://webdav.hidrive.strato.com/users/${user_hidrive}/";
    
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_HTTPHEADER => ['Depth: 1',],
    ];
    
    $res = $mu_->get_contents($url, $options);
    
    $files = [];
    foreach (explode('</D:response>', $res) as $item) {
        $rc = preg_match('/<D:href>(.+?)<.+?<lp1:creationdate>(.+?)<.+?<lp1:getcontentlength>/s', $item, $match);
        if ($rc === 1) {
            if (strtotime($match[2]) > strtotime('-20 hours')) {
                error_log(print_r($match, true));
                $files[] = $match[1];
            }
            // error_log(date('Y/m/d H:i:s', strtotime($match[2])));
        }
    }

    error_log(pathinfo($files[0])['basename']);
    
    $sql_delete = <<< __HEREDOC__
DELETE
  FROM t_webcache
 WHERE url_base64 = :b_url_base64
__HEREDOC__;

    $pdo = $mu_->get_pdo();
    $statement_delete = $pdo->prepare($sql_delete);
    for ($i = 0; $i < 10; $i++) {
        $url = 'http://my.cl.ly/items?per_page=100&page=' . ($i + 1);
        $rc = $statement_delete->execute([':b_url_base64' => base64_encode($url),
                                         ]);
        error_log($log_prefix . ($i + 1) . ' ' . $rc);
        if ($rc === false) {
            break;
        }
    }
    $pdo = null;
    
    $user_cloudapp = $mu_->get_env('CLOUDAPP_USER', true);
    $password_cloudapp = $mu_->get_env('CLOUDAPP_PASSWORD', true);
    
    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
        CURLOPT_HTTPHEADER => ['Accept: application/json',],
    ];
    
    $page = 0;
    for (;;) {
        $page++;
        $url = 'http://my.cl.ly/items?per_page=100&page=' . $page;
        $res = $mu_->get_contents($url, $options, true);
        $json = json_decode($res);
        if (count($json) === 0) {
            break;
        }
    }

    $options = [
        CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
        CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
        CURLOPT_HTTPHEADER => ['Accept: application/json',],
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HEADER => true,
    ];
    
    $urls = [];
    foreach ($files as $file) {
        $page = 0;
        for (;;) {
            $page++;
            $url = 'http://my.cl.ly/items?per_page=100&page=' . $page;
            $res = $mu_->get_contents($url, null, true);
            $json = json_decode($res);
            // error_log(print_r($json, true));
            if (count($json) === 0) {
                break;
            }
            foreach ($json as $item) {
                if ($item->file_name == pathinfo($file)['basename']) {
                    $urls[$item->href] = $options;
                }
            }
        }
    }
    
    $res = $mu_->get_contents_multi($urls);
    error_log($log_prefix . 'memory_get_usage : ' . number_format(memory_get_usage()) . 'byte');
    error_log($log_prefix . print_r($res, true));
    $res = null;
    
    foreach ($files as $file) {
        $base_name = pathinfo($file)['basename'];
        $url = "https://webdav.hidrive.strato.com/users/${user_hidrive}/${base_name}";
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
            CURLOPT_CUSTOMREQUEST => 'GET',
        ];
        $res = $mu_->get_contents($url, $options);
        @unlink("/tmp/${base_name}");
        file_put_contents("/tmp/${base_name}", $res);
        
        $url = 'http://my.cl.ly/items/new';
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
            CURLOPT_HTTPHEADER => ['Accept: application/json',],
        ];
        $res = $mu_->get_contents($url, $options);
        $json = json_decode($res);

        $post_data = [
            'AWSAccessKeyId' => $json->params->AWSAccessKeyId,
            'key' => $json->params->key,
            'policy' => $json->params->policy,
            'signature' => $json->params->signature,
            'success_action_redirect' => $json->params->success_action_redirect,
            'acl' => $json->params->acl,
            'file' => new CURLFile("/tmp/${base_name}", 'text/plain', $base_name),
        ];
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        $res = $mu_->get_contents($json->url, $options);
        $rc = preg_match('/Location: (.+)/i', $res, $match);

        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
            CURLOPT_HTTPHEADER => ['Accept: application/json',],
            CURLOPT_HEADER => true,
        ];
        $res = $mu_->get_contents(trim($match[1]), $options);
        unlink("/tmp/${base_name}");
    }
    
    return;
    
}
