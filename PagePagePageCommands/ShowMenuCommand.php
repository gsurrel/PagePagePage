<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class ShowMenuCommand implements Command
{
    public function __construct(private CommandContext $ctx)
    {
    }

    public function execute(): void
    {
        $buttons = [
            [['text' => '📓 Generate site', 'callback_data' => 'regenerate']],
            [
                ['text' => '📑 Edit Articles', 'callback_data' => 'list_articles'],
                ['text' => '🆕 New Article', 'callback_data' => 'create_article_prompt'],
            ],
            [
                ['text' => '📚 Download all', 'callback_data' => 'download_my_data'],
                ['text' => '🔥 Delete Site', 'callback_data' => 'delete_site'],
            ]
        ];

        $text = "🧭 What would you like to do?";

        if ($this->ctx->messageId !== null) {
            $this->ctx->messenger->edit($this->ctx->chatId, $this->ctx->messageId, $text, $buttons);
        } else {
            $this->ctx->messenger->sendHtmlWithButtons($this->ctx->chatId, $text, $buttons);
        }
    }
}
