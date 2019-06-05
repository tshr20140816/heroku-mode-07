<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$file_name_blog = tempnam('/tmp', 'blog_' . md5(microtime(true)));
@unlink($file_name_rss_items);

$rc = func_20190602($mu, $file_name_blog);

$time_finish = microtime(true);

error_log("${pid} FINISH " . substr(($time_finish - $time_start), 0, 6) . 's ' . substr((microtime(true) - $time_start), 0, 6) . 's');
exit();

function func_20190602($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    /*
    $array['20190101'] = '10';
    $array['20190102'] = '20';
    
    error_log(json_encode($array));
    error_log(print_r(json_decode(json_encode($array), true), true));
    return;
    */
    
    $list_targets = [];
    $list_targets[] = 'TOODLEDO';
    $list_targets[] = 'TTRSS';
    $list_targets[] = 'REDMINE';
    $list_targets[] = 'FIRST';
    $list_targets[] = 'KYOTO';
    
    $urls = [];
    foreach ($list_targets as $target) {
        if (getenv('HEROKU_API_KEY_' . $target) == '') {
            $api_key = getenv('HEROKU_API_KEY');
        } else {
            $api_key = base64_decode(getenv('HEROKU_API_KEY_' . $target));
        }
        $options = [CURLOPT_HTTPHEADER => ['Accept: application/vnd.heroku+json; version=3',
                                           "Authorization: Bearer ${api_key}",
                                          ]];
        
        $urls['https://api.heroku.com/account?' . hash('md5', $target)] = $options;
    }
    
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 10,
    ];
    $list_contents = $mu_->get_contents_multi($urls, null, $multi_options);
    
    $urls = [];
    foreach ($list_targets as $target) {
        $data = json_decode($list_contents['https://api.heroku.com/account?' . hash('md5', $target)], true);
        error_log($log_prefix . '$data : ' . print_r($data, true));

        $account = explode('@', $data['email'])[0];
        if (getenv('HEROKU_API_KEY_' . $target) == '') {
            $api_key = getenv('HEROKU_API_KEY');
        } else {
            $api_key = base64_decode(getenv('HEROKU_API_KEY_' . $target));
        }
        $options = [CURLOPT_HTTPHEADER => ['Accept: application/vnd.heroku+json; version=3.account-quotas',
                                           "Authorization: Bearer ${api_key}",
                                          ]];
        $urls["https://api.heroku.com/accounts/${data['id']}/actions/get-quota?" . hash('md5', $target)] = $options;
    }
    $list_contents = null;
    
    $multi_options = [
        CURLMOPT_PIPELINING => 3,
        CURLMOPT_MAX_HOST_CONNECTIONS => 10,
    ];
    $list_contents = $mu_->get_contents_multi($urls, null, $multi_options);
    
    $sql_select = <<< __HEREDOC__
SELECT T1.value
  FROM t_data_log T1
 WHERE T1.key = :b_key
__HEREDOC__;    
    
    $sql_upsert = <<< __HEREDOC__
INSERT INTO t_data_log VALUES(:b_key, :b_value)
    ON CONFLICT (key)
    DO UPDATE SET value = :b_value
__HEREDOC__;

    $pdo = $mu_->get_pdo();
    foreach ($list_targets as $target) {
        $hash = hash('md5', $target);
        foreach ($list_contents as $url => $contents) {
            if (substr($url, strlen($hash) * -1) === $hash) {
                $data = json_decode($contents, true);
                error_log($log_prefix . '$data : ' . print_r($data, true));

                $dyno_used = (int)$data['quota_used'];
                $dyno_quota = (int)$data['account_quota'];

                error_log($log_prefix . '$dyno_used : ' . $dyno_used);
                error_log($log_prefix . '$dyno_quota : ' . $dyno_quota);
                
                $quota = $dyno_quota - $dyno_used;
                
                $quotas = [];
                if ($j == 1) {
                    $quotas[date('Ymd', strtotime('+9 hours'))] = $quota;
                    $statement = $pdo->prepare($sql_upsert);
                    $rc = $statement->execute([':b_key' => $target,
                                               ':b_value' => json_encode($quotas),
                                              ]);
                    error_log($log_prefix . 'UPSERT $rc : ' . $rc);
                } else {
                    
                }
                
                break;
            }
        }
    }
    $list_contents = null;
    $pdo = null;
    
    error_log($log_prefix . '$list_quota : ' . print_r($list_quota, true));
    
    return;

    $keyword = strtolower($target_);
    for ($i = 0; $i < strlen($keyword); $i++) {
        $keyword[$i] = chr(ord($keyword[$i]) + 1);
    }
    $keyword .= 'rvpub';

    $description = '';
    $j = (int)date('j', strtotime('+9hours'));
    if ($j != 1) {
        $description = $mu_->search_blog($mu_->to_next_word('allquota'));
    }
    if (strpos($description, " ${j},") == false) {
        $description = '<div class="' . $keyword . '">' . trim($description . " ${j}," . (int)($quota / 60)) . '</div>';
        // $mu_->post_blog_hatena($keyword, $description);
        // $mu_->post_blog_wordpress($keyword, $description);
        $mu_->post_blog_wordpress_async($keyword, $description);
    }

    $quota = floor($quota / 86400) . 'd ' . ($quota / 3600 % 24) . 'h ' . ($quota / 60 % 60) . 'm';

    file_put_contents($file_name_blog_, "\nQuota " . strtolower($target_) . " : ${quota}\n", FILE_APPEND);
}
