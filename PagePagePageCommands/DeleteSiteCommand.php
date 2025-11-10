<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class DeleteSiteCommand implements Command
{
    public function __construct(private CommandContext $ctx)
    {
    }

    public function execute(): void
    {
        // Instead of deleting right away, show confirmation menu
        $buttons = [
            [
                ['text' => '🔥 Yes, delete my site', 'callback_data' => 'confirm_delete_site'],
            ],
            [
                ['text' => '⬅️ Cancel', 'callback_data' => 'show_menu'],
            ],
        ];

        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "⚠️ Are you sure you want to permanently delete your site? This cannot be undone.",
            $buttons
        );
    }

    public function confirm(): void
    {
        $dir = $this->ctx->siteManager->getUserDir();

        if (!is_dir($dir)) {
            $this->ctx->messenger->edit(
                $this->ctx->chatId,
                $this->ctx->messageId,
                "⚠️ Site folder not found, nothing to delete."
            );
            return;
        }

        // Calculate UTC midnight in 90 days
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $midnight90 = $now->modify('+90 days')->setTime(0, 0);
        $utcDateAtMidnightIn90Days = $midnight90->format('U');

        // Current UTC time for uniqueness
        $utcTime = $now->format('H-i-s');

        // Assuming CommandContext exposes userId
        $userId = $this->ctx->userId;

        // Build archive path exactly as required
        $archiveDir = realpath(__DIR__ . '/../../tmp/')
            . '/' . $utcDateAtMidnightIn90Days
            . '/' . $utcTime . '-' . $userId;

        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0777, true);
        }

        // Move the site directory into archive
        @rename($dir, $archiveDir);

        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "🔥 Site deleted."
        );
    }

    private function rrmdir(string $dir): void
    {
        $items = glob("$dir/*", GLOB_MARK);
        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->rrmdir($item);
            } else {
                unlink($item);
            }
        }
        rmdir($dir);
    }
}
