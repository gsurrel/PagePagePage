<?php
declare(strict_types=1);

namespace PagePagePageCommands;

use PagePagePageServices\CommandContext;

final class StartCommand implements Command
{
    public function __construct(private CommandContext $ctx)
    {
    }

    public function execute(): void
    {
        $text = <<<TEXT
        👋 Welcome to PagePagePage
        
        With this bot, you can publish your own blog, without any complex setup, app, nor new account. You already have everything!

        The service is currently in open beta! Use this service at your own risk, there is no reliability guarantee yet!

        By using the service, you accept the <a href="https://www.pagepagepage.org/terms.html">Terms of use</a> and the <a href="https://www.pagepagepage.org/privacy.html">privacy policy</a>.
        
        Use the interactive /menu to manage your site.
        
        What should be the name of your site?
        TEXT;

        $this->ctx->siteManager->setAwaitingSiteName(true);
        $this->ctx->messenger->send($this->ctx->chatId, $text);
    }
}
