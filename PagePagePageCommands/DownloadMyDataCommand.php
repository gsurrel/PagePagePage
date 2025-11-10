<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

final class DownloadMyDataCommand implements Command
{
    public function __construct(private CommandContext $ctx)
    {
    }

    public function execute(): void
    {
        $dir = $this->ctx->siteManager->getUserDir();

        // Compute date (midnight UTC + 30 days)
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTime(0, 0)
            ->modify('+30 days')
            ->format('U');

        // Compute hash
        $hash = hash('sha256', $this->ctx->config->salt . $this->ctx->userId);

        // Build target path
        $targetDir = __DIR__ . "../../../tmp/$date";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $zipPath = "$targetDir/$hash.zip";

        // Create ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create archive at $zipPath");
        }

        // Helper to add files/dirs
        $addPath = function (string $path, string $localName = '') use ($zip) {
            if (is_file($path)) {
                $zip->addFile($path, $localName ?: basename($path));
            } elseif (is_dir($path)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($files as $file) {
                    $local = ($localName ? $localName . '/' : '') .
                        substr($file->getPathname(), strlen($path) + 1);
                    if ($file->isDir()) {
                        $zip->addEmptyDir($local);
                    } else {
                        $zip->addFile($file->getPathname(), $local);
                    }
                }
            }
        };

        // Add required elements
        $addPath("$dir/site.sqlite", "site.sqlite");
        $addPath("$dir/site", "site");
        $addPath("$dir/assets", "assets");

        $zip->close();

        // Add required elements
        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "Your data is available to download at https://{$this->ctx->config->baseAddress}/tmp/$date/$hash.zip for 30 days."
        );

    }
}
