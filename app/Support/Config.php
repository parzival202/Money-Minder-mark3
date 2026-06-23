<?php

namespace App\Support;

final class Config
{
    private static array $items = [];

    public static function loadDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $config = require $file;
            if (is_array($config)) {
                self::$items[$key] = $config;
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
