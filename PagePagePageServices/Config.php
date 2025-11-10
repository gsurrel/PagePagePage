<?php
declare(strict_types=1);

namespace PagePagePageServices;

final readonly class Config
{
    public string $botToken;
    public string $webhookToken;
    public string $salt;
    public string $baseAddress;

    public function __construct(string $botToken, string $webhookToken, string $salt, string $baseAddress)
    {
        if ($botToken === '' || $webhookToken === '' || $salt === '') {
            throw new \InvalidArgumentException("Config values must not be empty.");
        }

        $this->botToken = $botToken;
        $this->webhookToken = $webhookToken;
        $this->salt = $salt;
        $this->baseAddress = $baseAddress;
    }

    /**
     * Load config from environment variables or fallback defaults.
     */
    public static function fromEnv(): self
    {
        $botToken = getenv('PAGEPAGEPAGE_BOT_TOKEN') ?: '';
        $webhookToken = getenv('PAGEPAGEPAGE_WEBHOOK_TOKEN') ?: '';
        $salt = getenv('PAGEPAGEPAGE_SALT') ?: '';
        $baseAddress = getenv('PAGEPAGEPAGE_BASE_ADDRESS') ?: '';

        return new self($botToken, $webhookToken, $salt, $baseAddress);
    }
}
