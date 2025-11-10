<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class ArticleMenuCommand implements Command
{
    public function __construct(
        private CommandContext $ctx,
        private int $articleId
    ) {
    }

    public function execute(): void
    {
        $title = $this->ctx->siteManager->getTitle($this->articleId);

        $draft = $this->ctx->siteManager->isDraft($this->articleId);
        $buttons = [
            [['text' => '🔘 Select for editing', 'callback_data' => "select_article::{$this->articleId}"]],
            [['text' => '✏️ Edit title', 'callback_data' => "edit_title::{$this->articleId}"]],
            [
                $draft
                ? ['text' => '📤 Publish article', 'callback_data' => "unset_draft::{$this->articleId}"]
                : ['text' => '📝 Mark as draft', 'callback_data' => "set_draft::{$this->articleId}"]
            ],
            [['text' => '🗑️ Delete article', 'callback_data' => "delete_article::{$this->articleId}"]],
            [['text' => '⬅️ Back to Articles', 'callback_data' => 'list_articles']]
        ];

        $this->ctx->messenger->edit(
            $this->ctx->chatId,
            $this->ctx->messageId,
            "📌 What would you like to do with \"$title\"?",
            $buttons
        );
    }
}
