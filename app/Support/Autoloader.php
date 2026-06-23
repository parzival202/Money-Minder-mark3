<?php

namespace App\Support;

final class Autoloader
{
    private static array $prefixes = [];
    private static bool $registered = false;

    public static function register(array $prefixes): void
    {
        foreach ($prefixes as $prefix => $baseDir) {
            self::$prefixes[rtrim($prefix, '\\') . '\\'] = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        if (!self::$registered) {
            spl_autoload_register([self::class, 'autoload']);
            self::$registered = true;
        }
    }

    private static function autoload(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (strpos($class, $prefix) !== 0) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
            $file = $baseDir . $relativePath;

            if (is_file($file)) {
                require_once $file;
            }
        }
    }
}
