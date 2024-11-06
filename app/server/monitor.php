<?php
require_once __DIR__ . '/../../bootstrap/bootstrap.php';

require_once __DIR__ . '/../../helpers/App.php';
require_once __DIR__ . '/../../helpers/Config.php';
require_once __DIR__ . '/../../helpers/GoogleChat.php';

use helpers\App;
use helpers\Config;
use helpers\GoogleChat;

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
        $updatedAt = date('Y-m-d H:i:s', $jsonData['TIMESTAMP']);

        $tr .= "<tr>";
        $tr .= "<td align=\"center\">{$no}</td>";
        $tr .= "<td>{$jsonData['PLATFORM']}</td>";
        $tr .= "<td><a href=\"#{$linkCloud}\">{$jsonData['INSTANCE_ID']}</a></td>";
        $tr .= "<td><a href=\"https://ipinfo.io/{$jsonData['PUBLIC_IP']}/json\" target=\"_blank\">{$jsonData['PUBLIC_IP']}</a></td>";
        $tr .= "<td>{$jsonData['CPU']['usage_percent']}%</td>";
        $tr .= "<td>{$jsonData['RAM']['usage_percent']}% ~ " . convertKBtoGB($jsonData['RAM']['used']) . "GB / " . convertKBtoGB($jsonData['RAM']['total']) . "GB</td>";
        $tr .= "<td>{$jsonData['DISK']['usage_percent']}% ~ " . convertKBtoGB($jsonData['DISK']['used']) . "GB / " . convertKBtoGB($jsonData['DISK']['total']) . "GB</td>";
        $tr .= "<td>{$updatedAt}</td>";
        $tr .= "</tr>";
    }

    echo "
        <div style=\"max-width: 1000px; margin: auto;\">
        <div style=\"text-align: center;\"><h1>Server Monitor</h1></div>
        <div style=\"text-align: right; font-weight: bold; margin-bottom: 5px;\"><label><input type=\"checkbox\" value=\"1\" id=\"auto_refresh\" checked>Auto Refresh</label></div>
        <table border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"width:100%; margin:auto;\">
        <thead>
        <tr>
        <th>No</th>
        <th>PLATFORM</th>
        <th>INSTANCE_ID</th>
        <th>PUBLIC_IP</th>
        <th>CPU</th>
        <th>RAM</th>
        <th>DISK</th>
        <th>UPDATE_AT</th>
        </tr>
        </thead>
        <tbody>
        {$tr}
        </tbody>
        </table>
        </div>
    ";
} catch (\Exception $e) {
    $mentor = $config['google_chat']['mentor_system_user'];
    $mentor = empty($mentor) ? '' : $mentor;
    $message = "{$mentor}\n```ERROR: [Line: {$e->getLine()}] {$e->getMessage()}```";
    echo $message;
    GoogleChat::send($message, $config['google_chat']['webhook_system_team']);
}

?>

<script>
    // Select the checkbox element
const checkbox = document.getElementById("auto_refresh");

// Function to reload the page every minute
function reloadPageEveryMinute() {
  setInterval(() => {
    if (checkbox.checked) {
      location.reload();
    }
  }, 30000); // 60000 milliseconds = 1 minute
}

// Run the function when the checkbox state changes or if it's already checked
checkbox.addEventListener("change", () => {
  if (checkbox.checked) {
    reloadPageEveryMinute();
  }
});

// Start reloading automatically if checkbox is checked by default
if (checkbox.checked) {
  reloadPageEveryMinute();
}

</script>
