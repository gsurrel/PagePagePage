<?php
declare(strict_types=1);

namespace PagePagePageServices;

use PagePagePageBot\CommandRouter;
use PagePagePageBot\DummyTelegramClient;
use PagePagePageBot\TelegramClient;
use PagePagePageBot\TelegramClientInterface;
use PagePagePageServices\FileSystemLogger;
use PagePagePageServices\MemoryLogger;

final class ServiceProvider
{
    private bool $testing;

    public function __construct(private Config $config)
    {
        $this->testing = \defined('PAGEPAGEPAGE_TESTING') && PAGEPAGEPAGE_TESTING === true;
    }

    public function getTelegramClient(): TelegramClientInterface
    {
        return $this->testing
            ? new DummyTelegramClient()
            : new TelegramClient();
    }

    public function getSiteManager(string $userDir, ?string $username): SiteManager
    {
        $storage = new SiteStorage($userDir, $this->getLogger($userDir), $username);
        return new SiteManager($storage, $this->getLogger($userDir));
    }

    public function getLogger(string $userDir): LoggerInterface
    {
        return $this->testing
            ? new MemoryLogger()
            : new FileSystemLogger($userDir);
    }

    public function getMessenger(): BotMessenger
    {
        return new BotMessenger($this->config, $this->getTelegramClient());
    }

    public function getCommandRouter(CommandContext $ctx): CommandRouter
    {
        return new CommandRouter($ctx);
    }
}
