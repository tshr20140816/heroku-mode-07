<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$description = backup_cloudapp($mu);

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/backup_opendrive.php';
exec('curl -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");

$time_finish = microtime(true);
$mu->post_blog_wordpress("${requesturi} [" . substr(($time_finish - $time_start), 0, 6) . 's]', $description);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function backup_cloudapp($mu_)
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
                $files[] = $match[1];
            }
        }
    }

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

    $size = 0;
    $view_counter = 0;
    $page = 0;
    for (;;) {
        $page++;
        $url = 'http://my.cl.ly/items?per_page=100&page=' . $page;
        $res = $mu_->get_contents($url, $options, true);
        $json = json_decode($res);
        if (count($json) === 0) {
            break;
        }
        foreach ($json as $item) {
            $size += $item->content_length;
            $view_counter += $item->view_counter;
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
        // $res = $mu_->get_contents($url, $options);
        @unlink("/tmp/${base_name}");
        // file_put_contents("/tmp/${base_name}", $res);
        
        $line = 'curl -v -m 60 -o ' . "/tmp/${base_name}" . ' -u ' . "${user_hidrive}:${password_hidrive} " . $url;
        error_log($log_prefix . $line);
        $res = null;
        exec($line, $res);
        error_log($log_prefix . print_r($res, true));
        $res = null;

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

    $size = number_format($size);
    return "CloudApp usage : ${size}Byte ${view_counter}View";
}
