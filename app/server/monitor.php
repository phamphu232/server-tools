<?php
require_once __DIR__ . '/../../helpers/App.php';
require_once __DIR__ . '/../../helpers/Config.php';
require_once __DIR__ . '/../../helpers/GoogleChat.php';

use helpers\App;
use helpers\Config;
use helpers\GoogleChat;

$config = Config::get();

try {
    $clientIp = App::getClientIp();
    $allowedIp = explode(',', $config['app_server']['allowed_ip']);

    if (!in_array($clientIp, $allowedIp)) {
        echo "Your ip: {$clientIp} is not allowed";
        exit();
    }

    $directory = "{$config['BASE_DIR']}/cache/servers/";
    $jsonFiles = glob($directory . '*.json');

    $CPULimit = 95;
    $RAMLimit = 95;
    $DISKLimit = 95;

    $tr = '';
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

        $linkCloud = "javascript:;";
        if (strtolower($jsonData['PLATFORM']) == 'aws') {
            $linkCloud = "https://ap-northeast-1.console.aws.amazon.com/ec2/home?region=ap-northeast-1#InstanceDetails:instanceId={$jsonData['INSTANCE_ID']}";
        } else if (strtolower($jsonData['PLATFORM']) == 'gcp') {
            $linkCloud = "https://console.cloud.google.com/compute/instancesDetail/zones/asia-northeast2-a/instances/{$jsonData['INSTANCE_ID']}&authuser=1";
        }

        $no = $i + 1;
        $tr .= "<tr>";
        $tr .= "<td align=\"center\">{$no}</td>";
        $tr .= "<td>{$jsonData['PLATFORM']}</td>";
        $tr .= "<td><a href=\"{$linkCloud}\" target=\"_blank\">{$jsonData['INSTANCE_ID']}</a></td>";
        $tr .= "<td><a href=\"https://ipinfo.io/{$jsonData['PUBLIC_IP']}/json\" target=\"_blank\">{$jsonData['PUBLIC_IP']}</a></td>";
        $tr .= "<td>{$jsonData['CPU']['usage_percent']}%</td>";
        $tr .= "<td>{$jsonData['RAM']['usage_percent']}%</td>";
        $tr .= "<td>{$jsonData['DISK']['usage_percent']}%</td>";
        $tr .= "</tr>";
    }

    echo "<table border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"margin:auto;\">
    <thead>
    <tr>
    <th>No</th>
    <th>PLATFORM</th>
    <th>INSTANCE_ID</th>
    <th>PUBLIC_IP</th>
    <th>CPU</th>
    <th>RAM</th>
    <th>DISK</th>
    </tr>
    </thead>
    <tbody>
    {$tr}
    </tbody>
    </table>";
} catch (\Exception $e) {
    $mentor = $config['google_chat']['mentor_system_user'];
    $mentor = empty($mentor) ? '' : $mentor;
    $message = "{$mentor}\n```ERROR: [Line: {$e->getLine()}] {$e->getMessage()}```";
    echo $message;
    GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}
