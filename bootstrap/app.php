<?php

require_once __DIR__ . '/../app/Support/Autoloader.php';

App\Support\Autoloader::register([
    'App\\' => __DIR__ . '/../app',
]);

App\Support\Config::loadDirectory(__DIR__ . '/../config');

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        $base = dirname(__DIR__);
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return App\Support\Config::get($key, $default);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string {
        $storage = base_path('storage');
        return $path === '' ? $storage : $storage . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!defined('APP_NAME')) {
    define('APP_NAME', (string)config('app.name', 'MoneyMinder'));
}

if (!defined('MONTHLY_SAVING_GOAL')) {
    define('MONTHLY_SAVING_GOAL', (float)config('app.savings.monthly_goal', 50000));
}

if (!defined('ANNUAL_SAVING_GOAL')) {
    define('ANNUAL_SAVING_GOAL', (float)config('app.savings.annual_goal', 600000));
}

$timezone = (string)config('app.timezone', 'Africa/Abidjan');
if ($timezone !== '') {
    date_default_timezone_set($timezone);
}
