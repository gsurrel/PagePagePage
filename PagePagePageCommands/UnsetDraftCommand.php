<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class UnsetDraftCommand implements Command
{
    public function __construct(
        private CommandContext $ctx,
        private int $articleId
    ) {
    }

    public function execute(): void
    {
        $manager = $this->ctx->siteManager;

        if (!$manager->hasArticle($this->articleId)) {
            $this->ctx->messenger->edit(
                $this->ctx->chatId,
                $this->ctx->messageId,
                "❌ Article not found."
            );
            return;
        }

        if (!$manager->isDraft($this->articleId)) {
            $this->ctx->messenger->edit(
                $this->ctx->chatId,
                $this->ctx->messageId,
                "ℹ️ Article is already published."
            );
            return;
        }

        $manager->setDraft($this->articleId, false);

        // Refresh menu
        (new ArticleMenuCommand($this->ctx, $this->articleId))->execute();
    }
}
