<?php
declare(strict_types=1);

namespace PagePagePageServices\Renderers;

use PagePagePageDTO\TelegramMessage;
use PagePagePageDTO\RenderedBlock;

interface MessageRendererInterface
{
    /**
     * Determines if this renderer can handle the given message
     */
    public function canRender(TelegramMessage $msg, array $articleMessages): bool;

    /**
     * Returns rendered HTML block and required headers
     */
    public function render(TelegramMessage $msg, array $articleMessages): ?RenderedBlock;
}
