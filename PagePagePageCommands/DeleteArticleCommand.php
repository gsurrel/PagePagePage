<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class DeleteArticleCommand implements Command
{
    public function __construct(
        private CommandContext $ctx,
        private int $articleId,
        private int $messageId
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

        $title = $manager->getTitle($this->articleId);
        $manager->deleteArticle($this->articleId);

        $buttons = [];
        foreach ($manager->getArticles() as $id => $article) {
            $label = $article['title'] ?? 'Untitled';
            if ($article['draft']) {
                $label .= " 📝";
            }
            $buttons[] = [['text' => $label, 'callback_data' => "article_menu::$id"]];
        }

        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "🗑️ Deleted article <b>$title</b>.",
            $buttons
        );
    }
}
