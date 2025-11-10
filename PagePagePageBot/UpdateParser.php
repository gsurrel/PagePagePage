<?php
declare(strict_types=1);

namespace PagePagePageBot;

use PagePagePageDTO\TelegramUpdate;
use PagePagePageDTO\TelegramMessage;

final class UpdateParser
{
    private TelegramUpdate $update;

    public function __construct(array $rawUpdate)
    {
        $this->update = TelegramUpdate::fromArray($rawUpdate);
    }

    public function isCallback(): bool
    {
        return $this->update->hasCallback();
    }

    public function getCallbackData(): ?string
    {
        return $this->update->getCallback()?->getData();
    }

    public function getCallbackMessage(): ?TelegramMessage
    {
        return $this->update->getCallback()?->getMessage();
    }

    public function getCallbackMessageId(): ?int
    {
        return $this->getCallbackMessage()?->getMessageId();
    }

    public function getCallbackTimestamp(): ?int
    {
        return $this->getCallbackMessage()?->getTimestamp();
    }

    public function getMessage(): ?TelegramMessage
    {
        return $this->update->getMessage();
    }

    public function getEditedMessage(): ?TelegramMessage
    {
        return $this->update->getEditedMessage();
    }

    public function getUsername(): ?string
    {
        return $this->update->getCallback()?->getFrom()?->getUsername()
            ?? ($this->getMessage() ?? $this->getEditedMessage())?->getFrom()?->getUsername();
    }

    public function getUserId(): int
    {
        return $this->update->getCallback()?->getFrom()?->getId()
            ?? ($this->getMessage() ?? $this->getEditedMessage())?->getFrom()?->getId();
    }

    public function getChatId(): ?int
    {
        return $this->update->getCallback()?->getMessage()?->getChat()?->getId()
            ?? ($this->getMessage() ?? $this->getEditedMessage())?->getChat()?->getId();
    }

    public function getCommandText(): ?string
    {
        $text = $this->getMessage()?->getText();
        return $text !== null ? strtolower(trim($text)) : null;
    }
}
