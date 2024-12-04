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

// $databaseFile = __DIR__ . '/../../database.sqlite';

$config = Config::get();

$mentor = $config['google_chat']['mentor_system_user'];
$mentor = empty($mentor) ? '' : $mentor;

$clientIp = App::getClientIp();
$allowedIp = explode(',', $config['app_server']['allowed_ip']);

try {
    if (php_sapi_name() !== 'cli' && !in_array($clientIp, $allowedIp)) {
        echo "Your ip: {$clientIp} is not allowed";
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo 'Method not allowed';
        exit();
    }

    $inputRaw = file_get_contents("php://input");
    $requestContent = json_decode($inputRaw, true);

    $verifyCode = md5("VERIFY_CODE:{$requestContent['TIMESTAMP']}_{$requestContent['PLATFORM']}_{$requestContent['PUBLIC_IP']}");
    if ($verifyCode != $requestContent['VERIFY_CODE']) {
        throw new Exception("Verify code error, verifyCode check: {$verifyCode}, requestVerifyCode: {$requestContent['VERIFY_CODE']}");
    }

    $cpuParts = [];
    preg_match_all('/(\d+\.\d+)(\W|%)([a-zA-Z]+)/', $requestContent['CPU'], $cpuParts);
    $cpuDetails = array_combine($cpuParts[3], $cpuParts[1]);
    $cpuDetails['usage_percent'] = ceil($cpuDetails['us'] + $cpuDetails['sy']);

    $ramParts = [];
    preg_match_all('/\W\d+/', $requestContent['RAM'], $ramParts);
    $ramDetails = [
        'total' => trim($ramParts[0][0]),
        'used' => trim($ramParts[0][1]),
        'free' => trim($ramParts[0][2]),
        'shared' => trim($ramParts[0][3]),
        'buff/cache' => trim($ramParts[0][4]),
        'available' => trim($ramParts[0][5]),
    ];
    $ramDetails['usage_percent'] = ceil(($ramDetails['used'] / $ramDetails['total']) * 100);

    $diskParts = [];
    preg_match_all('/\W\d+/', $requestContent['DISK'], $diskParts);
    $diskDetails = [
        'total' => trim($diskParts[0][0]),
        'used' => trim($diskParts[0][1]),
        'free' => trim($diskParts[0][2]),
        'usage_percent' => ceil($diskParts[0][3]),
    ];

    $data = [
        "PLATFORM" => $requestContent['PLATFORM'],
        "ZONE_CODE" => $requestContent['ZONE_CODE'],
        "INSTANCE_NAME" => $requestContent['INSTANCE_NAME'],
        "PROJECT_ID" => $requestContent['PROJECT_ID'],
        "INSTANCE_ID" => $requestContent['INSTANCE_ID'],
        "PUBLIC_IP" => $requestContent['PUBLIC_IP'],
        "USERNAME" => $requestContent['USERNAME'],
        'CPU' => $cpuDetails,
        'RAM' => $ramDetails,
        'DISK' => $diskDetails,
        "TIMESTAMP" => $requestContent['TIMESTAMP'],
        "VERIFY_CODE" => $verifyCode,
    ];

    $keyCache = "{$requestContent['PLATFORM']}_{$requestContent['PUBLIC_IP']}";
    $cacheObj = new Cache("servers/{$keyCache}.json");

    $dataCache = $cacheObj->get($keyCache);

    $inputHistory = [];
    if ($dataCache) {
        $inputHistory = $dataCache['INPUT_HISTORY'];
        // Add the new input to the history
        array_unshift($inputHistory, $data);
        // Only keep the last 5 inputs
        $inputHistory = array_slice($inputHistory, 0, 5);
    }

    $data['CPU_TOP'] = $requestContent['CPU_TOP'];
    $data['RAM_TOP'] = $requestContent['RAM_TOP'];
    $data['INPUT_HISTORY'] = $inputHistory;

    $cacheObj->set($keyCache, $data);

    $cacheConfigObj = new Cache("config/servers.json");
    if (empty($cacheConfigObj->get($keyCache))) {
        $cacheConfigObj->set($keyCache, [
            // 'CPU_THROTTLE' => 96,
            // 'RAM_THROTTLE' => 98,
            // 'DISK_THROTTLE' => 96,
            'CPU_THROTTLE' => 100,
            'RAM_THROTTLE' => 100,
            'DISK_THROTTLE' => 100,
        ]);
    }

    echo $inputRaw;
} catch (\Exception $e) {
    $message = "{$mentor}\n```ERROR: [File: {$e->getFile()}] [Line: {$e->getLine()}] {$e->getMessage()} \n{$inputRaw}```";
    echo $message;
    GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}
