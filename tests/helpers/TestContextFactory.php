<?php
declare(strict_types=1);

use PagePagePageServices\CommandContext;
use PagePagePageServices\Config;
use PagePagePageServices\SiteManager;
use PagePagePageServices\BotMessenger;
use PagePagePageServices\MemoryLogger;
use PagePagePageBot\TelegramClientInterface;

function createTestContextWithRealSiteManager(
    TelegramClientInterface $client,
    string $username = 'tester',
    int $chatId = 123456,
    ?int $messageId = null
): array {
    $tmpDir = sys_get_temp_dir() . '/pagepagepage-test-' . uniqid();
    mkdir($tmpDir, recursive: true);

    $logger = new MemoryLogger();
    $manager = new SiteManager($tmpDir, $logger);
    $config = new Config('fake-token', 'fake-salt');
    $messenger = new BotMessenger($config, $client);

    $ctx = new CommandContext($config, $manager, $logger, $messenger, $chatId, $username, $messageId);

    return [$ctx, $logger, $manager, $tmpDir];
}
