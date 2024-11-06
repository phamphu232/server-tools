<?php
require_once __DIR__ . '/../../bootstrap/bootstrap.php';

require_once __DIR__ . '/../../helpers/App.php';
require_once __DIR__ . '/../../helpers/Config.php';
require_once __DIR__ . '/../../helpers/GoogleChat.php';
require_once __DIR__ . '/../../helpers/Skype.php';

use helpers\App;
use helpers\Config;
use helpers\GoogleChat;
use helpers\Skype;

$config = Config::get();

function convertKBtoGB($kilobytes)
{
    $gigabytes = $kilobytes / (1024 * 1024);
    return number_format($gigabytes, 2);
}

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

    $arrWarning = [];
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

        if ($jsonData['TIMESTAMP'] < strtotime('-5 minutes')) {
            $jsonData['is_missing_report'] = true;
            $arrWarning[] = $jsonData;
            continue;
        }

        if (intval($jsonData['DISK']['usage_percent']) > $DISKLimit) {
            $jsonData['is_high_disk'] = true;
            $arrWarning[] = $jsonData;
        }

        $inputHistory = $jsonData['INPUT_HISTORY'];
        if (count($inputHistory) == 5) {
            $jsonData['is_high_cpu'] = true;
            $jsonData['is_high_ram'] = true;
            foreach ($inputHistory as $input) {
                if (intval($input['CPU']['usage_percent']) < $CPULimit) {
                    $jsonData['is_high_cpu'] = false;
                }
                if (intval($input['RAM']['usage_percent']) < $RAMLimit) {
                    $jsonData['is_high_ram'] = false;
                }
            }

            if (!empty($jsonData['is_high_cpu'])) {
                $arrWarning[] = $jsonData;
            }

            if (!empty($jsonData['is_high_ram'])) {
                $arrWarning[] = $jsonData;
            }
        }
    }

    if (empty($arrWarning)) {
        return;
    }

    $message = "";
    foreach ($arrWarning as $server) {
        $linkCloud = "";
        if (strtolower($server['PLATFORM']) == 'aws') {
            // $linkCloud = "https://ap-northeast-1.console.aws.amazon.com/ec2/home?region=ap-northeast-1#InstanceDetails:instanceId={$server['instance_id']}";
        } else if (strtolower($server['PLATFORM']) == 'gcp') {
            // $linkCloud = "https://console.cloud.google.com/compute/instancesDetail/zones/asia-northeast2-a/instances/{$server['instance_id']}&authuser=1";
        }
        $updatedAt = date('Y-m-d H:i:s', $server['TIMESTAMP']);
        $missingReport = !empty($server['is_missing_report']) ? "[Missing Report From: {$updatedAt}]" : '';
        $heightCPU = !empty($server['is_high_cpu']) ? "[High CPU: {$server['CPU']['usage_percent']}%]" : '';
        $heightRAM = !empty($server['is_high_ram']) ? "[High RAM: {$server['RAM']['usage_percent']}% ~ " . convertKBtoGB($server['RAM']['used']) . "GB / " . convertKBtoGB($server['RAM']['total']) . "GB]" : '';
        $heightDisk = !empty($server['is_high_disk']) ? "[High Disk: {$server['DISK']['usage_percent']}% ~ " . convertKBtoGB($server['DISK']['used']) . "GB / " . convertKBtoGB($server['DISK']['total']) . "GB]" : '';
        $message .= "Platform: <a href=\"{$linkCloud}\">{$server['PLATFORM']}</a> | Public IP: <a href=\"https://ipinfo.io/{$server['PUBLIC_IP']}/json\">{$server['PUBLIC_IP']}</a> | Updated: {$updatedAt}<br/>";
        $message .= " => <b>{$missingReport} {$heightCPU} {$heightRAM} {$heightDisk}</b><br/><br/>";
    }

    if (!empty($config['skype']['mentor'])) {
        $message = "{$config['skype']['mentor']} <b>Server Warnings</b><br/>" . $message;
    }

    echo $message;

    Skype::send($message, $config['skype']['recipient'], $config['skype']['endpoint'], $config['skype']['username'], $config['skype']['password']);
} catch (\Exception $e) {
    $mentor = $config['google_chat']['mentor_system_user'];
    $mentor = empty($mentor) ? '' : $mentor;
    $message = "{$mentor}\n```ERROR: [Line: {$e->getLine()}] {$e->getMessage()}```";
    echo $message;
    GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}
