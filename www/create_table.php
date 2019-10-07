<?php

$connection_info = parse_url(getenv('DATABASE_URL'));
$pdo = new PDO(
    "pgsql:host=${connection_info['host']};dbname=" . substr($connection_info['path'], 1),
    $connection_info['user'],
    $connection_info['pass']
);

//

$sql = <<< __HEREDOC__
CREATE TABLE m_authorization (
  access_token character varying(255) NOT NULL
 ,expires_in bigint NOT NULL
 ,refresh_token character varying(255) NOT NULL
 ,scope character varying(255) NOT NULL
 ,create_time timestamp DEFAULT localtimestamp NOT NULL
 ,update_time timestamp DEFAULT localtimestamp NOT NULL
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('m_authorization create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE t_webcache (
    url_base64 character varying(1024) PRIMARY KEY,
    content_compress_base64 text,
    update_time timestamp without time zone DEFAULT LOCALTIMESTAMP NOT NULL
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_webcache create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE m_tenki (
    location_number character varying(5) NOT NULL,
    point_name character varying(100) NOT NULL,
    yyyymmdd character varying(8) NOT NULL,
    PRIMARY KEY (location_number, point_name, yyyymmdd)
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('m_tenki create table result : ' . $count);

$sql = <<< __HEREDOC__
CREATE TABLE t_ical (
    ical_data text
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_ical create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE t_imageparsehash (
    group_id integer NOT NULL,
    hash_text character varying(128) NOT NULL,
    parse_text text NOT NULL,
    update_time timestamp without time zone DEFAULT LOCALTIMESTAMP NOT NULL,
    PRIMARY KEY (group_id, hash_text)
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_imageparsehash create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE m_env (
    key character varying(128) PRIMARY KEY,
    value character varying(512) NOT NULL
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('m_env create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE m_lib_account (
    lib_id character varying(64) PRIMARY KEY,
    lib_password character varying(64) NOT NULL,
    symbol character varying(3) NOT NULL,
    update_time timestamp without time zone DEFAULT LOCALTIMESTAMP NOT NULL
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('m_lib_account create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE t_check_webpage (
    url_base64 character varying(1024) PRIMARY KEY,
    content_compress_base64 text,
    last_modified character varying(64),
    etag character varying(64),
    hash character varying(128),
    type integer NOT NULL,
    update_time timestamp without time zone DEFAULT LOCALTIMESTAMP NOT NULL
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_check_webpage create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE t_mail (
    uid character varying(255) PRIMARY KEY
   ,no bigint NOT NULL
   ,header text NOT NULL
   ,body text
)
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_mail create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE t_waon_history (
    check_time timestamp PRIMARY KEY,
    balance int,
    last_use_date date
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_waon_history create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE t_rss (
    rss_id int PRIMARY KEY,
    rss_data text
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE t_blog_post (
    blog_site character varying(100) NOT NULL,
    yyyymmdd character varying(8) NOT NULL,
    post_count int NOT NULL,
    PRIMARY KEY (blog_site, yyyymmdd)
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_blog_post create table result : ' . $count);

//

$sql = <<< __HEREDOC__
CREATE TABLE t_data_log (
    key character varying(128) PRIMARY KEY,
    value text
);
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_data_log create table result : ' . $count);

$pdo = null;
