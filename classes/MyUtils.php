<?php

require_once 'XML/RPC2/Client.php';

class MyUtils
{
    private $_access_token = null;
    public $_count_web_access = 0;

    public function get_decrypt_string($encrypt_base64_string_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $method = 'aes-256-cbc';
        $key = getenv('ENCRYPT_KEY');
        $iv = hex2bin(substr(hash('sha512', $key), 0, openssl_cipher_iv_length($method) * 2));
        return openssl_decrypt($encrypt_base64_string_, $method, $key, 0, $iv);
    }

    public function get_encrypt_string($original_string_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $method = 'aes-256-cbc';
        $key = getenv('ENCRYPT_KEY');
        $iv = hex2bin(substr(hash('sha512', $key), 0, openssl_cipher_iv_length($method) * 2));
        return openssl_encrypt($original_string_, $method, $key, 0, $iv);
    }

    public function get_pdo()
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $connection_info = parse_url(getenv('DATABASE_URL'));
        $pdo = new PDO(
            "pgsql:host=${connection_info['host']};dbname=" . substr($connection_info['path'], 1),
            $connection_info['user'],
            $connection_info['pass']
        );
        return $pdo;
    }

    public function get_access_token()
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $file_name = '/tmp/access_token';

        if (file_exists($file_name)) {
            $timestamp = filemtime($file_name);
            if ($timestamp > strtotime('-15 minutes')) {
                $access_token = file_get_contents($file_name);
                error_log($log_prefix . '(CACHE HIT) $access_token : ' . $access_token);
                $this->_access_token = $access_token;
                return $access_token;
            }
        }

        $sql = <<< __HEREDOC__
SELECT M1.access_token
      ,M1.refresh_token
      ,M1.expires_in
      ,M1.create_time
      ,M1.update_time
      ,CASE WHEN LOCALTIMESTAMP < M1.update_time + interval '90 minutes' THEN 0 ELSE 1 END refresh_flag
  FROM m_authorization M1;
__HEREDOC__;

        $pdo = $this->get_pdo();

        $access_token = null;
        foreach ($pdo->query($sql) as $row) {
            $access_token = $row['access_token'];
            $refresh_token = $row['refresh_token'];
            $refresh_flag = $row['refresh_flag'];
        }

        if ($access_token == null) {
            error_log($log_prefix . 'ACCESS TOKEN NONE');
            exit();
        }

        if ($refresh_flag == 0) {
            $res = $this->get_contents('https://api.toodledo.com/3/folders/get.php?access_token=' . $access_token);
            if ($res == '{"errorCode":2,"errorDesc":"Unauthorized","errors":[{"status":"2","message":"Unauthorized"}]}') {
                $refresh_flag = 1;
            } else {
                file_put_contents('/tmp/folders', serialize(json_decode($res, true)));
            }
        }

        if ($refresh_flag == 1) {
            error_log($log_prefix . "refresh_token : ${refresh_token}");
            $post_data = ['grant_type' => 'refresh_token', 'refresh_token' => $refresh_token];

            $res = $this->get_contents(
                'https://api.toodledo.com/3/account/token.php',
                [CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                 CURLOPT_USERPWD => base64_decode(getenv('TOODLEDO_CLIENTID')) . ':' . base64_decode(getenv('TOODLEDO_SECRET')),
                 CURLOPT_POST => true,
                 CURLOPT_POSTFIELDS => http_build_query($post_data),
                ]
            );

            error_log($log_prefix . "token.php RESPONSE : ${res}");
            $params = json_decode($res, true);

            $sql = <<< __HEREDOC__
UPDATE m_authorization
   SET access_token = :b_access_token
      ,refresh_token = :b_refresh_token
      ,update_time = LOCALTIMESTAMP;
__HEREDOC__;

            $statement = $pdo->prepare($sql);
            $rc = $statement->execute([':b_access_token' => $params['access_token'],
                                 ':b_refresh_token' => $params['refresh_token']]);
            error_log($log_prefix . "UPDATE RESULT : ${rc}");
            $access_token = $params['access_token'];
        }
        $pdo = null;

        error_log($log_prefix . '$access_token : ' . $access_token);

        $this->_access_token = $access_token;
        file_put_contents($file_name, $access_token); // For Cache

        return $access_token;
    }

    public function get_folder_id($folder_name_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $file_name = '/tmp/folders';
        if (file_exists($file_name)) {
            $folders = unserialize(file_get_contents($file_name));
            error_log($log_prefix . '(CACHE HIT) FOLDERS');
        } else {
            $res = $this->get_contents('https://api.toodledo.com/3/folders/get.php?access_token=' . $this->_access_token, null, true);
            $folders = json_decode($res, true);
            file_put_contents($file_name, serialize($folders));
        }

        $target_folder_id = 0;
        for ($i = 0; $i < count($folders); $i++) {
            if ($folders[$i]['name'] == $folder_name_) {
                $target_folder_id = $folders[$i]['id'];
                error_log($log_prefix . "${folder_name_} FOLDER ID : ${target_folder_id}");
                break;
            }
        }
        return $target_folder_id;
    }

    public function get_contexts()
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $file_name = '/tmp/contexts';
        if (file_exists($file_name)) {
            $list_context_id = unserialize(file_get_contents($file_name));
            // error_log($log_prefix . '(CACHE HIT) $list_context_id : ' . print_r($list_context_id, true));
            error_log($log_prefix . '(CACHE HIT) $list_context_id');
            return $list_context_id;
        }

        $res = $this->get_contents('https://api.toodledo.com/3/contexts/get.php?access_token=' . $this->_access_token, null, true);
        $contexts = json_decode($res, true);
        $list_context_id = [];
        for ($i = 0; $i < count($contexts); $i++) {
            switch ($contexts[$i]['name']) {
                case '日......':
                    $list_context_id[0] = $contexts[$i]['id'];
                    break;
                case '.月.....':
                    $list_context_id[1] = $contexts[$i]['id'];
                    break;
                case '..火....':
                    $list_context_id[2] = $contexts[$i]['id'];
                    break;
                case '...水...':
                    $list_context_id[3] = $contexts[$i]['id'];
                    break;
                case '....木..':
                    $list_context_id[4] = $contexts[$i]['id'];
                    break;
                case '.....金.':
                    $list_context_id[5] = $contexts[$i]['id'];
                    break;
                case '......土':
                    $list_context_id[6] = $contexts[$i]['id'];
                    break;
            }
        }
        error_log($log_prefix . '$list_context_id : ' . print_r($list_context_id, true));

        file_put_contents($file_name, serialize($list_context_id));

        return $list_context_id;
    }

    public function add_tasks($list_add_task_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        error_log($log_prefix . 'ADD TARGET TASK COUNT : ' . count($list_add_task_));

        $list_res = [];

        if (count($list_add_task_) == 0) {
            return $list_res;
        }

        $tmp = array_chunk($list_add_task_, 50);
        for ($i = 0; $i < count($tmp); $i++) {
            $post_data = ['access_token' => $this->_access_token, 'tasks' => '[' . implode(',', $tmp[$i]) . ']'];
            $res = $this->get_contents(
                'https://api.toodledo.com/3/tasks/add.php',
                [CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post_data),
                ]
            );
            error_log($log_prefix . 'add.php RESPONSE : ' . $res);
            $list_res[] = $res;
        }

        return $list_res;
    }

    public function edit_tasks($list_edit_task_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        error_log($log_prefix . 'EDIT TARGET TASK COUNT : ' . count($list_edit_task_));

        $list_res = [];

        if (count($list_edit_task_) == 0) {
            return $list_res;
        }

        $tmp = array_chunk($list_edit_task_, 50);
        for ($i = 0; $i < count($tmp); $i++) {
            $post_data = ['access_token' => $this->_access_token, 'tasks' => '[' . implode(',', $tmp[$i]) . ']'];
            $res = $this->get_contents(
                'https://api.toodledo.com/3/tasks/edit.php',
                [CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post_data),
                ]
            );
            error_log($log_prefix . 'edit.php RESPONSE : ' . $res);
            $list_res[] = $res;
        }

        return $list_res;
    }

    public function delete_tasks($list_delete_task_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        error_log($log_prefix . 'DELETE TARGET TASK COUNT : ' . count($list_delete_task_));

        if (count($list_delete_task_) == 0) {
            return;
        }

        $tmp = array_chunk($list_delete_task_, 50);
        for ($i = 0; $i < count($tmp); $i++) {
            $post_data = ['access_token' => $this->_access_token, 'tasks' => '[' . implode(',', $tmp[$i]) . ']'];
            $res = $this->get_contents(
                'https://api.toodledo.com/3/tasks/delete.php',
                [CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post_data),
                ]
            );
            error_log($log_prefix . 'delete.php RESPONSE : ' . $res);
        }
    }

    public function get_weather_guest_area()
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $sql = <<< __HEREDOC__
SELECT T1.location_number
      ,T1.point_name
      ,T1.yyyymmdd
  FROM m_tenki T1;
__HEREDOC__;

        $pdo = $this->get_pdo();
        $list_weather_guest_area = [];
        foreach ($pdo->query($sql) as $row) {
            $location_number = $row['location_number'];
            $point_name = $row['point_name'];
            $yyyymmdd = (int)$row['yyyymmdd'];
            if ($yyyymmdd >= (int)date('Ymd') && $yyyymmdd) {
                $list_weather_guest_area[] = $location_number . ',' . $point_name . ',' . $yyyymmdd;
            }
        }
        error_log($log_prefix . '$list_weather_guest_area : ' . print_r($list_weather_guest_area, true));
        $pdo = null;

        return $list_weather_guest_area;
    }

    public function get_env($key_name_, $is_decrypt_ = false)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        if (apcu_exists(__METHOD__) === true) {
            $list_env = apcu_fetch(__METHOD__);
            error_log($log_prefix . '(CACHE HIT)$list_env');
        } else {
            $sql = <<< __HEREDOC__
SELECT T1.key
      ,T1.value
  FROM m_env T1
 ORDER BY T1.key
__HEREDOC__;

            $pdo = $this->get_pdo();

            $list_env = [];
            foreach ($pdo->query($sql) as $row) {
                $list_env[$row['key']] = $row['value'];
            }

            error_log($log_prefix . '$list_env : ' . print_r($list_env, true));
            $pdo = null;

            apcu_store(__METHOD__, $list_env);
        }
        $value = '';
        if (array_key_exists($key_name_, $list_env)) {
            $value = $list_env[$key_name_];
            if ($is_decrypt_ === true) {
                $value = $this->get_decrypt_string($value);
            }
        }
        return $value;
    }

    public function to_small_size($target_)
    {
        $subscript = '₀₁₂₃₄₅₆₇₈₉';
        for ($i = 0; $i < 10; $i++) {
            $target_ = str_replace($i, mb_substr($subscript, $i, 1), $target_);
        }
        return $target_;
    }

    public function to_big_size($target_)
    {
        $subscript = '₀₁₂₃₄₅₆₇₈₉';
        for ($i = 0; $i < 10; $i++) {
            $target_ = str_replace(mb_substr($subscript, $i, 1), $i, $target_);
        }
        return $target_;
    }

    public function to_next_word($target_)
    {
        for ($i = 0; $i < strlen($target_); $i++) {
            $target_[$i] = chr(ord($target_[$i]) + 1);
            if ($target_[$i] == '{') {
                $target_[$i] = 'a';
            }
        }
        return $target_;
    }

    public function post_blog_wordpress_async($title_, $description_ = null)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        if (is_null($description_)) {
            $description_ = '.';
        }

        error_log($log_prefix . 'start exec');
        // exec('php -d apc.enable_cli=1 ../scripts/put_blog.php ' . base64_encode($title_) . ' ' . base64_encode($description_) . ' >/dev/null &');
        exec('php -d apc.enable_cli=1 -d include_path=.:/app/.heroku/php/lib/php:/app/lib ../scripts/put_blog.php ' .
             base64_encode($title_) . ' ' .
             base64_encode($description_) . ' >/dev/null &');
        error_log($log_prefix . 'finish exec');
    }

    public function post_blog_wordpress($title_, $description_ = null, $is_only_ = false)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        if (is_null($description_)) {
            $description_ = '.';
        }

        $username = $this->get_env('WORDPRESS_USERNAME', true);
        $password = $this->get_env('WORDPRESS_PASSWORD', true);
        $client_id = $this->get_env('WORDPRESS_CLIENT_ID', true);
        $client_secret = $this->get_env('WORDPRESS_CLIENT_SECRET', true);

        $url = 'https://public-api.wordpress.com/oauth2/token';
        $post_data = ['client_id' => $client_id,
                      'client_secret' => $client_secret,
                      'grant_type' => 'password',
                      'username' => $username,
                      'password' => $password,
                     ];

        $options = [CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query($post_data),
                   ];
        $res = $this->get_contents($url, $options);
        error_log($log_prefix . print_r(json_decode($res), true));

        $access_token = json_decode($res)->access_token;

        $url = 'https://public-api.wordpress.com/rest/v1/me/';
        $options = [CURLOPT_HTTPHEADER => ["Authorization: Bearer ${access_token}",],];
        $res = $this->get_contents($url, $options);
        error_log($log_prefix . print_r(json_decode($res), true));

        $blog_id = json_decode($res)->primary_blog;

        $url = "https://public-api.wordpress.com/rest/v1.1/sites/${blog_id}/posts/new/";
        $post_data = ['title' => date('Y/m/d H:i:s', strtotime('+9 hours')) . " ${title_}",
                      'content' => $description_,
                     ];
        $options = [CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query($post_data),
                    CURLOPT_HTTPHEADER => ["Authorization: Bearer ${access_token}",],
                   ];
        $res = $this->get_contents($url, $options);
        error_log($log_prefix . print_r(json_decode($res), true));

        $sql = <<< __HEREDOC__
INSERT INTO t_blog_post VALUES('wordpress', :b_yyyymmdd, 1)
    ON CONFLICT (blog_site, yyyymmdd)
    DO UPDATE SET post_count = ( SELECT T1.post_count + 1
                                   FROM t_blog_post T1
                                  WHERE T1.blog_site = 'wordpress'
                                    AND T1.yyyymmdd = :b_yyyymmdd
                               )
__HEREDOC__;

        $pdo = $this->get_pdo();

        $statement = $pdo->prepare($sql);
        $rc = $statement->execute([':b_yyyymmdd' => date('Ymd', strtotime('+9 hours'))]);
        error_log($log_prefix . 'UPSERT $rc : ' . $rc);

        $pdo = null;

        /*
        try {
            $url = 'https://' . $username . '.wordpress.com/xmlrpc.php';

            $file_name = '/tmp/blog_id_wordpress';
            if (file_exists($file_name)) {
                $blogid = file_get_contents($file_name);
            } else {
                error_log($log_prefix . 'url : ' . $url);
                $client = XML_RPC2_Client::create($url, ['prefix' => 'wp.']);
                error_log($log_prefix . 'xmlrpc : getUsersBlogs');
                $this->_count_web_access++;
                $result = $client->getUsersBlogs($username, $password);
                error_log($log_prefix . 'RESULT : ' . print_r($result, true));

                $blogid = $result[0]['blogid'];
                file_put_contents($file_name, $blogid);
            }

            $client = XML_RPC2_Client::create($url, ['prefix' => 'wp.', 'connectionTimeout' => 1000]); // 1sec
            error_log($log_prefix . 'xmlrpc : newPost');
            $this->_count_web_access++;
            if (is_null($description_)) {
                $description_ = '.';
            }
            $post_data = ['post_title' => date('Y/m/d H:i:s', strtotime('+9 hours')) . " ${title_}",
                          'post_content' => $description_,
                          'post_status' => 'publish',
                         ];
            $result = $client->newPost($blogid, $username, $password, $post_data);
            error_log($log_prefix . 'RESULT : ' . print_r($result, true));
        } catch (Exception $e) {
            error_log($log_prefix . 'Exception : ' . $e->getMessage());
        }
        */

        if ($is_only_ === false) {
            $this->post_blog_hatena($title_, $description_);
            $this->post_blog_livedoor($title_, $description_);
        }
    }

    public function post_blog_fc2_async($title_, $description_ = null)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        if (is_null($description_)) {
            $description_ = '.';
        }

        error_log($log_prefix . 'start exec');
        exec('php -d apc.enable_cli=1 -d include_path=.:/app/.heroku/php/lib/php:/app/lib ../scripts/put_blog_fc2.php ' .
             base64_encode($title_) . ' ' .
             base64_encode($description_) . ' >/dev/null &');
        error_log($log_prefix . 'finish exec');
    }

    public function post_blog_fc2($title_, $description_ = null)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        try {
            $url = 'https://blog.fc2.com/xmlrpc.php';
            error_log($log_prefix . 'url : ' . $url);
            $client = XML_RPC2_Client::create(
                $url,
                ['prefix' => 'metaWeblog.', 'connectionTimeout' => 2000]
            );
            error_log($log_prefix . 'xmlrpc : newPost');
            $this->_count_web_access++;
            if (is_null($description_)) {
                $description_ = '.';
            }
            $options = ['title' => date('Y/m/d H:i:s', strtotime('+9 hours')) . " ${title_}", 'description' => $description_];
            $result = $client->newPost('', $this->get_env('FC2_ID', true), $this->get_env('FC2_PASSWORD', true), $options, 1); // 1 : publish
            error_log($log_prefix . 'RESULT : ' . print_r($result, true));
        } catch (Exception $e) {
            error_log($log_prefix . 'Exception : ' . $e->getMessage());
            $this->post_blog_wordpress($title_, $description_);
        }
    }

    public function post_blog_hatena($title_, $description_ = null)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        if (is_null($description_)) {
            $description_ = '.';
        }

        $hatena_id = $this->get_env('HATENA_ID', true);
        $hatena_blog_id = $this->get_env('HATENA_BLOG_ID', true);
        $hatena_api_key = $this->get_env('HATENA_API_KEY', true);

        $xml = <<< __HEREDOC__
<?xml version="1.0" encoding="utf-8"?>
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:app="http://www.w3.org/2007/app">
  <title>__TITLE__</title>
  <content type="text/plain">__CONTENT__</content>
</entry>
__HEREDOC__;

        $xml = str_replace('__TITLE__', date('Y/m/d H:i:s', strtotime('+9 hours')) . " ${title_}", $xml);
        $xml = str_replace('__CONTENT__', htmlspecialchars(nl2br($description_)), $xml);

        $url = "https://blog.hatena.ne.jp/${hatena_id}/${hatena_blog_id}/atom/entry";

        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${hatena_id}:${hatena_api_key}",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => ['Expect:',],
        ];

        $res = $this->get_contents($url, $options);

        $sql = <<< __HEREDOC__
INSERT INTO t_blog_post VALUES('hatena', :b_yyyymmdd, 1)
    ON CONFLICT (blog_site, yyyymmdd)
    DO UPDATE SET post_count = ( SELECT T1.post_count + 1
                                   FROM t_blog_post T1
                                  WHERE T1.blog_site = 'hatena'
                                    AND T1.yyyymmdd = :b_yyyymmdd
                               )
__HEREDOC__;

        $pdo = $this->get_pdo();

        $statement = $pdo->prepare($sql);
        $rc = $statement->execute([':b_yyyymmdd' => date('Ymd', strtotime('+9 hours'))]);
        error_log($log_prefix . 'UPSERT $rc : ' . $rc);

        $pdo = null;

        error_log($log_prefix . 'RESULT : ' . $res);
    }

    function post_blog_livedoor($title_, $description_ = null)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        if (is_null($description_)) {
            $description_ = '.';
        }

        $livedoor_id = $this->get_env('LIVEDOOR_ID', true);
        $livedoor_atom_password = $this->get_env('LIVEDOOR_ATOM_PASSWORD', true);

        $xml = <<< __HEREDOC__
<?xml version="1.0" encoding="utf-8"?>
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:app="http://www.w3.org/2007/app">
  <title>__TITLE__</title>
  <content type="text/plain">__CONTENT__</content>
</entry>
__HEREDOC__;

        $xml = str_replace('__TITLE__', date('Y/m/d H:i:s', strtotime('+9 hours')) . " ${title_}", $xml);
        $xml = str_replace('__CONTENT__', htmlspecialchars(nl2br($description_)), $xml);

        $url = "https://livedoor.blogcms.jp/atompub/${livedoor_id}/article";

        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${livedoor_id}:${livedoor_atom_password}",
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/atom+xml;type=entry', 'Expect:',],
        ];

        $res = $this->get_contents($url, $options);

        error_log($log_prefix . 'RESULT : ' . $res);

        error_log($log_prefix . 'start exec');
        exec('php -d apc.enable_cli=1 -d include_path=.:/app/.heroku/php/lib/php:/app/lib ../scripts/update_ttrss.php >/dev/null &');
        error_log($log_prefix . 'finish exec');
    }

    public function upload_fc2($file_name_) {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        error_log($log_prefix . 'filesize : ' . number_format(filesize($file_name_)));
        $ftp_link_id = ftp_connect($this->get_env('FC2_FTP_SERVER', true));

        $rc = ftp_login($ftp_link_id, $this->get_env('FC2_FTP_ID', true), $this->get_env('FC2_FTP_PASSWORD', true));
        error_log($log_prefix . 'ftp_login : ' . $rc);

        $rc = ftp_pasv($ftp_link_id, true);
        error_log($log_prefix . 'ftp_pasv : ' . $rc);

        $rc = ftp_nlist($ftp_link_id, '.');
        error_log($log_prefix . print_r($rc, true));

        $rc = ftp_put($ftp_link_id, pathinfo($file_name_)['basename'], $file_name_, FTP_ASCII);
        error_log($log_prefix . 'ftp_put : ' . $rc);

        $rc = ftp_close($ftp_link_id);
        error_log($log_prefix . 'ftp_close : ' . $rc);
    }

    public function search_blog($keyword_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $wordpress_username = $this->get_env('WORDPRESS_USERNAME', true);

        $url = "https://${wordpress_username}.wordpress.com/?s=${keyword_}";
        $res = $this->get_contents($url);
        $rc = preg_match('/<h1 class="entry-title"><a href="(.+?)"/', $res, $match);
        $res = $this->get_contents($match[1]);
        $rc = preg_match('/<div class="' . $keyword_ . '">(.+?)</', $res, $match);

        error_log($log_prefix . $match[1]);

        return $match[1];
    }

    public function get_contents($url_, $options_ = null, $is_cache_search = false)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        if ($is_cache_search !== true) {
            return $this->get_contents_nocache($url_, $options_);
        }

        if (is_null($options_) == false && array_key_exists(CURLOPT_POST, $options_) === true) {
            $url_base64 = base64_encode($url_ . '?' . $options_[CURLOPT_POSTFIELDS]);
        } else {
            $url_base64 = base64_encode($url_);
        }

        $sql = <<< __HEREDOC__
SELECT T1.url_base64
      ,T1.content_compress_base64
      ,T1.update_time
      ,CASE WHEN LOCALTIMESTAMP < T1.update_time + interval '22 hours' THEN 0 ELSE 1 END refresh_flag
  FROM t_webcache T1
 WHERE T1.url_base64 = :b_url_base64;
__HEREDOC__;

        $pdo = $this->get_pdo();

        $statement = $pdo->prepare($sql);

        $statement->execute([':b_url_base64' => $url_base64]);
        $result = $statement->fetchAll();

        if (count($result) === 0 || $result[0]['refresh_flag'] == '1') {
            $res = $this->get_contents_nocache($url_, $options_);
            $content_compress_base64 = base64_encode(gzencode($res, 9));

            $sql = <<< __HEREDOC__
DELETE
  FROM t_webcache
 WHERE url_base64 = :b_url_base64
    OR LOCALTIMESTAMP > update_time + interval '5 days';
__HEREDOC__;

            if (count($result) != 0) {
                $statement = $pdo->prepare($sql);
                $rc = $statement->execute([':b_url_base64' => $url_base64]);
                error_log($log_prefix . 'DELETE $rc : ' . $rc);
            }

            $sql = <<< __HEREDOC__
INSERT INTO t_webcache
( url_base64
 ,content_compress_base64
) VALUES (
  :b_url_base64
 ,:b_content_compress_base64
);
__HEREDOC__;
            if (strlen($res) > 0) {
                $statement = $pdo->prepare($sql);
                $rc = $statement->execute([':b_url_base64' => $url_base64,
                                           ':b_content_compress_base64' => $content_compress_base64]);
                error_log($log_prefix . 'INSERT $rc : ' . $rc);
            }
        } else {
            if (is_null($options_) == false && array_key_exists(CURLOPT_POST, $options_) === true) {
                error_log($log_prefix . '(CACHE HIT) url : ' . $url_ . '?' . $options_[CURLOPT_POSTFIELDS]);
            } else {
                error_log($log_prefix . '(CACHE HIT) url : ' . $url_);
            }
            $res = gzdecode(base64_decode($result[0]['content_compress_base64']));
        }
        $pdo = null;
        return $res;
    }

    public function get_contents_nocache($url_, $options_ = null)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
        error_log($log_prefix . 'URL : ' . $url_);
        error_log($log_prefix . 'options : ' . print_r($options_, true));

        $options = [
            CURLOPT_URL => $url_,
            CURLOPT_USERAGENT => getenv('USER_AGENT'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PATH_AS_IS => true,
            CURLOPT_TCP_FASTOPEN => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
        ];

        if (is_null($options_) === false && array_key_exists(CURLOPT_USERAGENT, $options_)) {
            unset($options[CURLOPT_USERAGENT]);
        }

        $time_start = 0;
        $time_finish = 0;
        for ($i = 0; $i < 3; $i++) {
            $time_start = microtime(true);
            $ch = curl_init();
            $this->_count_web_access++;
            foreach ($options as $key => $value) {
                $rc = curl_setopt($ch, $key, $value);
                if ($rc == false) {
                    error_log($log_prefix . "curl_setopt : ${key} ${value}");
                }
            }
            if (is_null($options_) === false) {
                foreach ($options_ as $key => $value) {
                    $rc = curl_setopt($ch, $key, $value);
                    if ($rc == false) {
                        error_log($log_prefix . "curl_setopt : ${key} ${value}");
                    }
                }
            }
            $res = curl_exec($ch);
            $time_finish = microtime(true);
            $http_code = (string)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            error_log($log_prefix .
                      "HTTP STATUS CODE : ${http_code} [" .
                      substr(($time_finish - $time_start), 0, 5) . 'sec] ' .
                      parse_url($url_, PHP_URL_HOST));
            curl_close($ch);
            if (apcu_exists('HTTP_STATUS') === true) {
                $dic_http_status = apcu_fetch('HTTP_STATUS');
            } else {
                $dic_http_status = [];
            }
            if (array_key_exists($http_code, $dic_http_status) === true) {
                $dic_http_status[$http_code]++;
            } else {
                $dic_http_status[$http_code] = 1;
            }
            apcu_store('HTTP_STATUS', $dic_http_status);
            /*
            if ($http_code == '200' || $http_code == '201' || $http_code == '207' || $http_code == '303') {
                break;
            }
            */
            switch ($http_code) {
                case '200':
                case '201':
                case '207':
                case '302':
                case '303':
                    break 2;
            }

            error_log($log_prefix . '$res : ' . $res);
            $res = $http_code;

            if ($http_code == '429' || $http_code == '502' || $http_code == '503') {
                // 429 quickchart
                // 502 my.cl (CloudApp)
                // 503 feed43
                sleep(3);
                error_log($log_prefix . 'RETRY URL : ' . $url_);
            } else {
                break;
            }
        }

        error_log($log_prefix . 'LENGTH : ' . number_format(strlen($res)));
        return $res;
    }

    public function get_contents_multi($urls_, $urls_is_cache_ = null, $multi_options_ = null)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $time_start = microtime(true);

        if (is_null($urls_)) {
            $urls_ = [];
        }
        if (is_null($urls_is_cache_)) {
            $urls_is_cache_ = [];
        }

        $sql_select = <<< __HEREDOC__
SELECT T1.url_base64
      ,T1.content_compress_base64
  FROM t_webcache T1
 WHERE CASE WHEN LOCALTIMESTAMP < T1.update_time + interval '1 days' THEN 0 ELSE 1 END = 0
__HEREDOC__;

        $pdo = $this->get_pdo();
        $statement = $pdo->prepare($sql_select);
        $statement->execute();
        $results = $statement->fetchAll();

        $cache_data = [];
        foreach ($results as $result) {
            $cache_data[$result['url_base64']] = $result['content_compress_base64'];
        }

        $results_cache = [];

        foreach ($urls_is_cache_ as $url => $options) {
            if (array_key_exists(base64_encode($url), $cache_data)) {
                error_log($log_prefix . '(CACHE HIT) $url : ' . $url);
                $results_cache[$url] = gzdecode(base64_decode($cache_data[base64_encode($url)]));
            } else {
                $urls_[$url] = $options;
            }
        }

        $mh = curl_multi_init();
        if (is_null($multi_options_) === false) {
            foreach ($multi_options_ as $key => $value) {
                $rc = curl_multi_setopt($mh, $key, $value);
                if ($rc === false) {
                    error_log($log_prefix . "curl_multi_setopt : ${key} ${value}");
                }
            }
        }

        foreach ($urls_ as $url => $options_add) {
            error_log($log_prefix . 'CURL MULTI Add $url : ' . $url);
            $ch = curl_init();
            $this->_count_web_access++;
            $options = [CURLOPT_URL => $url,
                        CURLOPT_USERAGENT => getenv('USER_AGENT'),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS => 3,
                        CURLOPT_PATH_AS_IS => true,
                        CURLOPT_TCP_FASTOPEN => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            ];

            if (is_null($options_add) === false && array_key_exists(CURLOPT_USERAGENT, $options_add)) {
                unset($options[CURLOPT_USERAGENT]);
            }
            foreach ($options as $key => $value) {
                $rc = curl_setopt($ch, $key, $value);
                if ($rc == false) {
                    error_log($log_prefix . "curl_setopt : ${key} ${value}");
                }
            }
            if (is_null($options_add) === false) {
                foreach ($options_add as $key => $value) {
                    $rc = curl_setopt($ch, $key, $value);
                    if ($rc == false) {
                        error_log($log_prefix . "curl_setopt : ${key} ${value}");
                    }
                }
            }
            curl_multi_add_handle($mh, $ch);
            $list_ch[$url] = $ch;
        }

        $active = null;
        $rc = curl_multi_exec($mh, $active);

        $count = 0;
        while ($active && $rc == CURLM_OK) {
            $count++;
            if (curl_multi_select($mh) == -1) {
                usleep(1);
            }
            $rc = curl_multi_exec($mh, $active);
        }
        error_log($log_prefix . 'loop count : ' . $count);

        $results = [];
        foreach (array_keys($urls_) as $url) {
            $ch = $list_ch[$url];
            $res = curl_getinfo($ch);
            $http_code = (string)$res['http_code'];
            error_log($log_prefix . "CURL Result ${http_code} : ${url}");
            if ($http_code[0] == '2') {
                $result = curl_multi_getcontent($ch);
                $results[$url] = $result;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if (apcu_exists('HTTP_STATUS') === true) {
                $dic_http_status = apcu_fetch('HTTP_STATUS');
            } else {
                $dic_http_status = [];
            }
            if (array_key_exists($http_code, $dic_http_status) === true) {
                $dic_http_status[$http_code]++;
            } else {
                $dic_http_status[$http_code] = 1;
            }
            apcu_store('HTTP_STATUS', $dic_http_status);
        }

        curl_multi_close($mh);

        $sql_delete = <<< __HEREDOC__
DELETE
  FROM t_webcache
 WHERE url_base64 = :b_url_base64
    OR LOCALTIMESTAMP > update_time + interval '5 days';
__HEREDOC__;

        $sql_insert = <<< __HEREDOC__
INSERT INTO t_webcache
( url_base64
 ,content_compress_base64
) VALUES (
  :b_url_base64
 ,:b_content_compress_base64
);
__HEREDOC__;

        foreach ($results as $url => $result) {
            if (array_key_exists($url, $urls_is_cache_) === false) {
                continue;
            }

            // delete & insert

            $url_base64 = base64_encode($url);
            $statement = $pdo->prepare($sql_delete);
            $rc = $statement->execute([':b_url_base64' => $url_base64]);
            error_log($log_prefix . 'DELETE $rc : ' . $rc);

            $statement = $pdo->prepare($sql_insert);
            $rc = $statement->execute([':b_url_base64' => $url_base64,
                                       ':b_content_compress_base64' => base64_encode(gzencode($result, 9))]);
            error_log($log_prefix . 'INSERT $rc : ' . $rc);
        }

        $pdo = null;

        $results = array_merge($results, $results_cache);

        $total_time = substr((microtime(true) - $time_start), 0, 5) . 'sec';

        error_log($log_prefix . 'urls : ' . print_r(array_keys($results), true));
        error_log("${log_prefix}Total Time : [${total_time}]");
        error_log($log_prefix . 'memory_get_usage : ' . number_format(memory_get_usage()) . 'byte');

        return $results;
    }

    public function backup_data($data_, $file_name_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $base_name = pathinfo($file_name_)['basename'];

        $user_hidrive = $this->get_env('HIDRIVE_USER', true);
        $password_hidrive = $this->get_env('HIDRIVE_PASSWORD', true);

        $user_pcloud = $this->get_env('PCLOUD_USER', true);
        $password_pcloud = $this->get_env('PCLOUD_PASSWORD', true);

        $user_teracloud = $this->get_env('TERACLOUD_USER', true);
        $password_teracloud = $this->get_env('TERACLOUD_PASSWORD', true);
        $api_key_teracloud = $this->get_env('TERACLOUD_API_KEY', true);
        $node_teracloud = $this->get_env('TERACLOUD_NODE', true);

        $user_opendrive = $this->get_env('OPENDRIVE_USER', true);
        $password_opendrive = $this->get_env('OPENDRIVE_PASSWORD', true);

        $user_cloudme = $this->get_env('CLOUDME_USER', true);
        $password_cloudme = $this->get_env('CLOUDME_PASSWORD', true);

        $user_4shared = $this->get_env('4SHARED_USER', true);
        $password_4shared = $this->get_env('4SHARED_PASSWORD', true);

        /*
        $user_cloudapp = $this->get_env('CLOUDAPP_USER', true);
        $password_cloudapp = $this->get_env('CLOUDAPP_PASSWORD', true);
        */

        $authtoken_zoho = $this->get_env('ZOHO_AUTHTOKEN', true);

        $res = bzcompress($data_, 9);
        $method = 'aes-256-cbc';
        $password = base64_encode($user_hidrive) . base64_encode($password_hidrive);
        $iv = substr(sha1($file_name_), 0, openssl_cipher_iv_length($method));
        $res = openssl_encrypt($res, $method, $password, OPENSSL_RAW_DATA, $iv);

        $res = base64_encode($res);
        error_log($log_prefix . $base_name . ' size : ' . number_format(strlen($res)));
        file_put_contents($file_name_, $res);

        $urls = [];

        // CloudApp

        /*
        $url_target = '';
        $page = 0;
        for (;;) {
            $page++;
            $url = 'http://my.cl.ly/items?per_page=100&page=' . $page;

            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
                CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
                CURLOPT_HTTPHEADER => ['Accept: application/json',],
            ];

            $res = $this->get_contents($url, $options, true);
            $json = json_decode($res);
            if (count($json) === 0) {
                break;
            }
            foreach ($json as $item) {
                if ($item->file_name == $base_name) {
                    $url_target = $item->href;
                    break 2;
                }
            }
        }

        if ($url_target != '') {
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
                CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
                CURLOPT_HTTPHEADER => ['Accept: application/json',],
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HEADER => true,
            ];
            $urls[$url_target] = $options;
        }
        */

        // Zoho

        $url = "https://apidocs.zoho.com/files/v1/files?authtoken=${authtoken_zoho}&scope=docsapi";
        $res = $this->get_contents($url);

        foreach (json_decode($res)->FILES as $item) {
            if ($item->DOCNAME == $base_name) {
                $url = "https://apidocs.zoho.com/files/v1/delete?authtoken=${authtoken_zoho}&scope=docsapi";
                $post_data = ['docid' => $item->DOCID,];
                $options = [CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => http_build_query($post_data),
                            CURLOPT_HEADER => true,
                           ];
                $urls[$url] = $options;
                break;
            }
        }

        // HiDrive

        $url = "https://webdav.hidrive.strato.com/users/${user_hidrive}/${base_name}";
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HEADER => true,
        ];
        $urls[$url] = $options;

        $url = 'https://webdav.pcloud.com/' . $base_name;
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${user_pcloud}:${password_pcloud}",
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HEADER => true,
        ];
        $urls[$url] = $options;

        $url = "https://${node_teracloud}.teracloud.jp/dav/${base_name}";
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${user_teracloud}:${password_teracloud}",
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HEADER => true,
        ];
        $urls[$url] = $options;

        $url = 'https://webdav.opendrive.com/' . $base_name;
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${user_opendrive}:${password_opendrive}",
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HEADER => true,
        ];
        $urls[$url] = $options;

        $url = "https://webdav.cloudme.com/${user_cloudme}/xios/${base_name}";
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => "${user_cloudme}:${password_cloudme}",
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HEADER => true,
        ];
        $urls[$url] = $options;

        $url = 'https://webdav.4shared.com/' . $base_name;
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "${user_4shared}:${password_4shared}",
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HEADER => true,
        ];
        $urls[$url] = $options;

        $res = $this->get_contents_multi($urls);
        error_log($log_prefix . 'memory_get_usage : ' . number_format(memory_get_usage()) . 'byte');
        error_log($log_prefix . print_r($res, true));
        $res = null;

        $urls = [];
        $file_size = filesize($file_name_);
        if ($file_size > (1024 ** 2 * 2)) {
            $fh = fopen($file_name_, 'rb');

            $url = "https://webdav.hidrive.strato.com/users/${user_hidrive}/${base_name}";
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => $fh,
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $res = $this->get_contents($url, $options);

            $url = 'https://webdav.pcloud.com/' . $base_name;
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_pcloud}:${password_pcloud}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => $fh,
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $res = $this->get_contents($url, $options);

            $url = "https://${node_teracloud}.teracloud.jp/dav/${base_name}";
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_teracloud}:${password_teracloud}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => $fh,
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $res = $this->get_contents($url, $options);

            /*
            $url = 'https://webdav.opendrive.com/' . $base_name;
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_opendrive}:${password_opendrive}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => $fh,
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $res = $this->get_contents($url, $options);
            */

            $url = "https://webdav.cloudme.com/${user_cloudme}/xios/${base_name}";
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
                CURLOPT_USERPWD => "${user_cloudme}:${password_cloudme}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => $fh,
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $res = $this->get_contents($url, $options);

            $url = 'https://webdav.4shared.com/' . $base_name;
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_4shared}:${password_4shared}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => $fh,
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $res = $this->get_contents($url, $options);

            fclose($fh);

            $url = "https://apidocs.zoho.com/files/v1/upload?authtoken=${authtoken_zoho}&scope=docsapi";
            $post_data = ['filename' => $base_name,
                          'content' => new CURLFile($file_name_, 'text/plain'),
                         ];
            $options = [CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $post_data,
                        CURLOPT_HEADER => true,
                       ];
            $res = $this->get_contents($url, $options);
        } else {
            $list_fh = [];
            $list_file_name = [];

            $list_file_name[] = tempnam("/tmp", 'backup_' .  md5(microtime(true)));
            copy($file_name_, end($list_file_name));
            $list_fh[] = fopen(end($list_file_name), 'rb');

            $url = "https://webdav.hidrive.strato.com/users/${user_hidrive}/${base_name}";
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_hidrive}:${password_hidrive}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => end($list_fh),
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $urls[$url] = $options;

            $list_file_name[] = tempnam("/tmp", 'backup_' .  md5(microtime(true)));
            copy($file_name_, end($list_file_name));
            $list_fh[] = fopen(end($list_file_name), 'rb');

            $url = 'https://webdav.pcloud.com/' . $base_name;
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_pcloud}:${password_pcloud}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => end($list_fh),
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $urls[$url] = $options;

            $list_file_name[] = tempnam("/tmp", 'backup_' .  md5(microtime(true)));
            copy($file_name_, end($list_file_name));
            $list_fh[] = fopen(end($list_file_name), 'rb');

            $url = "https://${node_teracloud}.teracloud.jp/dav/${base_name}";
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_teracloud}:${password_teracloud}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => end($list_fh),
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $urls[$url] = $options;

            /*
            $list_file_name[] = tempnam("/tmp", 'backup_' .  md5(microtime(true)));
            copy($file_name_, end($list_file_name));
            $list_fh[] = fopen(end($list_file_name), 'rb');

            $url = 'https://webdav.opendrive.com/' . $base_name;
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_opendrive}:${password_opendrive}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => end($list_fh),
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $urls[$url] = $options;
            */

            $list_file_name[] = tempnam("/tmp", 'backup_' .  md5(microtime(true)));
            copy($file_name_, end($list_file_name));
            $list_fh[] = fopen(end($list_file_name), 'rb');

            $url = "https://webdav.cloudme.com/${user_cloudme}/xios/${base_name}";
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
                CURLOPT_USERPWD => "${user_cloudme}:${password_cloudme}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => end($list_fh),
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $urls[$url] = $options;

            $list_file_name[] = tempnam("/tmp", 'backup_' .  md5(microtime(true)));
            copy($file_name_, end($list_file_name));
            $list_fh[] = fopen(end($list_file_name), 'rb');

            $url = 'https://webdav.4shared.com/' . $base_name;
            $options = [
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => "${user_4shared}:${password_4shared}",
                CURLOPT_PUT => true,
                CURLOPT_INFILE => end($list_fh),
                CURLOPT_INFILESIZE => $file_size,
                CURLOPT_HEADER => true,
            ];
            $urls[$url] = $options;

            $list_file_name[] = tempnam("/tmp", 'backup_' .  md5(microtime(true)));
            copy($file_name_, end($list_file_name));

            $url = "https://apidocs.zoho.com/files/v1/upload?authtoken=${authtoken_zoho}&scope=docsapi";
            $post_data = ['filename' => $base_name,
                          'content' => new CURLFile(end($list_file_name), 'text/plain'),
                         ];
            $options = [CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $post_data,
                        CURLOPT_HEADER => true,
                       ];
            $urls[$url] = $options;

            $res = $this->get_contents_multi($urls);
            error_log($log_prefix . 'memory_get_usage : ' . number_format(memory_get_usage()) . 'byte');
            error_log($log_prefix . print_r($res, true));
            $res = null;
            foreach ($list_fh as $fh) {
                fclose($fh);
            }
            foreach ($list_file_name as $file_name) {
                unlink($file_name);
            }
        }

        // CloudApp

        /*
        $url = 'http://my.cl.ly/items/new';
        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
            CURLOPT_HTTPHEADER => ['Accept: application/json',],
        ];
        $res = $this->get_contents($url, $options);
        $json = json_decode($res);

        $post_data = [
            'AWSAccessKeyId' => $json->params->AWSAccessKeyId,
            'key' => $json->params->key,
            'policy' => $json->params->policy,
            'signature' => $json->params->signature,
            'success_action_redirect' => $json->params->success_action_redirect,
            'acl' => $json->params->acl,
            'file' => new CURLFile($file_name_, 'text/plain', $base_name),
        ];
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        $res = $this->get_contents($json->url, $options);
        $rc = preg_match('/Location: (.+)/i', $res, $match);

        $options = [
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => "${user_cloudapp}:${password_cloudapp}",
            CURLOPT_HTTPHEADER => ['Accept: application/json',],
            CURLOPT_HEADER => true,
        ];
        $res = $this->get_contents(trim($match[1]), $options);

        unlink($file_name_);
        */

        /*
        error_log($log_prefix . 'start exec');
        exec('php -d apc.enable_cli=1 -d include_path=.:/app/.heroku/php/lib/php:/app/lib ../scripts/backup_cloudapp.php ' .
             $file_name_ . ' >/dev/null &');
        error_log($log_prefix . 'finish exec');
        */
        unlink($file_name_);

        return $file_size;
    }

    public function get_contents_proxy($url_)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        error_log($log_prefix . $url_);
        $cookie = tempnam('/tmp', 'cookie_' . md5(microtime(true)));

        $post_data = ['form[url]' => $url_,
                      'form[dataCenter]' => 'random',
                      'terms-agreed' => '1',
                     ];

        $options = [CURLOPT_ENCODING => 'gzip, deflate, br',
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
                    CURLOPT_HEADER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query($post_data),
                   ];

        $res = $this->get_contents(getenv('WEB_PROXY'), $options);
        unlink($cookie);
        return $res;
    }

    public function get_contents_proxy_multi($urls_, $multi_options_ = null)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $time_start = microtime(true);

        $mh = curl_multi_init();
        if (is_null($multi_options_) === false) {
            foreach ($multi_options_ as $key => $value) {
                $rc = curl_multi_setopt($mh, $key, $value);
                if ($rc === false) {
                    error_log($log_prefix . "curl_multi_setopt : ${key} ${value}");
                }
            }
        }

        $cookie = [];
        foreach ($urls_ as $url) {
            error_log($log_prefix . 'CURL MULTI Add $url : ' . $url);
            $cookie[] = tempnam('/tmp', 'cookie_' . md5(microtime(true)));

            $post_data = ['form[url]' => $url,
                          'form[dataCenter]' => 'random',
                          'terms-agreed' => '1',
                         ];

            $ch = curl_init();
            $this->_count_web_access++;
            $options = [CURLOPT_URL => getenv('WEB_PROXY'),
                        CURLOPT_USERAGENT => getenv('USER_AGENT'),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS => 3,
                        CURLOPT_PATH_AS_IS => true,
                        CURLOPT_TCP_FASTOPEN => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
                        CURLOPT_COOKIEJAR => end($cookie),
                        CURLOPT_COOKIEFILE => end($cookie),
                        CURLOPT_ENCODING => 'gzip, deflate, br',
                        CURLOPT_HTTPHEADER => [
                            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                            'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                            'Cache-Control: no-cache',
                            'Connection: keep-alive',
                            'DNT: 1',
                            'Upgrade-Insecure-Requests: 1',
                            ],
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query($post_data),
            ];

            foreach ($options as $key => $value) {
                $rc = curl_setopt($ch, $key, $value);
                if ($rc == false) {
                    error_log($log_prefix . "curl_setopt : ${key} ${value}");
                }
            }
            curl_multi_add_handle($mh, $ch);
            $list_ch[$url] = $ch;
        }

        $active = null;
        $rc = curl_multi_exec($mh, $active);

        $count = 0;
        while ($active && $rc == CURLM_OK) {
            $count++;
            if (curl_multi_select($mh) == -1) {
                usleep(1);
            }
            $rc = curl_multi_exec($mh, $active);
        }
        error_log($log_prefix . 'loop count : ' . $count);

        $results = [];
        foreach ($urls_ as $url) {
            $ch = $list_ch[$url];
            $res = curl_getinfo($ch);
            $http_code = (string)$res['http_code'];
            error_log($log_prefix . "CURL Result ${http_code} : ${url}");
            if ($http_code[0] == '2') {
                $result = curl_multi_getcontent($ch);
                $results[$url] = $result;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if (apcu_exists('HTTP_STATUS') === true) {
                $dic_http_status = apcu_fetch('HTTP_STATUS');
            } else {
                $dic_http_status = [];
            }
            if (array_key_exists($http_code, $dic_http_status) === true) {
                $dic_http_status[$http_code]++;
            } else {
                $dic_http_status[$http_code] = 1;
            }
            apcu_store('HTTP_STATUS', $dic_http_status);
        }

        curl_multi_close($mh);

        foreach ($cookie as $file) {
            unlink($file);
        }
        $total_time = substr((microtime(true) - $time_start), 0, 5) . 'sec';

        error_log($log_prefix . 'urls : ' . print_r(array_keys($results), true));
        error_log("${log_prefix}Total Time : [${total_time}]");
        error_log($log_prefix . 'memory_get_usage : ' . number_format(memory_get_usage()) . 'byte');

        return $results;
    }

    public function shrink_image($file_, $is_put_blog_ = false)
    {
        $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';

        $url = 'https://api.tinify.com/shrink';
        $options = [CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                    CURLOPT_USERPWD => 'api:' . getenv('TINYPNG_API_KEY'),
                    CURLOPT_POST => true,
                    CURLOPT_BINARYTRANSFER => true,
                    CURLOPT_POSTFIELDS => file_get_contents($file_),
                    CURLOPT_HEADER => true,
                    CURLOPT_TIMEOUT => 5,
                   ];
        for ($i = 0; $i < 3; $i++) {
            $res = $this->get_contents($url, $options);
            if (strlen($res) > 0) {
                break;
            }
        }

        if (strlen($res) < 4) {
            return file_get_contents($file_);
        }

        $tmp = preg_split('/^\r\n/m', $res, 2);

        $json = json_decode($tmp[1]);
        error_log($log_prefix . print_r($json, true));

        $rc = preg_match('/compression-count: (.+)/i', $tmp[0], $match);

        $compression_count = $match[1];
        error_log($log_prefix . 'Compression count : ' . $compression_count); // Limits 500/month
        if ($is_put_blog_) {
            $this->post_blog_wordpress('api.tinify.com', 'Compression count : ' . $compression_count . "\r\n" . 'Limits 500/month');
        }

        $options = [CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                    CURLOPT_USERPWD => 'api:' . getenv('TINYPNG_API_KEY'),
                   ];
        $res = $this->get_contents($json->output->url, $options);

        if (strlen($res) < 4) {
            $res = file_get_contents($file_);
        }

        return $res;
    }

}
