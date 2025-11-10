<?php
declare(strict_types=1);

namespace PagePagePageDTO;

final class TelegramUser
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

    public function getFirstName(): string
    {
        return $this->raw['first_name'];
    }

    public function getLastName(): ?string
    {
        return $this->raw['last_name'] ?? null;
    }

    public function getUsername(): ?string
    {
        return $this->raw['username'] ?? null;
    }

    public function getNickname(): string
    {
        return $this->getUsername() ? "@{$this->getUsername()}" : $this->getFirstName();
    }

    public function getRaw(): array
    {
        return $this->raw;
    }
}
