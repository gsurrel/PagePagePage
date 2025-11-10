<?php
declare(strict_types=1);

namespace PagePagePageServices;

interface LoggerInterface
{
    public function log(string $message, string $context = ''): void;
}
