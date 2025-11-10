<?php
declare(strict_types=1);

namespace PagePagePageServices;

final class MemoryLogger implements LoggerInterface
{
    public array $logs = [];

    public function log(string $message, string $context = ''): void
    {
        $this->logs[] = [$context, $message];
    }

    public function getMessagesFor(string $context): array
    {
        return array_map(fn($log) => $log[1], array_filter($this->logs, fn($log) => $log[0] === $context));
    }
}
