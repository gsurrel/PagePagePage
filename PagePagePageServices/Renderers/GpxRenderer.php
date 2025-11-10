<?php
declare(strict_types=1);

namespace PagePagePageServices\Renderers;

use PagePagePageDTO\TelegramMessage;
use PagePagePageDTO\RenderedBlock;

final class GpxRenderer implements MessageRendererInterface
{
    public function canRender(TelegramMessage $msg, array $articleMessages): bool
    {
        $filename = $msg->getDocumentFileName();
        return is_string($filename) && str_ends_with($filename, '.gpx');
    }

    public function render(TelegramMessage $msg, array $articleMessages): ?RenderedBlock
    {
        $fileName = $msg->getDocumentFileName();
        $fileId = $msg->getDocumentFileId();

        if (!is_string($fileName) || !is_string($fileId)) {
            return null;
        }

        $mapId = 'map-' . htmlspecialchars((string) ($msg->getMessageId() ?? uniqid()));
        $caption = $msg->getCaption() ?? '';
        $gpxPath = basename($fileName);

        $styles = ["https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css"];
        $scripts = [
            "https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js",
            "https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/2.1.2/gpx.min.js",
            <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
    var map = L.map('$mapId').setView([0, 0], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18, attribution: '© OpenStreetMap'
    }).addTo(map);
    new L.GPX('$fileId.gpx', {
        async: true,
        marker_options: { startIconUrl: null, endIconUrl: null, shadowUrl: null }
    }).on('loaded', function(e) {
        map.fitBounds(e.target.getBounds());
    }).addTo(map);
});
</script>
JS
        ];

        $html = "<figure><div id='$mapId' style='height: 400px;'>";
        if (!empty($caption)) {
            $html .= "<figcaption>" . htmlspecialchars($caption) . "</figcaption>";
        }
        $html .= "</div></figure>";

        return new RenderedBlock(
            html: $html,
            styles: $styles,
            scripts: $scripts,
            assets: [
                [
                    'file_id' => $fileId,
                    'filename' => basename($fileName)
                ]
            ]
        );
    }
}
