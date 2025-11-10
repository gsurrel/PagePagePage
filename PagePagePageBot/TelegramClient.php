<?php
declare(strict_types=1);

namespace PagePagePageBot;

use PagePagePageUtils\Utils;

final class TelegramClient implements TelegramClientInterface
{
    public function sendMessage(string $token, int $chatId, string $text): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        $this->post($token, 'sendMessage', $payload);
    }

    public function sendPayload(string $token, array $payload): void
    {
        $this->post($token, 'sendMessage', $payload);
    }

    public function editMessage(string $token, array $payload): void
    {
        $this->post($token, 'editMessageText', $payload);
    }

    private function post(string $token, string $method, array $payload): void
    {
        $url = "https://api.telegram.org/bot{$token}/{$method}";
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json",
                'content' => $json
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            Utils::debug("❌ Telegram API call to $method failed.", __DIR__);
            return;
        }

        $result = json_decode($response, true);
        if (!is_array($result) || !($result['ok'] ?? false)) {
            $desc = $result['description'] ?? 'Unknown error';
            Utils::debug("⚠️ Telegram API error in $method: $desc", __DIR__);
        }
    }
}
