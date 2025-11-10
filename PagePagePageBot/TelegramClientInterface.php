<?php
declare(strict_types=1);

namespace PagePagePageBot;

interface TelegramClientInterface
{
    public function sendMessage(string $token, int $chatId, string $text): void;
    public function sendPayload(string $token, array $payload): void;
    public function editMessage(string $token, array $payload): void;
}
