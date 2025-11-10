<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class CreateArticleCommand implements Command
{
    public function __construct(private CommandContext $ctx)
    {
    }

    public function execute(): void
    {
        $this->ctx->siteManager->clearSelection();
        $this->ctx->siteManager->setAwaitingTitle(true);

        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "📝 Please send the title of your new article."
        );
    }

    public function handleTitleInput(string $title, int $timestamp): void
    {
        if ($title === '') {
            $this->ctx->messenger->send($this->ctx->chatId, "⚠️ Please send a valid title.");
            return;
        }

        $manager = $this->ctx->siteManager;
        $articleId = $manager->createArticle($title, $timestamp);
        $manager->setAwaitingTitle(false);

        $this->ctx->messenger->send(
            $this->ctx->chatId,
            "✅ Article \"$title\" created.\n\nSend messages to add them to the article."
        );
    }
}
