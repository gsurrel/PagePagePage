<?php
declare(strict_types=1);

namespace PagePagePageServices\Renderers;

use PagePagePageDTO\TelegramMessage;
use PagePagePageDTO\RenderedBlock;
use PagePagePageUtils\Utils;

final class MediaGroupRenderer implements MessageRendererInterface
{
    private array $renderedGroups = [];

    public function canRender(TelegramMessage $msg, array $articleMessages): bool
    {
        $groupId = $msg->getMediaGroupId();
        return $groupId !== null && !isset($this->renderedGroups[$groupId]);
    }

    public function render(TelegramMessage $msg, array $articleMessages): ?RenderedBlock
    {
        $groupId = $msg->getMediaGroupId();
        $groupMsgs = array_values(array_filter(
            $articleMessages,
            fn($m): bool =>
            $m instanceof TelegramMessage &&
            $m->getMediaGroupId() === $groupId &&
            !empty($m->getRaw()['photo'] ?? [])
        ));

        $count = count($groupMsgs);
        if ($count === 0)
            return null;

        $assets = [];
        $imagesHtml = '';
        foreach ($groupMsgs as $index => $m) {
            $raw = $m->getRaw();
            $photo = end($raw['photo']);
            $fileId = $photo['file_id'] ?? '';
            if (!is_string($fileId))
                continue;

            $filename = "$fileId.jpg";
            $i = $index + 1;

            // Build dots markup
            $dots = '';
            for ($j = 1; $j <= $count; $j++) {
                $dots .= ($j === $i) ? '<span>&bull;</span>' : '&bull;';
            }

            $imagesHtml .= "<div><img src='$filename'><div>$dots</div></div>";
            $assets[] = ['file_id' => $fileId, 'filename' => $filename];
        }

        // First non-empty caption wins
        $caption = '';
        foreach ($groupMsgs as $m) {
            $caption = $m->getCaption();
            if (!empty($caption)) {
                $caption = Utils::formatMessageText($caption, $m->getCaptionEntities());
                break;
            }
        }

        $figcaption = $caption !== '' ? "<figcaption>$caption</figcaption>" : '';
        $this->renderedGroups[$groupId] = true;

        $html = "<figure><div class='carousel'>$imagesHtml</div>$figcaption</figure>";

        return new RenderedBlock(html: $html, assets: $assets);
    }
}
