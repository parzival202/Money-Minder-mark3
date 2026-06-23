<?php
// Optional local configuration overrides.
// Create config.local.php for machine-specific values that should not be committed.
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    require_once $localConfigPath;
}

if (!defined('BOT_TOKEN')) {
    define('BOT_TOKEN', getenv('MM_BOT_TOKEN') ?: '');
}

if (!defined('CHAT_ID')) {
    define('CHAT_ID', getenv('MM_CHAT_ID') ?: '');
}

if (!defined('CURRENCY')) {
    define('CURRENCY', getenv('MM_CURRENCY') ?: 'FCFA');
}
