<?php

include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

func_20190805($mu);

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_20190805($mu_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

    $heroku_app_name = $mu_->get_env('HEROKU_APP_NAME_TTRSS');
    $database_url = $mu_->get_env('DATABASE_URL_TTRSS', true);
    
    $file_name = "/tmp/${heroku_app_name}_" .  date('d', strtotime('+9 hours')) . '_pg_dump.txt';
    error_log($log_prefix . $file_name);
    $cmd = "pg_dump --format=plain --dbname=${database_url} >${file_name}";
    $res = null;
    exec($cmd, $res);
    error_log($log_prefix . print_r($res, true));
    
    $res = hash_file('sha256', $file_name);
    // error_log($log_prefix . 'sha256 start : ' . $res);
    
    copy($file_name, $file_name . 'a');
    copy($file_name, $file_name . 'b');
    
    func_20190805c($mu_, file_get_contents($file_name . 'a'), $file_name . 'c');
    func_20190805b($mu_, $file_name . 'b');
}

function func_20190805b($mu_, $file_name_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    @unlink($file_name_ . '.bz2');
    @unlink($file_name_ . '.enc');
    
    $base_name = pathinfo($file_name_)['basename'];
    
    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);
    
    $res = null;
    exec('bzip2 -v ' . $file_name_, $res);
    error_log($log_prefix . print_r($res, true));
    
    $method = 'aes-256-cbc';
    $password = base64_encode($user_hidrive) . base64_encode($password_hidrive);
    $iv = substr(sha1($file_name_), 0, openssl_cipher_iv_length($method));
    // $res = openssl_encrypt($res, $method, $password, OPENSSL_RAW_DATA, $iv);
    
    $line = 'openssl ' . $method . ' -base64 -iv ' . $iv . ' -pass pass:' . $password . ' -in ' . $file_name_ . '.bz2 -out ' . $file_name_ . '.enc';
    error_log($log_prefix . $line);
    
    $res = null;
    exec($line, $res);
    error_log($log_prefix . print_r($res, true));
    
    error_log($log_prefix . 'size : ' . number_format(filesize($file_name_ . '.enc')));
    error_log($log_prefix . 'hash : ' . hash_file('sha256', $file_name_ . '.enc'));
}

function func_20190805c($mu_, $data_, $file_name_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    @unlink($file_name_ . '.bz2');
    @unlink($file_name_ . '.enc');
    
    $base_name = pathinfo($file_name_)['basename'];
    
    $user_hidrive = $mu_->get_env('HIDRIVE_USER', true);
    $password_hidrive = $mu_->get_env('HIDRIVE_PASSWORD', true);
    
    $file_name = tempnam("/tmp", 'bz_' .  md5(microtime(true)));
    $rc = file_put_contents($file_name, $data_);
    $data_ = null;
    $res = null;
    exec('bzip2 -v ' . $file_name, $res);
    error_log($log_prefix . print_r($res, true));
    $res = file_get_contents($file_name . '.bz2');
    unlink($file_name . '.bz2');
    
    $method = 'aes-256-cbc';
    $password = base64_encode($user_hidrive) . base64_encode($password_hidrive);
    $iv = substr(sha1($file_name_), 0, openssl_cipher_iv_length($method));
    $res = openssl_encrypt($res, $method, $password, OPENSSL_RAW_DATA, $iv);
    
    $res = base64_encode($res);
    file_put_contents($file_name_, $res);
    $res = null;
    
    error_log($log_prefix . 'size : ' . number_format(filesize($file_name_)));
    error_log($log_prefix . 'hash : ' . hash_file('sha256', $file_name_));
}
