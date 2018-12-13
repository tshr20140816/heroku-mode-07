<?php

$connection_info = parse_url(getenv('DATABASE_URL'));
$pdo = new PDO(
  "pgsql:host=${connection_info['host']};dbname=" . substr($connection_info['path'], 1),
  $connection_info['user'],
  $connection_info['pass']);

$sql = <<< __HEREDOC__
SELECT 'X'
  FROM pg_class V1
 WHERE V1.relkind = 'r'
   AND V1.relname = 'm_authorization';
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('m_authorization : ' . $count);

if ($count == 0) {
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
  error_log('create table result : ' . $count);
}

$sql = <<< __HEREDOC__
SELECT 'X'
  FROM pg_class V1
 WHERE V1.relkind = 'r'
   AND V1.relname = 't_webcache';
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('t_webcache : ' . $count);

if ($count == 0) {
  $sql = <<< __HEREDOC__
CREATE TABLE public.t_webcache (
    url_base64 character varying(1024) PRIMARY KEY,
    content_compress_base64 text,
    update_time timestamp without time zone DEFAULT LOCALTIMESTAMP NOT NULL
);
__HEREDOC__;
  $count = $pdo->exec($sql);
  error_log('create table result : ' . $count);
}

$sql = <<< __HEREDOC__
SELECT 'X'
  FROM pg_class V1
 WHERE V1.relkind = 'r'
   AND V1.relname = 'm_tenki';
__HEREDOC__;
$count = $pdo->exec($sql);
error_log('m_tenki : ' . $count);

if ($count == 0) {
  $sql = <<< __HEREDOC__
CREATE TABLE public.m_tenki (
    location_number character varying(5) NOT NULL,
    point_name character varying(100) NOT NULL,
    yyyymmdd character varying(8) NOT NULL
);
CREATE UNIQUE INDEX pk_m_tenki ON public.m_tenki USING btree (location_number, point_name, yyyymmdd);
ALTER TABLE public.m_tenki CLUSTER ON pk_m_tenki;
__HEREDOC__;
  $count = $pdo->exec($sql);
  error_log('create table result : ' . $count);
}

$pdo = null;

?>
