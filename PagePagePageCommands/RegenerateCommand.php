<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;
use PagePagePageServices\HtmlRenderer;
use PagePagePageUtils\Utils;

final class RegenerateCommand implements Command
{
    public function __construct(private CommandContext $ctx)
    {
    }

    public function execute(): void
    {
        if ($this->ctx->siteManager->isOffline()) {
            $this->ctx->messenger->edit(
                $this->ctx->chatId,
                $this->ctx->messageId,
                "ℹ️ Cannot regenerate an offline site."
            );
            return;
        }

        $siteName = $this->ctx->siteManager->getSiteName();
        if ($siteName === null) {
            $this->ctx->messenger->edit(
                $this->ctx->chatId,
                $this->ctx->messageId,
                "ℹ️ The site needs a name before publishing."
            );
            return;
        }

        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "🔄 Regenerating your site..."
        );

        $articles = array_filter(
            $this->ctx->siteManager->getArticles(),
            fn(array $a) => !$a['draft']
        );

        $renderer = new HtmlRenderer(
            $this->ctx->siteManager->getUserDir(),
            $siteName,
            $this->ctx->userId,
            $this->ctx->config
        );

        $renderer->renderSite($articles);

        $siteSlug = Utils::slugify($this->ctx->siteManager->getSiteName());

        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "✅ Site successfully regenerated: https://{$this->ctx->config->baseAddress}/site/{$siteSlug}/"
        );
    }
}
