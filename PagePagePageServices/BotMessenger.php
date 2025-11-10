<?php
declare(strict_types=1);

namespace PagePagePageServices;

use PagePagePageBot\TelegramClientInterface;

final class BotMessenger
{
    public function __construct(
        private Config $config,
        private TelegramClientInterface $client
    ) {
    }

    public function send(int $chatId, string $text): void
    {
        $this->client->sendMessage($this->config->botToken, $chatId, $text);
    }

    public function sendHtmlWithButtons(int $chatId, string $html, array $buttons): void
    {
        $this->client->sendPayload($this->config->botToken, [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    public function edit(int $chatId, int $messageId, string $html, array $buttons = []): void
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $html,
            'parse_mode' => 'HTML',
        ];

        if ($buttons !== []) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
        }

        $this->client->editMessage($this->config->botToken, $payload);
    }
}
