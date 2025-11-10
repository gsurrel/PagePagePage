<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class ListArticlesCommand implements Command
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
                "📭 No articles found."
            );
            return;
        }

        $buttons = [];
        foreach ($articles as $id => $article) {
            $title = $article['title'] ?? 'Untitled';
            if ($article['draft']) {
                $title .= " 📝";
            }
            $buttons[] = [['text' => $title, 'callback_data' => "article_menu::$id"]];
        }

        $text = "📄 Select an article to manage:";

        if ($this->ctx->messageId !== null) {
            $this->ctx->messenger->edit($this->ctx->chatId, $this->ctx->messageId, $text, $buttons);
        } else {
            $this->ctx->messenger->sendHtmlWithButtons($this->ctx->chatId, $text, $buttons);
        }
    }
}
