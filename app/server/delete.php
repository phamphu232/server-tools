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

  if (!isset($input['key'])) {
    echo 'Key not found';
    exit();
  }

  $keyCache = $input['key'];
  $cacheObj = new Cache("servers/{$keyCache}.json");

  $deleteData = $cacheObj->get($keyCache);
  if (!$deleteData) {
    echo 'Key not found';
    exit();
  }

  $cacheObj->set("{$keyCache}_deleted", $deleteData);
  $cacheObj->set("{$keyCache}", []);

  echo 'OK';
  exit();
} catch (\Exception $e) {
  $mentor = $config['google_chat']['mentor_system_user'];
  $mentor = empty($mentor) ? '' : $mentor;
  $message = "{$mentor}\n```ERROR: [Line: {$e->getLine()}] {$e->getMessage()}```";
  echo $message;
  GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}
