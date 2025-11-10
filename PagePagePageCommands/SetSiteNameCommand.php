<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;
use PagePagePageUtils\Utils;

final class SetSiteNameCommand implements Command
{
    public function __construct(
        private CommandContext $ctx,
        private string $siteName,
    ) {
    }

    public function execute(): void
    {
        $manager = $this->ctx->siteManager;

        $len = strlen($this->siteName);

        if ($len < 6) {
            $this->ctx->messenger->send(
                $this->ctx->chatId,
                "Your site name is too short. It must be at least 6 characters."
            );
            return;
        }

        if ($len > 64) {
            $this->ctx->messenger->send(
                $this->ctx->chatId,
                "Your site name is too long. It must be no more than 64 characters."
            );
            return;
        }

        if (!$this->siteNameAvailable()) {
            $this->ctx->messenger->send(
                $this->ctx->chatId,
                "You site name <b>{$this->siteName}</b> you chose is unavailable. Choose another one."
            );
            return;
        }

        $existingSiteName = $manager->getSiteName();
        if ($existingSiteName !== null) {
            $this->ctx->messenger->send(
                $this->ctx->chatId,
                "You site already has a name: <b>{$existingSiteName}</b>. The name cannot be changed."
            );
            $manager->setAwaitingSiteName(false);
            return;
        }

        $manager->setSiteName($this->siteName);
        $manager->setAwaitingSiteName(false);

        // Create symlink site/$siteNameSlug → $userDir/site
        $userDir = $this->ctx->siteManager->getUserDir();
        $siteRoot = rtrim($userDir, '/') . '/site';
        $this->ensureSiteNameSymlink($siteRoot);

        $this->ctx->messenger->send(
            $this->ctx->chatId,
            "Site name set to <b>{$this->siteName}</b>. Now, use the /menu to create an article."
        );
    }

    private function siteNameAvailable(): bool
    {
        $siteSlug = Utils::slugify($this->siteName);
        $link = "site/{$siteSlug}";

        return !file_exists($link);
    }

    private function ensureSiteNameSymlink(string $siteRoot): void
    {
        $siteSlug = Utils::slugify($this->siteName);
        $link = "../site/{$siteSlug}";

        // Ensure parent exists
        if (!is_dir('../site')) {
            mkdir('../site', 0777, true);
        }

        // Remove pre-existing file or link
        if (is_link($link) || file_exists($link)) {
            unlink($link);
        }

        // Base = directory that will contain the symlink
        $baseDirAbs = realpath(dirname($link)) ?: dirname($link);

        // Target = the real "site" directory we want to point to
        $targetAbs = rtrim(realpath($siteRoot) ?: $siteRoot, "/\\");

        // Compute a relative target from baseDirAbs -> targetAbs
        $relativeTarget = $this->relativePath($targetAbs, $baseDirAbs);

        // Create the symlink
        @symlink($relativeTarget, $link);
    }

    private function relativePath(string $from, string $to): string
    {
        $from = explode('/', rtrim($from, '/'));
        $to = explode('/', rtrim($to, '/'));
        while (count($from) && count($to) && ($from[0] === $to[0])) {
            array_shift($from);
            array_shift($to);
        }
        return str_repeat('../', count($to)) . implode('/', $from);
    }
}
