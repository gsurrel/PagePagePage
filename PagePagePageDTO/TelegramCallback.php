<?php
declare(strict_types=1);

namespace PagePagePageDTO;

final class TelegramCallback
{
    private array $raw;

    public function __construct(array $raw)
    {
        $this->raw = $raw;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getData(): string
    {
        return $this->raw['data'];
    }

    public function getMessage(): TelegramMessage
    {
        return TelegramMessage::fromArray($this->raw['message']);
    }

    public function getFrom(): TelegramUser
    {
        return TelegramUser::fromArray($this->raw['from']);
    }

    public function getRaw(): array
    {
        return $this->raw;
    }
}
