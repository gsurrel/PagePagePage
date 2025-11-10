<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PagePagePageCommands\StartCommand;
use PagePagePageBot\TelegramClientInterface;

final class StartCommandTest extends TestCase
{
    public function testExecuteSetsAwaitingTitleAndLogs(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with('fake-token', 123456, $this->stringContains('Welcome to PagePagePage'));

        [$ctx, $logger, $manager, $tmpDir] = createTestContextWithRealSiteManager($client);

        $command = new StartCommand($ctx);
        $command->execute();

        $this->assertContains('Awaiting title set to: true', $logger->getMessagesFor('SiteManager'));

        array_map('unlink', glob("$tmpDir/*"));
        rmdir($tmpDir);
    }
}
