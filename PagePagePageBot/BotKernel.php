<?php
declare(strict_types=1);

namespace PagePagePageBot;

use PagePagePageCommands\SetSiteNameCommand;
use PagePagePageServices\Config;
use PagePagePageServices\ServiceProvider;
use PagePagePageServices\CommandContext;
use PagePagePageCommands\CreateArticleCommand;
use PagePagePageCommands\RenameArticleCommand;

final class BotKernel
{
    private ServiceProvider $provider;

    public function __construct(private Config $config)
    {
        $this->provider = new ServiceProvider($config);
    }

    public function handleWebhook(): void
    {
        $input = $this->getRawInput();
        //file_put_contents(filename: 'debug-update.json', data: $input);

        // Ensure the request is legit
        $token = getallheaders()['X-Telegram-Bot-Api-Secret-Token'] ?? '';
        if ($token !== $this->config->webhookToken) {
            http_response_code(403);
            exit('Forbidden');
        }

        $parser = new UpdateParser(json_decode($input, true));

        $userId = $parser->getUserId();
        $username = $parser->getUsername();
        $chatId = $parser->getChatId();

        if (!$userId || !$chatId) {
            throw new \RuntimeException("Missing userId or chatId in update");
        }

        $userDir = __DIR__ . "/../users/$userId";
        if (!is_dir($userDir)) {
            mkdir($userDir, 0775, true);
        }

        $siteManager = $this->provider->getSiteManager($userDir, $username);
        $messenger = $this->provider->getMessenger();
        $messageId = $parser->getCallbackMessageId();

        $logger = $this->provider->getLogger($userDir);
        $logger->log("handleWebhook: $input", "BotKernel");
        $logger->log("handleWebhook: userId=$userId userId=$username chatId=$chatId messageId=$messageId", "BotKernel");

        $ctx = new CommandContext(
            $this->config,
            $siteManager,
            $logger,
            $messenger,
            $chatId,
            $userId,
            $messageId,
        );

        $router = $this->provider->getCommandRouter($ctx);

        if ($parser->isCallback()) {
            $this->handleCallback($parser, $router, $ctx);
        } else {
            $this->handleMessage($parser, $router, $ctx);
        }
    }

    private function getRawInput(): string
    {
        return file_get_contents("php://input") ?: '';
    }

    private function handleCallback(
        UpdateParser $parser,
        CommandRouter $router,
        CommandContext $ctx,
    ): void {
        $logger = $ctx->logger;
        $action = $parser->getCallbackData();

        $logger->log("Detected callback: action=$action", "BotKernel");

        $router->handleCallback(
            $action,
            $ctx->chatId,
            $parser->getCallbackTimestamp()
        );

        // $logger->log("Finished callback for $action", "BotKernel");
    }

    private function handleMessage(
        UpdateParser $parser,
        CommandRouter $router,
        CommandContext $ctx
    ): void {
        $message = $parser->getMessage() ?? $parser->getEditedMessage();
        $isEdited = $parser->getMessage() === null;
        if (!$message) {
            throw new \RuntimeException("Failed to extract message from update");
        }

        $cmdText = $parser->getCommandText();
        if ($cmdText !== null && str_starts_with($cmdText, '/')) {
            $router->route($message);
            return;
        }

        $siteManager = $ctx->siteManager;
        $timestamp = $message->getTimestamp();

        if ($siteManager->isAwaitingSitename()) {
            $siteName = trim($message->getText() ?? '');

            $ctx->logger->log("Awaiting for sitename ($siteName)", "BotKernel");

            (new SetSiteNameCommand($ctx, $siteName))->execute();

            return;
        }

        if ($siteManager->isAwaitingTitle()) {
            $title = trim($message->getText() ?? '');
            $selectedId = $siteManager->getSelectedArticleId($timestamp);

            $ctx->logger->log("Awaiting for title ($title) for article id=$selectedId", "BotKernel");
            if ($selectedId && $siteManager->hasArticle($selectedId)) {
                (new RenameArticleCommand($ctx, $selectedId, $timestamp))->handleTitleInput($title);
            } else {
                (new CreateArticleCommand($ctx))->handleTitleInput($title, $timestamp);
            }

            return;
        }

        $selectedId = $siteManager->getSelectedArticleId($timestamp);
        if (!$selectedId) {
            $ctx->messenger->send($ctx->chatId, "⚠️ No article selected. Use the /menu to create or select an article.");
        } else {
            $result = !$isEdited ? $siteManager->appendMessage($message) : $siteManager->replaceMessage($message);
            $ctx->messenger->send(
                $ctx->chatId,
                $result === null ? "📎 Message added to current article." : $result
            );
        }
    }
}
