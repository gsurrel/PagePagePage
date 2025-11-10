<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class RenameArticleCommand implements Command
{
    public function __construct(
        private CommandContext $ctx,
        private int $articleId,
        private int $timestamp
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

        $manager->selectArticle($this->articleId, $this->timestamp);
        $manager->setAwaitingTitle(true);

        $title = $manager->getTitle($this->articleId);
        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "✏️ Current title: \"$title\"\n\nPlease send the new title."
        );
    }

    public function handleTitleInput(string $title): void
    {
        if ($title === '') {
            $this->ctx->messenger->send($this->ctx->chatId, "⚠️ Please send a valid title.");
            return;
        }

        $manager = $this->ctx->siteManager;
        $articleId = $this->articleId;

        if ($manager->hasArticle($articleId)) {
            $manager->renameArticle($articleId, $title);
            $manager->setAwaitingTitle(false);

            $this->ctx->messenger->send(
                $this->ctx->chatId,
                "✅ Title updated to \"$title\"."
            );
        } else {
            $this->ctx->messenger->send($this->ctx->chatId, "❌ Article not found.");
        }
    }
}
