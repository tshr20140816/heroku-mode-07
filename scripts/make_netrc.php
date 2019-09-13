<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$time_start = microtime(true);
error_log("${pid} START scripts/update_ttrss.php " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$login_user = $mu->get_env('GITHUB_USER', true);
$login_password = $mu->get_env('GITHUB_PASSWORD', true);

$netrc = <<< __HEREDOC__
machine github.com
login {$login_user}
password {$login_password}
__HEREDOC__

file_put_contents('../.netrc', $netrc);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
