<?php
declare(strict_types=1);

namespace PagePagePageServices\Renderers;

use PagePagePageDTO\TelegramMessage;
use PagePagePageDTO\RenderedBlock;
use PagePagePageUtils\Utils;

final class TextRenderer implements MessageRendererInterface
{
    public function canRender(TelegramMessage $msg, array $articleMessages): bool
    {
        return $msg->getText() !== null;
    }

    public function render(TelegramMessage $msg, array $articleMessages): ?RenderedBlock
    {
        $html = "<section>" . Utils::formatMessageText($msg->getText() ?? '', $msg->getEntities()) . "</section>";
        return new RenderedBlock($html);
    }
}
