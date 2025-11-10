<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class SelectArticleCommand implements Command
{
    public function __construct(private CommandContext $ctx)
    {
    }

    public function execute(): void
    {
        $articles = $this->ctx->siteManager->getArticles();

        if (empty($articles)) {
            $this->ctx->messenger->edit(
                $this->ctx->chatId,
                $this->ctx->messageId,
                "📭 No articles available to select."
            );
            return;
        }

        $buttons = [];
        foreach ($articles as $id => $article) {
            $title = $article['title'] ?? 'Untitled';
            if ($article['draft']) {
                $title .= " 📝";
            }
            $buttons[] = [['text' => $title, 'callback_data' => "select_article::$id"]];
        }

        $text = "📚 Select an article to continue writing:";

        if ($this->ctx->messageId !== null) {
            $this->ctx->messenger->edit($this->ctx->chatId, $this->ctx->messageId, $text, $buttons);
        } else {
            $this->ctx->messenger->sendHtmlWithButtons($this->ctx->chatId, $text, $buttons);
        }
    }

    public function handleSelection(int $articleId, int $timestamp): void
    {
        $this->ctx->siteManager->selectArticle($articleId, $timestamp);

        $title = $this->ctx->siteManager->getTitle($articleId);
        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "✅ Selected article: $title"
        );

        // 🧭 Immediately update the inline menu
        (new ShowMenuCommand($this->ctx))->execute();
    }
}
