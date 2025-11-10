<?php
declare(strict_types=1);

namespace PagePagePageBot;

use PagePagePageDTO\TelegramMessage;
use PagePagePageCommands\ArticleMenuCommand;
use PagePagePageCommands\CreateArticleCommand;
use PagePagePageCommands\DeleteArticleCommand;
use PagePagePageCommands\DeleteSiteCommand;
use PagePagePageCommands\DownloadMyDataCommand;
use PagePagePageCommands\EditTitleCommand;
use PagePagePageCommands\ListArticlesCommand;
use PagePagePageCommands\RegenerateCommand;
use PagePagePageCommands\SelectArticleCommand;
use PagePagePageCommands\ShowMenuCommand;
use PagePagePageCommands\StartCommand;
use PagePagePageCommands\SetDraftCommand;
use PagePagePageCommands\UnsetDraftCommand;
use PagePagePageServices\CommandContext;

final class CommandRouter
{
    public function __construct(private CommandContext $ctx)
    {
    }

    public function route(TelegramMessage $message): void
    {
        $text = strtolower(trim($message->getText() ?? ''));

        match (true) {
            str_starts_with($text, '/start') =>
            (new StartCommand($this->ctx))->execute(),

            str_starts_with($text, '/menu') =>
            (new ShowMenuCommand($this->ctx))->execute(),

            default =>
            $this->ctx->messenger->send($this->ctx->chatId, "❓ Unknown command: $text"),
        };
    }

    public function handleCallback(
        string $action,
        int $messageId,
        int $timestamp
    ): void {
        $ctx = $this->ctx;

        $patterns = [
            'select_article::' => fn($id) => (new SelectArticleCommand($ctx))->handleSelection($id, $timestamp),
            'article_menu::' => fn($id) => (new ArticleMenuCommand($ctx, $id))->execute(),
            'set_draft::' => fn($id) => (new SetDraftCommand($ctx, $id))->execute(),
            'unset_draft::' => fn($id) => (new UnsetDraftCommand($ctx, $id))->execute(),
            'edit_title::' => fn($id) => (new EditTitleCommand($ctx, $id, $timestamp))->execute(),
            'delete_article::' => fn($id) => (new DeleteArticleCommand($ctx, $id, $messageId))->execute(),
        ];

        foreach ($patterns as $prefix => $handler) {
            if (str_starts_with($action, $prefix)) {
                $id = (int) explode('::', $action, 2)[1]; // TODO: safety
                $handler($id);
                return;
            }
        }

        $staticActions = [
            'regenerate' => fn() => (new RegenerateCommand($ctx))->execute(),
            'list_articles' => fn() => (new ListArticlesCommand($ctx))->execute(),
            'create_article_prompt' => fn() => (new CreateArticleCommand($ctx))->execute(),
            'download_my_data' => fn() => (new DownloadMyDataCommand($ctx))->execute(),
            'delete_site' => fn() => (new DeleteSiteCommand($ctx))->execute(),
            'confirm_delete_site' => fn() => (new DeleteSiteCommand($ctx))->confirm(),
            'show_menu' => fn() => (new ShowMenuCommand($ctx))->execute(),
        ];

        if (isset($staticActions[$action])) {
            $staticActions[$action]();
        } else {
            $ctx->messenger->send($ctx->chatId, "❓ Unknown action: $action");
        }
    }
}
