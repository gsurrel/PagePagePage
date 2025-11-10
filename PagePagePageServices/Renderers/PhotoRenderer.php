<?php
declare(strict_types=1);

namespace PagePagePageServices\Renderers;

use PagePagePageDTO\TelegramMessage;
use PagePagePageDTO\RenderedBlock;
use PagePagePageUtils\Utils;

final class PhotoRenderer implements MessageRendererInterface
{
    public function canRender(TelegramMessage $msg, array $articleMessages): bool
    {
        return $msg->getPhoto() !== null && $msg->getPhoto() !== [] && $msg->getMediaGroupId() === null;
    }

    public function render(TelegramMessage $msg, array $articleMessages): ?RenderedBlock
    {
        $photos = $msg->getPhoto();
        if (!is_array($photos) || $photos === []) {
            return null;
        }

        $photo = end($photos);
        $fileId = $photo['file_id'] ?? '';
        if (!is_string($fileId) || $fileId === '') {
            return null;
        }

        $filename = "$fileId.jpg";
        $html = "<figure><img src='$filename' style='max-width: 100%; height: auto;'>";

        $caption = $msg->getCaption();
        if (!empty($caption) && is_string($caption)) {
            $html .= "<figcaption>" . Utils::formatMessageText($caption, $msg->getCaptionEntities()) . "</figcaption>";
        }
        $html .= "</figure>";

        return new RenderedBlock(
            html: $html,
            assets: [
                [
                    'file_id' => $fileId,
                    'filename' => $filename
                ]
            ]
        );
    }
}
