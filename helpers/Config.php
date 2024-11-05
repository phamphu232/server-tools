<?php

namespace helpers;

class Config
{
    public static function get($key = null, $default = null)
    {
        $envFilePath = __DIR__ . "/../env.ini";

        if (!file_exists($envFilePath) && $key !== 'BASE_DIR') {
            return $default;
        }

        $env = parse_ini_file($envFilePath, true);

        $env['BASE_DIR'] = dirname(dirname(__FILE__));

        if ($key === null) {
            return $env;
        }

        if (isset($env[$key])) {
            return $env[$key];
        }

        return $default;
    }
}
