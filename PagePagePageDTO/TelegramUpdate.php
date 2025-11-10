<?php
declare(strict_types=1);

namespace PagePagePageDTO;

final class TelegramUpdate
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

    public function hasMessage(): bool
    {
        return isset($this->raw['message']);
    }

    public function hasEditedMessage(): bool
    {
        return isset($this->raw['edited_message']);
    }

    public function hasCallback(): bool
    {
        return isset($this->raw['callback_query']);
    }

    public function getMessage(): ?TelegramMessage
    {
        return $this->hasMessage() ? TelegramMessage::fromArray($this->raw['message']) : null;
    }

    public function getEditedMessage(): ?TelegramMessage
    {
        return $this->hasEditedMessage() ? TelegramMessage::fromArray($this->raw['edited_message']) : null;
    }

    public function getCallback(): ?TelegramCallback
    {
        return $this->hasCallback() ? TelegramCallback::fromArray($this->raw['callback_query']) : null;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }
}
