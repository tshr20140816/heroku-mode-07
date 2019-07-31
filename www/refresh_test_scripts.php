<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

/*
$url = 'https://github.com/tshr20140816/heroku-mode-07/tree/master/www';

$res = $mu->get_contents($url);

$rc = preg_match_all('/<a .+? title="(\d+?)\.php"/', $res, $matches);

error_log(print_r($matches, true));

foreach ($matches[1] as $item) {
    $url = 'https://raw.githubusercontent.com/tshr20140816/heroku-mode-07/master/www/' . $item . '.php?' . microtime(true);
    $res = $mu->get_contents($url);
    $hash_new = hash('sha512', $res);
    $hash_old = hash('sha512', file_get_contents($item . '.php'));

    error_log($item . '.php');
    error_log('HASH OLD : ' . $hash_old);
    error_log('HASH NEW : ' . $hash_new);
    if ($hash_new != $hash_old) {
        unlink($item . '.php');
        file_put_contents($item . '.php', $res);
        // error_log($item . '.php');
        error_log($res);
    }
}
*/

exec('cd /tmp && git clone --depth=1 https://github.com/tshr20140816/heroku-mode-07.git repo', $res);
error_log(print_r($res, true));

$res = [];
exec('ls -lang /tmp/repo/www/*.php', $res);
error_log(print_r($res, true));


$rc = opcache_reset();
error_log('opcache_reset : ' . $rc);
