<?php
declare(strict_types=1);

namespace PagePagePageServices;

final class FileSystemLogger implements LoggerInterface
{
    public function __construct(private string $userDir)
    {
    }

    public function log(string $message, string $context = ''): void
    {
        $logsDir = "$this->userDir/logs";

        // Ensure logs directory exists
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0777, true);
        }

        // Current UTC date at midnight
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTime(0, 0)
            ->format('Y-m-d');

        // Log filename
        $logFile = "$logsDir/$date.log";

        // Build log line
        $timestamp = gmdate('Y-m-d H:i:s');
        $prefix = $context !== '' ? "[$context] " : '';
        $line = "[$timestamp] {$prefix}{$message}\n";

        // Append to log file
        file_put_contents($logFile, $line, FILE_APPEND);

        // Cleanup: remove logs older than 14 days
        $files = glob("$logsDir/*.log");
        $threshold = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-14 days')
            ->setTime(0, 0);

        foreach ($files as $file) {
            $basename = basename($file, '.log');
            $fileDate = \DateTimeImmutable::createFromFormat('Y-m-d', $basename, new \DateTimeZone('UTC'));
            if ($fileDate instanceof \DateTimeImmutable && $fileDate < $threshold) {
                @unlink($file);
            }
        }
    }
}
