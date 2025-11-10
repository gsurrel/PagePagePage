<?php
declare(strict_types=1);

namespace PagePagePageDTO;

final class TelegramChat
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

    public function getId(): int
    {
        return $this->raw['id'];
    }

    public function getType(): string
    {
        return $this->raw['type'];
    }

    public function getUsername(): ?string
    {
        return $this->raw['username'] ?? null;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }
}
