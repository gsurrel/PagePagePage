<?php
declare(strict_types=1);

namespace PagePagePageCommands;

final class CommandBus
{
    public function dispatch(Command $command): void
    {
        $command->execute();
    }
}
