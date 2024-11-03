<?php

namespace helpers;

class GoogleChat
{
    public static function send($message, $webhook)
    {
        if (empty($webhook)) {
            if (php_sapi_name() == "cli") {
                print_r("Config:webhook is empty");
            }

            return;
        }

        $data = json_encode([
            'text' => $message
        ], JSON_UNESCAPED_UNICODE);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $webhook);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }
}
