<?php
require_once __DIR__ . '/../../bootstrap/bootstrap.php';

require_once __DIR__ . '/../../helpers/App.php';
require_once __DIR__ . '/../../helpers/Cache.php';
require_once __DIR__ . '/../../helpers/Config.php';
require_once __DIR__ . '/../../helpers/GoogleChat.php';
require_once __DIR__ . '/../../helpers/Skype.php';

use helpers\App;
use helpers\Cache;
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

    $arrServerConfig = [];
    if (file_exists($config['BASE_DIR'] . '/cache/config/servers.json')) {
        $arrServerConfig = json_decode(file_get_contents($config['BASE_DIR'] . '/cache/config/servers.json'), true);
    }

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

        if (!empty($arrServerConfig[$fileName])) {
            $jsonData = array_merge($jsonData, $arrServerConfig[$fileName]);
        }

        if ($jsonData['TIMESTAMP'] < strtotime('-3 minutes')) {
            $jsonData['is_missing_report'] = true;
            $arrWarning[] = $jsonData;
            continue;
        }

        $CPULimit = 0;
        if (!empty($jsonData['CPU_THROTTLE'])) {
            $CPULimit = $jsonData['CPU_THROTTLE'];
        }

        $RAMLimit = 0;
        if (!empty($jsonData['RAM_THROTTLE'])) {
            $RAMLimit = $jsonData['RAM_THROTTLE'];
        }

        $DISKLimit = 0;
        if (!empty($jsonData['DISK_THROTTLE'])) {
            $DISKLimit = $jsonData['DISK_THROTTLE'];
        }

        if (intval($jsonData['DISK']['usage_percent']) > $DISKLimit && $DISKLimit > 0) {
            $jsonData['is_full_disk'] = true;
            $arrWarning[] = $jsonData;
        }

        $inputHistory = $jsonData['INPUT_HISTORY'];
        if (count($inputHistory) >= 5) {
            $jsonData['is_high_cpu'] = true;
            $jsonData['is_full_ram'] = true;
            foreach ($inputHistory as $input) {
                if (intval($input['CPU']['usage_percent']) < $CPULimit && $CPULimit > 0) {
                    $jsonData['is_high_cpu'] = false;
                }
                if (intval($input['RAM']['usage_percent']) < $RAMLimit && $RAMLimit > 0) {
                    $jsonData['is_full_ram'] = false;
                }
            }

            if (!empty($jsonData['is_high_cpu'])) {
                $arrWarning[] = $jsonData;
            }

            if (!empty($jsonData['is_full_ram'])) {
                $arrWarning[] = $jsonData;
            }
        }
    }

    if (empty($arrWarning)) {
        echo "Everything is fine.";
        return;
    }

    $cacheObj = new Cache("config/servers.json");
    $nowTime = date('Y-m-d H:i:s');

    $message = "";
    foreach ($arrWarning as $server) {
        $linkCloud = "";
        if (strtolower($server['PLATFORM']) == 'aws') {
            // $linkCloud = "https://ap-northeast-1.console.aws.amazon.com/ec2/home?region=ap-northeast-1#InstanceDetails:instanceId={$server['instance_id']}";
        } else if (strtolower($server['PLATFORM']) == 'gcp') {
            // $linkCloud = "https://console.cloud.google.com/compute/instancesDetail/zones/asia-northeast2-a/instances/{$server['instance_id']}&authuser=1";
        }

        $keyCache = "{$server['PLATFORM']}_{$server['PUBLIC_IP']}";
        $arrServerConfig[$keyCache] = !empty($arrServerConfig[$keyCache]) ? $arrServerConfig[$keyCache] : [];

        $updatedAt = date('Y-m-d H:i:s', $server['TIMESTAMP']);
        $missingReport = '';
        if (
            !empty($server['is_missing_report']) && (
                empty($arrServerConfig[$keyCache]['LAST_ALERT_MISSING_REPORT']) ||
                strtotime($nowTime) - strtotime($arrServerConfig[$keyCache]['LAST_ALERT_MISSING_REPORT']) > 15 * 60
            )
        ) {
            $missingReport = "[Missing Report From: {$updatedAt}]";
            $arrServerConfig[$keyCache]['LAST_ALERT_MISSING_REPORT'] = $nowTime;
        }

        $heightCPU = '';
        if (!empty($server['is_high_cpu'])) {
            $heightCPU = "[High CPU: {$server['CPU']['usage_percent']}%]";
            $arrServerConfig[$keyCache]['LAST_ALERT_HIGH_CPU'] = $nowTime;
        }

        $fullRAM = '';
        if (!empty($server['is_full_ram'])) {
            $fullRAM = "[Full RAM: {$server['RAM']['usage_percent']}% ~ " . convertKBtoGB($server['RAM']['used']) . "GB / " . convertKBtoGB($server['RAM']['total']) . "GB]";
            $arrServerConfig[$keyCache]['LAST_ALERT_FULL_RAM'] = $nowTime;
        }

        $fullDisk = '';
        if (
            !empty($server['is_full_disk']) && (
                empty($arrServerConfig[$keyCache]['LAST_ALERT_FULL_DISK']) ||
                strtotime($nowTime) - strtotime($arrServerConfig[$keyCache]['LAST_ALERT_FULL_DISK']) > 2 * 60 * 60
            )
        ) {
            $fullDisk = "[Full Disk: {$server['DISK']['usage_percent']}% ~ " . convertKBtoGB($server['DISK']['used']) . "GB / " . convertKBtoGB($server['DISK']['total']) . "GB]";
            $arrServerConfig[$keyCache]['LAST_ALERT_FULL_DISK'] = $nowTime;
        }

        if (!empty($missingReport) || !empty($heightCPU) || !empty($fullRAM) || !empty($fullDisk)) {
            $cacheObj->set($keyCache, $arrServerConfig[$keyCache]);

            $message .= "Server: {$server['SERVER_NAME']} | Public IP: <a href=\"https://ipinfo.io/{$server['PUBLIC_IP']}/json\">{$server['PUBLIC_IP']}</a> | Platform: <a href=\"{$linkCloud}\">{$server['PLATFORM']}</a><br/>";
            $message .= " => <b>{$missingReport} {$heightCPU} {$fullRAM} {$fullDisk}</b><br/><br/>";
        }
    }

    if (!empty($message) && !empty($config['skype']['mentor'])) {
        $message = "{$config['skype']['mentor']} <b>Server Warnings</b><br/>" . $message;
    }

    if (!empty($message)) {
        echo $message;
        Skype::send($message, $config['skype']['recipient'], $config['skype']['endpoint'], $config['skype']['username'], $config['skype']['password']);
    } else {
        echo "Everything is okay.";
    }
} catch (\Exception $e) {
    $mentor = $config['google_chat']['mentor_system_user'];
    $mentor = empty($mentor) ? '' : $mentor;
    $message = "{$mentor}\n```ERROR: [Line: {$e->getLine()}] {$e->getMessage()}```";
    echo $message;
    GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}
