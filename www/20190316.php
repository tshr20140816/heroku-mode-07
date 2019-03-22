<?php
include(dirname(__FILE__) . '/../classes/MyUtils.php');
$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));
$mu = new MyUtils();
$rc = func_test($mu, '/tmp/dummy');

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

function func_test($mu_, $file_name_blog_)
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    
    $username = $mu_->get_env('WORDPRESS_USERNAME', true);
    $password = $mu_->get_env('WORDPRESS_PASSWORD', true);
    
    $url = 'https://' . $username . '.wordpress.com/xmlrpc.php';
    $client = XML_RPC2_Client::create($url, ['prefix' => 'wp.']);
    $result = $client->getUsersBlogs($username, $password);
    error_log(print_r($result, true));
    $blogid = $result[0]['blogid'];
    
    $client = XML_RPC2_Client::create($url, ['prefix' => 'wp.']);
    // $results = $client->getPosts($blogid, $username, $password, ['number' => 500]);
    $results = $client->getPosts(
        $blogid,
        $username,
        $password,
        ['number' => 10, 'orderby' => 'desc', 'order' => 'date'],
        ['post_title']
    );
    
    error_log(print_r($results, true));
    /*
    foreach ($results as $result) {
        error_log($result['post_title']);
    }
    */
}
