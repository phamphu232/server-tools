<?php
require_once __DIR__ . '/../../bootstrap/bootstrap.php';

require_once __DIR__ . '/../../helpers/App.php';
require_once __DIR__ . '/../../helpers/Config.php';
require_once __DIR__ . '/../../helpers/GoogleChat.php';

use helpers\App;
use helpers\Config;
use helpers\GoogleChat;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Headers: *');
  header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
  header("Access-Control-Allow-Private-Network: true");
  header('Content-Type: text/plain');
  die();
}
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');


$config = Config::get();

function convertKBtoGB($kilobytes)
{
  $gigabytes = $kilobytes / (1024 * 1024);
  return number_format($gigabytes, 2);
}

try {
  $clientIp = App::getClientIp();
  $allowedIp = explode(',', $config['app_server']['allowed_ip']);

  if (php_sapi_name() !== 'cli' && !in_array($clientIp, $allowedIp)) {
    $rt = App::formatFailure("Your ip: {$clientIp} is not allowed");
    echo json_encode($rt);
    exit();
  }

  $arrServerConfig = [];
  if (file_exists($config['BASE_DIR'] . '/cache/config/servers.json')) {
    $arrServerConfig = json_decode(file_get_contents($config['BASE_DIR'] . '/cache/config/servers.json'), true);
  }

  $directory = "{$config['BASE_DIR']}/cache/servers/";
  $jsonFiles = glob($directory . '*.json');

  $rs = [];
  foreach ($jsonFiles as $i => $file) {
    $jsonData = file_get_contents($file);
    if (empty($jsonData)) {
      continue;
    }

    $fileName = pathinfo($file, PATHINFO_FILENAME);

    $jsonData = json_decode($jsonData, true);

    if (empty($jsonData[$fileName])) {
      continue;
    }

    $jsonData = $jsonData[$fileName];

    if (!empty($arrServerConfig[$fileName])) {
      $jsonData = array_merge($jsonData, $arrServerConfig[$fileName]);
    } else {
      $jsonData['CPU_THROTTLE'] = 0;
      $jsonData['RAM_THROTTLE'] = 0;
      $jsonData['DISK_THROTTLE'] = 0;
    }

    $cloudLink = "";
    if (strtolower($jsonData['PLATFORM']) == 'aws') {
      $cloudLink = "https://{$jsonData['ZONE_CODE']}.console.aws.amazon.com/ec2/home?region={$jsonData['ZONE_CODE']}#InstanceDetails:instanceId={$jsonData['INSTANCE_ID']}";
    } else if (strtolower($jsonData['PLATFORM']) == 'gcp') {
      $cloudLink = "https://console.cloud.google.com/compute/instancesDetail/zones/{$jsonData['ZONE_CODE']}/instances/{$jsonData['INSTANCE_NAME']}?project={$jsonData['PROJECT_ID']}&authuser=1";
    }


    $jsonData['KEY'] = $fileName;
    $jsonData['CLOUD_LINK'] = $cloudLink;

    unset($jsonData['INPUT_RAW']);
    unset($jsonData['INPUT_HISTORY']);
    unset($jsonData['VERIFY_CODE']);
    $rs[] = $jsonData;
  }

  $rt = App::formatSuccess('OK', ['Data' => $rs]);
  echo json_encode($rt);
} catch (\Exception $e) {
  $mentor = $config['google_chat']['mentor_system_user'];
  $mentor = empty($mentor) ? '' : $mentor;
  $message = "{$mentor}\n```ERROR: [Line: {$e->getLine()}] [File: {$e->getFile()}] {$e->getMessage()}```";
  echo $message;
  GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}
