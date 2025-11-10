<?php
declare(strict_types=1);

namespace PagePagePageDTO;

final class TelegramMessage
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

    public function getMessageId(): int
    {
        return $this->raw['message_id'];
    }

    public function getDate(): int
    {
        return $this->raw['date'];
    }

    public function getEditDate(): ?int
    {
        return $this->raw['edit_date'] ?? null;
    }

    public function getTimestamp(): int
    {
        return $this->getEditDate() ?? $this->getDate();
    }

    public function getText(): ?string
    {
        return $this->raw['text'] ?? null;
    }

    public function getForwardedText(): ?string
    {
        return $this->getText();
    }

    public function getEntities(): array
    {
        return $this->raw['entities'] ?? [];
    }

    public function getQuote(): ?array
    {
        return $this->raw['quote'] ?? null;
    }

    public function getReplyTo(): ?TelegramMessage
    {
        return isset($this->raw['reply_to_message'])
            ? new TelegramMessage($this->raw['reply_to_message'])
            : null;
    }

    public function getForwardedFrom(): ?TelegramUser
    {
        return isset($this->raw['forward_from']) ? TelegramUser::fromArray($this->raw['forward_from']) : null;
    }

    public function getReplyAuthorNickname(): ?string
    {
        $reply = $this->getReplyTo();
        return $reply?->getFrom()?->getNickname();
    }

    public function getFrom(): ?TelegramUser
    {
        return isset($this->raw['from']) ? TelegramUser::fromArray($this->raw['from']) : null;
    }

    public function getChat(): ?TelegramChat
    {
        return isset($this->raw['chat']) ? TelegramChat::fromArray($this->raw['chat']) : null;
    }

    public function getDocumentFileName(): ?string
    {
        return $this->raw['document']['file_name'] ?? null;
    }

    public function getDocumentFileId(): ?string
    {
        return $this->raw['document']['file_id'] ?? null;
    }

    public function getPhoto(): ?array
    {
        return $this->raw['photo'] ?? null;
    }

    public function getCaption(): ?string
    {
        return $this->raw['caption'] ?? null;
    }

    public function getCaptionEntities(): array
    {
        return $this->raw['caption_entities'] ?? [];
    }

    public function getMediaGroupId(): ?string
    {
        return $this->raw['media_group_id'] ?? null;
    }

    public function isForwarded(): bool
    {
        return isset($this->raw['forward_from']);
    }

    public function getRaw(): array
    {
        return $this->raw;
    }
}
