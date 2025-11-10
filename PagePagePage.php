<?php
/**
 * PagePagePage.php — Using Telegram for publishing static sites
 *
 * 1. Ensure to register your Telegram hook:
 * 
 *     curl -F "url=https://pagepagepage.org/<this-page-path>.php" \
 *          -F "secret_token=<some-secret-string-to-authenticate-telegram-requests>" \
 *          https://api.telegram.org/bot<telegram-api-token>/setWebhook
 * 
 *     Variables:
 *         - <this-page-path>:
 *               Avoid incoming requests not coming from Telegram.
 *         - <some-secret-string-to-authenticate-telegram-requests>:
 *               In case the secret path has been found, this ensures that only requests
 *               coming from Telegram will be accepted as they must contain that secret string.
 *         - <telegram-api-token>:
 *               The Telegram API token that is provided by https://t.me/BotFather once you
 *               have created a bot to interact with your PagePagePage instance.
 * 
 * 
 * 2. Set your environment variables:
 * 
 *     - PAGEPAGEPAGE_BOT_TOKEN (your Telegram api token)
 *     - PAGEPAGEPAGE_WEBHOOK_TOKEN (secret access token emited for Telegram to use in the webhook requests)
 *     - PAGEPAGEPAGE_SALT (random salt string, unique per installation)
 * 
 *     In case you cannot set environment variables, edit the PagePagePageServices/Config.php file to set the fallback values
 * 
 * 3. 
 * 
 * 
 */

declare(strict_types=1);

// Turn on all errors, warnings, notices
error_reporting(E_ALL);

// Log errors to a file instead of displaying them
ini_set('display_errors', 'Off');  // Don't show errors to users
ini_set('log_errors', 'On');       // Turn on logging

// Specify the log file path
ini_set('error_log', 'logfile.log');

// Optional: catch uncaught exceptions
set_exception_handler(function ($e) {
    error_log(print_r($e, true));
});

// Custom simple autoloader for classes
spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/';

    $classPath = str_replace('\\', '/', $class);
    $fullPath = "$baseDir$classPath.php";

    if (file_exists($fullPath))
        require_once $fullPath;
});

use PagePagePageServices\Config;
use PagePagePageBot\BotKernel;

// Initialize
$kernel = new BotKernel(Config::fromEnv());
$kernel->handleWebhook();
