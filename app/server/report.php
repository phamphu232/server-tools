<?php
require_once __DIR__ . '/../../helpers/Config.php';
require_once __DIR__ . '/../../helpers/GoogleChat.php';
require_once __DIR__ . '/../../helpers/Skype.php';

use helpers\Config;
use helpers\GoogleChat;
use helpers\Skype;

$config = Config::get();

try {
    $databaseFile = __DIR__ . '/../../database.sqlite';
    if (!file_exists($databaseFile)) {
        throw new \Exception("Database file not found: {$databaseFile}");
    }

    $db = new PDO('sqlite:' . $databaseFile);

    $sql = "SELECT * FROM servers";
    $stmt = $db->prepare($sql);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $CPULimit = 95;
    $RAMLimit = 95;
    $DISKLimit = 95;

    $arrWarning = [];
    foreach ($rows as $row) {
        if (empty($row['enabled_alert'])) {
            continue;
        }

        if (strtotime($row['updated_at']) < strtotime('-5 minutes')) {
            $row['is_missing_report'] = true;
            $arrWarning[] = $row;
            continue;
        }

        if (intval($row['disk']) > $DISKLimit) {
            $row['is_high_disk'] = true;
            $arrWarning[] = $row;
        }

        $inputHistory = json_decode($row['input_history'], true);
        if (count($inputHistory) == 5) {
            $row['is_high_cpu'] = true;
            $row['is_high_ram'] = true;
            foreach ($inputHistory as $input) {
                if (intval($input['CPU']['usage_percent']) < $CPULimit) {
                    $row['is_high_cpu'] = false;
                }
                if (intval($input['RAM']['usage_percent']) < $RAMLimit) {
                    $row['is_high_ram'] = false;
                }
            }

            if (!empty($row['is_high_cpu'])) {
                $arrWarning[] = $row;
            }

            if (!empty($row['is_high_ram'])) {
                $arrWarning[] = $row;
            }
        }
    }

    if (empty($arrWarning)) {
        return;
    }

    $message = "";
    foreach ($arrWarning as $server) {
        $linkCloud = "";
        if (strtolower($server['platform']) == 'aws') {
            $linkCloud = "https://ap-northeast-1.console.aws.amazon.com/ec2/home?region=ap-northeast-1#InstanceDetails:instanceId={$server['instance_id']}";
        } else if (strtolower($server['platform']) == 'gcp') {
            $linkCloud = "https://console.cloud.google.com/compute/instancesDetail/zones/asia-northeast2-a/instances/{$server['instance_id']}&authuser=1";
        }
        $missingReport = !empty($server['is_missing_report']) ? "[Missing Report From: {$server['updated_at']}]" : '';
        $heightCPU = !empty($server['is_high_cpu']) ? "[High CPU: {$server['cpu']}%]" : '';
        $heightRAM = !empty($server['is_high_ram']) ? "[High RAM: {$server['ram']}%]" : '';
        $heightDisk = !empty($server['is_high_disk']) ? "[High Disk: {$server['disk']}%]" : '';
        $message .= "Platform: <a href=\"{$linkCloud}\">{$server['platform']}</a> | Server: {$server['server_name']} | Public IP: <a href=\"https://ipinfo.io/{$server['public_ip']}/json\">{$server['public_ip']}</a> | Updated: {$server['updated_at']}<br/>";
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
