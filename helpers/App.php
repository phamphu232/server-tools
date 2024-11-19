<?php

namespace helpers;

class App
{
    public static function getClientIp()
    {
        $ipAddress = '';
        if (getenv('HTTP_CLIENT_IP')) {
            $ipAddress = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ipAddress = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_X_FORWARDED')) {
            $ipAddress = getenv('HTTP_X_FORWARDED');
        } else if (getenv('HTTP_FORWARDED_FOR')) {
            $ipAddress = getenv('HTTP_FORWARDED_FOR');
        } else if (getenv('HTTP_FORWARDED')) {
            $ipAddress = getenv('HTTP_FORWARDED');
        } else if (getenv('REMOTE_ADDR')) {
            $ipAddress = getenv('REMOTE_ADDR');
        } else {
            $ipAddress = 'UNKNOWN';
        }

        return $ipAddress;
    }

    public static function formatFailure($message, $data = null)
    {
        $format = [
            'Result' => 'ERROR',
            'Message' => $message,
        ];
        if (isset($data)) {
            $format = array_merge($format, $data);
        }
        return $format;
    }
    public static function formatSuccess($message = '', $data = null)
    {
        $format = [
            'Result' => 'OK',
            'Message' => $message,
            'Timestamp' => time(),
        ];
        if (isset($data)) {
            $format = array_merge($format, $data);
        }
        return $format;
    }
}
