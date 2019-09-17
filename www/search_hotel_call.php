include(dirname(__FILE__) . '/../classes/MyUtils.php');

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);

error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s'));

$mu = new MyUtils();

$url = 'https://' . getenv('HEROKU_APP_NAME') . '.herokuapp.com/search_hotel.php';
exec('curl -m 3 -u ' . getenv('BASIC_USER') . ':' . getenv('BASIC_PASSWORD') . " ${url} > /dev/null 2>&1 &");
//-H "User-Agent: hoge"
error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');
