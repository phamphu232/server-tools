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

  $arrServerConfig = [];
  if (file_exists($config['BASE_DIR'] . '/cache/config/servers.json')) {
    $arrServerConfig = json_decode(file_get_contents($config['BASE_DIR'] . '/cache/config/servers.json'), true);
  }

  $directory = "{$config['BASE_DIR']}/cache/servers/";
  $jsonFiles = glob($directory . '*.json');

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

    if (!empty($arrServerConfig[$fileName])) {
      $jsonData = array_merge($jsonData, $arrServerConfig[$fileName]);
    } else {
      $jsonData['CPU_THROTTLE'] = 0;
      $jsonData['RAM_THROTTLE'] = 0;
      $jsonData['DISK_THROTTLE'] = 0;
    }

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
    $tr .= "<td align=\"left\"><input type=\"text\" data-key=\"{$jsonData['PLATFORM']}_{$jsonData['PUBLIC_IP']}\" name=\"person_in_charge\" style=\"border:none; width:100%;\" class=\"config\" value=\"{$jsonData['PERSON_IN_CHARGE']}\" /></td>";
    $tr .= "<td align=\"left\"><input type=\"text\" data-key=\"{$jsonData['PLATFORM']}_{$jsonData['PUBLIC_IP']}\" name=\"server_name\" style=\"border:none; width:100%;\" class=\"config\" value=\"{$jsonData['SERVER_NAME']}\" /></td>";
    $tr .= "<td>{$jsonData['PLATFORM']}</td>";
    $tr .= "<td><a href=\"#{$linkCloud}\" style=\"white-space: nowrap; color: #00F;\" target=\"_blank\">{$jsonData['INSTANCE_ID']}</a></td>";
    $tr .= "<td><a href=\"https://ipinfo.io/{$jsonData['PUBLIC_IP']}/json\" style=\"white-space: nowrap; color: #00F;\" target=\"_blank\">{$jsonData['PUBLIC_IP']}</a></td>";
    $tr .= "<td align=\"left\">{$jsonData['CPU']['usage_percent']}%</td>";
    $tr .= "<td class=\"nowrap\" align=\"right\" style=\"width:30px; color:#ccc;\"><input type=\"text\" data-key=\"{$jsonData['PLATFORM']}_{$jsonData['PUBLIC_IP']}\" name=\"cpu_throttle\" style=\"border:none;width:30px;\" class=\"config warning text-right\" value=\"{$jsonData['CPU_THROTTLE']}\" pattern=\"[0-9]*\"/>%</td>";
    $tr .= "<td align=\"left\">{$jsonData['RAM']['usage_percent']}% ~ " . convertKBtoGB($jsonData['RAM']['used']) . "GB / " . convertKBtoGB($jsonData['RAM']['total']) . "GB</td>";
    $tr .= "<td class=\"nowrap\" align=\"right\" style=\"width:30px; color:#ccc;\"><input type=\"text\" data-key=\"{$jsonData['PLATFORM']}_{$jsonData['PUBLIC_IP']}\" name=\"ram_throttle\" style=\"border:none;width:30px;\" class=\"config warning text-right\" value=\"{$jsonData['RAM_THROTTLE']}\" pattern=\"[0-9]*\"/>%</td>";
    $tr .= "<td align=\"left\">{$jsonData['DISK']['usage_percent']}% ~ " . convertKBtoGB($jsonData['DISK']['used']) . "GB / " . convertKBtoGB($jsonData['DISK']['total']) . "GB</td>";
    $tr .= "<td class=\"nowrap\" align=\"right\" style=\"width:30px; color:#ccc;\"><input type=\"text\" data-key=\"{$jsonData['PLATFORM']}_{$jsonData['PUBLIC_IP']}\" name=\"disk_throttle\" style=\"border:none;width:30px;\"class=\"config warning text-right\" value=\"{$jsonData['DISK_THROTTLE']}\" pattern=\"[0-9]*\"/>%</td>";
    $tr .= "<td align=\"right\" class=\"nowrap\">{$updatedAt}</td>";
    $tr .= "</tr>";
  }

  echo "
        <div style=\"margin: 15px;\">
        <div><h1 style=\"text-align: left; margin-bottom: 5px;\">Server Monitor</h1></div>
        <div style=\"text-align: right; font-weight: bold; margin-bottom: 5px;\"><label><input type=\"checkbox\" value=\"1\" id=\"auto_refresh\" checked>Auto Refresh</label></div>
        <table border=\"1\" cellpadding=\"5\" cellspacing=\"0\" style=\"width:100%; margin:auto;\">
        <thead>
        <tr>
        <th>No</th>
        <th>PERSON IN CHARGE <span style=\"font-weight: normal; color: #ccc;\">(Skype)</span></th>
        <th>SERVER NAME</th>
        <th>PLATFORM</th>
        <th>INSTANCE_ID</th>
        <th>PUBLIC_IP</th>
        <th colspan=\"2\">CPU <span style=\"font-weight: normal; color: #ccc;\">| Throttle</span></th>
        <th colspan=\"2\">RAM <span style=\"font-weight: normal; color: #ccc;\">| Throttle</th>
        <th colspan=\"2\">DISK <span style=\"font-weight: normal; color: #ccc;\">| Throttle</th>
        <th>UPDATE_AT</th>
        </tr>
        </thead>
        <tbody>
        {$tr}
        </tbody>
        </table>
        <div style=\"margin-top: 8px;\">
        <b>Notes:</b><br/>
        <i> - CPU, RAM: Warning if exceeding throttle for 5 minutes.</i><br/>
        <i> - DISK: Warning if exceeding throttle every 2 hours.</i><br/>
        <i> - MISSING REPORT: Warning every 15 minutes.</i><br/>
        <i> - Set throttle to 0 to disable warning.</i>
        </div>
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
<style>
  table {
    width: 100%;
    border-collapse: collapse;
  }

  table,
  th,
  td {
    border: 1px solid black;
  }

  th,
  td {
    padding: 5px;
  }

  .text-right {
    text-align: right;
  }

  .nowrap {
    white-space: nowrap;
  }

  .warning {
    color: #ccc;
  }
</style>

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

  // Select all elements with the class 'config'
  const editableElements = document.querySelectorAll('.config');
  let debounceTimeout; // Declare a variable to hold the timeout ID

  editableElements.forEach(element => {
    element.addEventListener('input', () => {
      clearTimeout(debounceTimeout); // Clear the previous timeout if it exists

      // Set a new timeout to delay the POST request
      debounceTimeout = setTimeout(() => {
        const key = element.dataset.key;

        fetch('./update.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              key: key,
              person_in_charge: document.querySelector(`[data-key="${key}"][name=person_in_charge]`).value,
              server_name: document.querySelector(`[data-key="${key}"][name=server_name]`).value,
              cpu_throttle: document.querySelector(`[data-key="${key}"][name=cpu_throttle]`).value,
              ram_throttle: document.querySelector(`[data-key="${key}"][name=ram_throttle]`).value,
              disk_throttle: document.querySelector(`[data-key="${key}"][name=disk_throttle]`).value,
              action: "update"
            })
          })
          .then(response => response.text())
          .then(data => {
            console.log('Response:', data); // Log the server's response
          })
          .catch(error => {
            console.error('Error:', error); // Log any error
          });
      }, 500); // Set the delay (e.g., 500 milliseconds)
    });
  });
</script>