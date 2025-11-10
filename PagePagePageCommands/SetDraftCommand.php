<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class SetDraftCommand implements Command
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

        if ($manager->isDraft($this->articleId)) {
            $this->ctx->messenger->edit(
                $this->ctx->chatId,
                $this->ctx->messageId,
                "ℹ️ Article is already marked as draft."
            );
            return;
        }

        $manager->setDraft($this->articleId, true);

        // Refresh menu
        (new ArticleMenuCommand($this->ctx, $this->articleId))->execute();
    }
}
