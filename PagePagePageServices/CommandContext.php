<?php
declare(strict_types=1);

namespace PagePagePageServices;

final class CommandContext
{
    public function __construct(
        public readonly Config $config,
        public readonly SiteManager $siteManager,
        public readonly LoggerInterface $logger,
        public readonly BotMessenger $messenger,
        public readonly int $chatId,
        public readonly int $userId,
        public readonly ?int $messageId = null,
    ) {
    }
}
