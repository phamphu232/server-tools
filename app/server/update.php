<?php
require_once __DIR__ . '/../../bootstrap/bootstrap.php';

require_once __DIR__ . '/../../helpers/App.php';
require_once __DIR__ . '/../../helpers/Cache.php';
require_once __DIR__ . '/../../helpers/Config.php';
require_once __DIR__ . '/../../helpers/GoogleChat.php';

use helpers\App;
use helpers\Cache;
use helpers\Config;
use helpers\GoogleChat;

$config = Config::get();


try {
  $clientIp = App::getClientIp();
  $allowedIp = explode(',', $config['app_server']['allowed_ip']);

  if (php_sapi_name() !== 'cli' && !in_array($clientIp, $allowedIp)) {
    echo "Your ip: {$clientIp} is not allowed";
    exit();
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Method not allowed';
    exit();
  }

  $input = file_get_contents("php://input");
  $input = json_decode($input, true);

  $cacheServerDir = "{$config['BASE_DIR']}/cache/servers/";
  $cacheObj = new Cache("config/servers.json");

  $data = [
    'PERSON_IN_CHARGE' => trim($input['person_in_charge']),
    'SERVER_NAME' => trim($input['server_name']),
    'CPU_THROTTLE' => number_format(floatval("0{$input['cpu_throttle']}"), 0),
    'RAM_THROTTLE' => number_format(floatval("0{$input['ram_throttle']}"), 0),
    'DISK_THROTTLE' => number_format(floatval("0{$input['disk_throttle']}"), 0),
  ];

  $arrServerConfig = [];
  if (file_exists($config['BASE_DIR'] . '/cache/config/servers.json')) {
    $arrServerConfig = json_decode(file_get_contents($config['BASE_DIR'] . '/cache/config/servers.json'), true);
  }

  if (!empty($arrServerConfig[$input['key']])) {
    $data = array_merge($arrServerConfig[$input['key']], $data);
  }

  $cacheObj->set($input['key'], $data);

  echo json_encode($data);
} catch (\Exception $e) {
  $mentor = $config['google_chat']['mentor_system_user'];
  $mentor = empty($mentor) ? '' : $mentor;
  $message = "{$mentor}\n```ERROR: [Line: {$e->getLine()}] {$e->getMessage()}```";
  echo $message;
  GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}
