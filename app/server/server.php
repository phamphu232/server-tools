<?php

require_once __DIR__ . '/../../helpers/Config.php';
require_once __DIR__ . '/../../helpers/Utils.php';
require_once __DIR__ . '/../../helpers/GoogleChat.php';

use helpers\Config;
use helpers\GoogleChat;
use helpers\Utils;

$databaseFile = __DIR__ . '/../../database.sqlite';

$config = Config::get();

$mentor = $config['google_chat']['mentor_system_user'];
$mentor = empty($mentor) ? '' : $mentor;

$clientIp = Utils::getClientIp();
$allowedIp = explode(',', $config['app_server']['allowed_ip']);

try {
    if (!in_array($clientIp, $allowedIp)) {
        echo 'IP is not allowed';
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo 'Method not allowed';
        exit();
    }

    $inputRaw = file_get_contents("php://input");
    $requestContent = json_decode($inputRaw, true);

    $verifyCode = md5("VERIFY_CODE:{$requestContent['TIMESTAMP']}_{$requestContent['INSTANCE_ID']}_{$requestContent['PUBLIC_IP']}");
    if ($verifyCode != $requestContent['VERIFY_CODE']) {
        throw new Exception('Verify code error');
    }

    $cpuParts = [];
    preg_match_all('/(\d+\.\d+)%([a-z]+)/', $requestContent['CPU'], $cpuParts);
    $cpuDetails = array_combine($cpuParts[2], $cpuParts[1]);
    $cpuDetails['usage_percent'] = ceil($cpuDetails['us'] + $cpuDetails['sy']);

    $ramParts = [];
    preg_match_all('/\W\d+/', $requestContent['RAM'], $ramParts);
    $ramDetails = [
        'total' => $ramParts[0][0],
        'used' => $ramParts[0][1],
        'free' => $ramParts[0][2],
        'shared' => $ramParts[0][3],
        'buff/cache' => $ramParts[0][4],
        'available' => $ramParts[0][5],
    ];
    $ramDetails['usage_percent'] = ceil(($ramDetails['used'] / $ramDetails['total']) * 100);

    $diskParts = [];
    preg_match_all('/\W\d+/', $requestContent['DISK'], $diskParts);
    $diskDetails = [
        'total' => $diskParts[0][0],
        'used' => $diskParts[0][1],
        'free' => $diskParts[0][2],
        'usage_percent' => ceil($diskParts[0][3]),
    ];

    $input = [
        "PLATFORM" => $requestContent['PLATFORM'],
        "INSTANCE_ID" => $requestContent['INSTANCE_ID'],
        "PUBLIC_IP" => $requestContent['PUBLIC_IP'],
        "USERNAME" => $requestContent['USERNAME'],
        'CPU' => $cpuDetails,
        'RAM' => $ramDetails,
        'DISK' => $diskDetails,
        "TIMESTAMP" => $requestContent['TIMESTAMP'],
        "VERIFY_CODE" => $verifyCode,
    ];

    if (!file_exists($databaseFile)) {
        file_put_contents($databaseFile, '');
        $db = new PDO('sqlite:' . $databaseFile);
        $tableSql = "
            CREATE TABLE servers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_name TEXT(64),
                instance_id TEXT(64),
                public_ip TEXT(64),
                platform TEXT(16),
                username TEXT(16),
                is_production INTEGER DEFAULT (1),
                cpu INTEGER DEFAULT (0),
                ram INTEGER DEFAULT (0),
                disk INTEGER DEFAULT (0),
                enabled_alert INTEGER DEFAULT (1),
                input_history TEXT,
                input_raw TEXT, 
                created_at TEXT(20), 
                updated_at TEXT(20)
            );

            CREATE INDEX servers_public_ip_platform_idx ON servers (public_ip,platform);
            CREATE INDEX servers_server_name_idx ON servers (server_name);
            CREATE INDEX servers_public_ip_idx ON servers (public_ip);
            CREATE INDEX servers_cpu_idx ON servers (cpu);
            CREATE INDEX servers_ram_idx ON servers (ram);
            CREATE INDEX servers_disk_idx ON servers (disk);
            CREATE INDEX servers_updated_at_idx ON servers (updated_at);
        ";
        $db->exec($tableSql);
    } else {
        $db = new PDO('sqlite:' . $databaseFile);
    }

    $selectSql = "SELECT * FROM servers WHERE platform = :platform AND public_ip = :public_ip LIMIT 1";
    $selectStmt = $db->prepare($selectSql);
    $selectStmt->bindParam(':platform', $requestContent['PLATFORM'], PDO::PARAM_STR);
    $selectStmt->bindParam(':public_ip', $requestContent['PUBLIC_IP'], PDO::PARAM_STR);
    $selectStmt->execute();
    $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $sql = "";
        $sql .= "UPDATE servers SET username = :username, instance_id = :instance_id, cpu = :cpu, ram = :ram, disk = :disk, input_raw = :input_raw, input_history = :input_history, updated_at = :updated_at";
        $sql .= " WHERE platform = :platform AND public_ip = :public_ip";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':instance_id', $requestContent['INSTANCE_ID'], PDO::PARAM_STR);
        $inputHistoryOld = json_decode($row['input_history'], true);

        // Add the new input to the history
        array_unshift($inputHistoryOld, $input);

        // Only keep the last 5 inputs
        $inputHistoryOld = array_slice($inputHistoryOld, 0, 5);

        $inputHistory = json_encode($inputHistoryOld);

        $stmt->bindParam(':username', $requestContent['USERNAME'], PDO::PARAM_STR);
        $stmt->bindParam(':instance_id', $requestContent['INSTANCE_ID'], PDO::PARAM_STR);
        $stmt->bindParam(':public_ip', $requestContent['PUBLIC_IP'], PDO::PARAM_STR);
        $stmt->bindParam(':platform', $requestContent['PLATFORM'], PDO::PARAM_STR);
        $stmt->bindParam(':cpu',  $cpuDetails['usage_percent'], PDO::PARAM_INT);
        $stmt->bindParam(':ram',  $ramDetails['usage_percent'], PDO::PARAM_INT);
        $stmt->bindParam(':disk',  $diskDetails['usage_percent'], PDO::PARAM_INT);
        $stmt->bindParam(':input_raw',  $inputRaw, PDO::PARAM_STR);
        $stmt->bindParam(':input_history', $inputHistory, PDO::PARAM_STR);
        $stmt->bindParam(':updated_at',  date('Y-m-d H:i:s'), PDO::PARAM_STR);
    } else {
        $sql = "";
        $sql .= "INSERT INTO servers (instance_id, public_ip, username, platform, cpu, ram, disk, input_raw, input_history, created_at, updated_at)";
        $sql .= " VALUES (:instance_id, :public_ip, :username, :platform, :cpu, :ram, :disk, :input_raw, :input_history, :created_at, :updated_at)";
        $stmt = $db->prepare($sql);

        $inputHistory = json_encode([$input]);

        $stmt->bindParam(':username', $requestContent['USERNAME'], PDO::PARAM_STR);
        $stmt->bindParam(':instance_id', $requestContent['INSTANCE_ID'], PDO::PARAM_STR);
        $stmt->bindParam(':public_ip', $requestContent['PUBLIC_IP'], PDO::PARAM_STR);
        $stmt->bindParam(':platform', $requestContent['PLATFORM'], PDO::PARAM_STR);
        $stmt->bindParam(':cpu',  $cpuDetails['usage_percent'], PDO::PARAM_INT);
        $stmt->bindParam(':ram',  $ramDetails['usage_percent'], PDO::PARAM_INT);
        $stmt->bindParam(':disk',  $diskDetails['usage_percent'], PDO::PARAM_INT);
        $stmt->bindParam(':input_raw',  $inputRaw, PDO::PARAM_STR);
        $stmt->bindParam(':input_history',  $inputHistory, PDO::PARAM_STR);
        $stmt->bindParam(':created_at',  date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindParam(':updated_at',  date('Y-m-d H:i:s'), PDO::PARAM_STR);
    }

    $stmt->execute();
} catch (\Exception $e) {
    $message = "{$mentor}\n```ERROR: [File: {$e->getFile()}] [Line: {$e->getLine()}] {$e->getMessage()}```";
    echo $message;
    GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}
