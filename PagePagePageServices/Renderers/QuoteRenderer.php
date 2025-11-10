<?php
declare(strict_types=1);

namespace PagePagePageServices\Renderers;

use PagePagePageDTO\TelegramMessage;
use PagePagePageDTO\RenderedBlock;

final class QuoteRenderer implements MessageRendererInterface
{
    public function canRender(TelegramMessage $msg, array $articleMessages): bool
    {
        return $msg->isForwarded()
            || ($msg->getReplyAuthorNickname() !== null && ($msg->getQuote() || $msg->getReplyTo()));
    }

    public function render(TelegramMessage $msg, array $articleMessages): ?RenderedBlock
    {
        $html = '';
        $cite = $msg->getReplyAuthorNickname();

        $quotedText = $msg->getQuote()['text'] ?? $msg->getReplyTo()?->getText();
        if ($cite && $quotedText) {
            $html .= "<blockquote cite=\"" . htmlspecialchars($cite) . "\">" . htmlspecialchars($quotedText) . "</blockquote>";
        }

        if ($msg->isForwarded()) {
            $forwardedText = $msg->getForwardedText();
            $forwardedFrom = $msg->getForwardedFrom()?->getNickname() ?? 'Unknown';
            $html .= "<blockquote cite=\"" . htmlspecialchars($forwardedFrom) . "\">" . htmlspecialchars($forwardedText ?? '') . "</blockquote>";
        }

        return $html !== '' ? new RenderedBlock($html) : null;
    }
}
