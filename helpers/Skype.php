<?php

namespace helpers;

class Skype
{
    public static function send($message, $recipient, $webhook, $username, $password)
    {
        if (empty($recipient)) {
            if (php_sapi_name() == "cli") {
                print_r("Config:recipient is empty");
            }

            return;
        }

        if (empty($webhook)) {
            if (php_sapi_name() == "cli") {
                print_r("Config:webhook is empty");
            }

            return;
        }

        if (empty($username)) {
            if (php_sapi_name() == "cli") {
                print_r("Config:username is empty");
            }

            return;
        }

        if (empty($password)) {
            if (php_sapi_name() == "cli") {
                print_r("Config:password is empty");
            }

            return;
        }


        $data = json_encode([
            'username' => $username,
            'password' => $password,
            'message' => $message,
            'recipient' => $recipient,
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
