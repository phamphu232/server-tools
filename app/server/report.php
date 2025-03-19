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

function extractPersonInChargeSkype($str)
{
    if (empty($str)) {
        return '';
    }

    $persons = trim($str);
    $persons = explode(',', $persons);
    $persons = array_map(function ($person) {
        return trim($person);
    }, $persons);

    foreach ($persons as $i => $person) {
        $person = explode('|', $person);
        $persons[$i] = "<at id=\"{$person[0]}\">{$person[1]}</at>";
    }

    return implode(' ', $persons);
}

try {
    $clientIp = App::getClientIp();
    $allowedIp = explode(',', $config['app_server']['allowed_ip']);

    if (php_sapi_name() !== 'cli' && !in_array($clientIp, $allowedIp)) {
        echo "Your ip: {$clientIp} is not allowed";
        exit();
    }

    $directory = "{$config['BASE_DIR']}/cache/servers/";
    $jsonFiles = glob($directory . '*.json');

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

        $timeProcessingLimit = 5;

        $inputHistory = $jsonData['INPUT_HISTORY'];
        if (count($inputHistory) >= 5) {
            $jsonData['is_high_cpu'] = true;
            $jsonData['is_full_ram'] = true;
            $jsonData['is_slow_processing'] = true;

            foreach ($inputHistory as $input) {
                if (intval(date('s', strtotime($input['TIMESTAMP']))) <= $timeProcessingLimit) {
                    $jsonData['is_slow_processing'] = false;
                }

                if (empty($CPULimit) || intval($input['CPU']['usage_percent']) <= $CPULimit) {
                    $jsonData['is_high_cpu'] = false;
                }

                if (empty($RAMLimit) || intval($input['RAM']['usage_percent']) <= $RAMLimit) {
                    $jsonData['is_full_ram'] = false;
                }
            }

            if (!empty($jsonData['is_slow_processing'])) {
                $arrWarning[] = $jsonData;
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

    $mentors = [];
    $messageTitle = [];
    $message = "";
    foreach ($arrWarning as $server) {
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
            $messageTitle['missing_report'] = '[Missing Report]';
        }

        $slowProcessing = '';
        if (
            !empty($server['is_slow_processing']) && (
                empty($arrServerConfig[$keyCache]['LAST_ALERT_SLOW_PROCESSING']) ||
                strtotime($nowTime) - strtotime($arrServerConfig[$keyCache]['LAST_ALERT_SLOW_PROCESSING']) > 5 * 60
            )
        ) {
            $slowProcessing = "[Slow Processing]";
            $arrServerConfig[$keyCache]['LAST_ALERT_SLOW_PROCESSING'] = $nowTime;
            $arrServerConfig[$keyCache]['LAST_ALERT_CPU_TOP'] = $server['CPU_TOP'];
            $arrServerConfig[$keyCache]['LAST_ALERT_RAM_TOP'] = $server['RAM_TOP'];
            $messageTitle['slow_processing'] = '[Slow Processing]';
        }

        $heightCPU = '';
        if (
            !empty($server['is_high_cpu']) && (
                empty($arrServerConfig[$keyCache]['LAST_ALERT_HIGH_CPU']) ||
                strtotime($nowTime) - strtotime($arrServerConfig[$keyCache]['LAST_ALERT_HIGH_CPU']) > 5 * 60
            )
        ) {
            $heightCPU = "[High CPU: {$server['CPU']['usage_percent']}%]";
            $arrServerConfig[$keyCache]['LAST_ALERT_HIGH_CPU'] = $nowTime;
            $arrServerConfig[$keyCache]['LAST_ALERT_CPU_TOP'] = $server['CPU_TOP'];
            $messageTitle['high_cpu'] = '[High CPU]';
        }

        $fullRAM = '';
        if (
            !empty($server['is_full_ram']) && (
                empty($arrServerConfig[$keyCache]['LAST_ALERT_FULL_RAM']) ||
                strtotime($nowTime) - strtotime($arrServerConfig[$keyCache]['LAST_ALERT_FULL_RAM']) > 5 * 60
            )
        ) {
            $fullRAM = "[Full RAM: {$server['RAM']['usage_percent']}% ~ " . convertKBtoGB($server['RAM']['used']) . "GB / " . convertKBtoGB($server['RAM']['total']) . "GB]";
            $arrServerConfig[$keyCache]['LAST_ALERT_FULL_RAM'] = $nowTime;
            $arrServerConfig[$keyCache]['LAST_ALERT_RAM_TOP'] = $server['RAM_TOP'];
            $messageTitle['full_ram'] = '[Full RAM]';
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
            $messageTitle['full_disk'] = '[Full Disk]';
        }

        if (!empty($missingReport) || !empty($heightCPU) || !empty($fullRAM) || !empty($fullDisk)) {
            $cacheObj->set($keyCache, $arrServerConfig[$keyCache]);

            $cloudLink = "javascript:;";
            if (strtolower($server['PLATFORM']) == 'aws') {
                $cloudLink = "https://{$server['ZONE_CODE']}.console.aws.amazon.com/ec2/home?region={$server['ZONE_CODE']}#InstanceDetails:instanceId={$server['INSTANCE_ID']}";
            } else if (strtolower($server['PLATFORM']) == 'gcp') {
                $cloudLink = "https://console.cloud.google.com/compute/instancesDetail/zones/{$server['ZONE_CODE']}/instances/{$server['INSTANCE_NAME']}?project={$server['PROJECT_ID']}&authuser=1";
            }

            $message .= "Server: {$server['SERVER_NAME']} | Public IP: <https://ipinfo.io/{$server['PUBLIC_IP']}/json\|{$server['PUBLIC_IP']}> | Platform: <{$cloudLink}|{$server['PLATFORM']}>\n";
            $message .= " => \n{$missingReport}{$slowProcessing}{$heightCPU}{$fullRAM}{$fullDisk}\n";

            // $mentors = extractPersonInChargeSkype($server['PERSON_IN_CHARGE']);
            if (!empty($server['PERSON_IN_CHARGE'])) {
                $message .= " => {$server['PERSON_IN_CHARGE']}\n";
            }
            $message .= "\n";
        }
    }

    if (!empty($message)) {
        $messageTitle = implode(' | ', $messageTitle);
        $signature = "_- Sent from: <{$config['app_server']['url']}|{$config['app_server']['name']}>at: " . date('Y-m-d H:i:s') . "(JST) -_";
        $message = "\nServers {$messageTitle}\n\n{$message}{$signature}";
    }

    if (!empty($message)) {
        echo $message;
        // Skype::send($message, $config['skype']['recipient'], $config['skype']['endpoint'], $config['skype']['username'], $config['skype']['password']);
        GoogleChat::send($message, $config['google_chat']['webhook_security_team']);
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
