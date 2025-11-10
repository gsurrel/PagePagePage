<?php
declare(strict_types=1);

namespace PagePagePageServices;

use PagePagePageUtils\Utils;
use PagePagePageDTO\TelegramMessage;
use PagePagePageServices\Renderers\QuoteRenderer;
use PagePagePageServices\Renderers\TextRenderer;
use PagePagePageServices\Renderers\PhotoRenderer;
use PagePagePageServices\Renderers\MediaGroupRenderer;
use PagePagePageServices\Renderers\GpxRenderer;

final class HtmlRenderer
{
    private array $renderers;

    public function __construct(
        private string $userDir,
        private string $siteName,
        private int $userid,
        private Config $config
    ) {
        $this->renderers = [
            new MediaGroupRenderer(),
            new GpxRenderer(),
            new PhotoRenderer(),
            new QuoteRenderer(),
            new TextRenderer(),
        ];
    }

    /**
     * @param array<int, array{
     *     title: string,
     *     titles?: string[],
     *     last_edit: int,
     *     draft: bool,
     *     messages: TelegramMessage[]
     * }> $articles
     */
    public function renderSite(array $articles): void
    {
        $siteRoot = rtrim($this->userDir, '/') . '/site';
        $assetsRoot = rtrim($this->userDir, '/') . '/assets';

        $this->ensureDir($assetsRoot);
        $this->ensureDir($siteRoot);

        // Clean site folder except assets
        $this->cleanSiteFolder($siteRoot);

        // Ensure style.css symlink in {$userDir}/site
        $this->ensureStyleSymlink($siteRoot);

        $indexList = [];
        $renderedArticles = [];

        foreach ($articles as $id => $article) {
            // Timestamp-based folder name: yyyy-mm-dd hh.mm.ss
            $ts = (int) $id;
            $articleFolderName = gmdate('Y-m-d..h.i.s', $ts);
            $articleDir = "{$siteRoot}/{$articleFolderName}";
            $this->ensureDir($articleDir);

            $styles = [];
            $scripts = [];
            $assetRewrites = [];

            // Article body only
            $articleContent = "";

            foreach ($article['messages'] as $msg) {
                if (!$msg instanceof TelegramMessage) {
                    continue;
                }

                foreach ($this->renderers as $renderer) {
                    if ($renderer->canRender($msg, $article['messages'])) {
                        $block = $renderer->render($msg, $article['messages']);
                        if ($block !== null) {
                            $articleContent .= $block->html;

                            foreach ($block->styles as $s) {
                                $styles[$s] = true;
                            }
                            foreach ($block->scripts as $s) {
                                $scripts[$s] = true;
                            }

                            // Resolve and download assets; create per-article symlink and rewrite map
                            foreach ($block->assets as $asset) {
                                // Expect at least file_id and filename (renderer contract)
                                $resolved = $this->resolveAndDownloadAsset($asset, $assetsRoot);
                                if ($resolved === null) {
                                    Utils::debug("ERROR: Failed to resolve asset entry", $this->userDir);
                                    continue;
                                }

                                // Create relative symlink in the article folder pointing to ../../assets/<file_id>.<ext>
                                $targetRelative = "../../assets/{$resolved['canonical']}";
                                $linkPath = "{$articleDir}/{$resolved['canonical']}";
                                $this->ensureSymlinkOrCopy($targetRelative, $linkPath, $articleDir);

                                // Record rewrite for original filename -> canonical filename
                                if (!empty($resolved['original'])) {
                                    $assetRewrites[$resolved['original']] = $resolved['canonical'];
                                }
                            }
                        }
                        break;
                    }
                }
            }

            // Rewrite HTML references from original filenames to canonical id names
            if ($assetRewrites) {
                // Simple string replacements; order doesn't matter because canonical names are unique
                $articleContent = str_replace(
                    array_keys($assetRewrites),
                    array_values($assetRewrites),
                    $articleContent
                );
            }

            // Wrap in full HTML page
            $published = gmdate('Y-m-d\TH:i', $id);
            $lastEdit = gmdate('Y-m-d\TH:i', $article['last_edit']);

            $html = "<!doctype html><html><head>"
                . "<meta charset='UTF-8'>"
                . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
                . "<meta name='color-scheme' content='dark light'>"
                . "<link rel='stylesheet' href='../style.css'>"
                . "<link rel='alternate' type='application/atom+xml' title='{$this->siteName}' href='../atom.xml'>"
                . "<title>" . htmlspecialchars($article['title']) . "</title>"
                . "</head><body>"
                . "<header><a href='../'>🏠 Index</a></header>"
                . "<main><article><h1>" . htmlspecialchars($article['title']) . "</h1>{$articleContent}</article></main>"
                . "<footer><div id='pub-edit-container'>"
                . "<label>Published: <input type='datetime-local' value='$published' readonly> UTC</label><br>"
                . "<label>Edited: <input type='datetime-local' value='$lastEdit' readonly> UTC</label>"
                . "</div></footer>";

            foreach (array_keys($styles) as $s) {
                $html = str_replace("</head>", "<link rel='stylesheet' href=\"{$s}\" />\n</head>", $html);
            }

            foreach (array_keys($scripts) as $s) {
                $html .= str_starts_with($s, '<script') ? $s : "<script src=\"{$s}\" defer></script>";
            }

            $html .= "</body></html>";

            // Write HTML file based on the slug
            $currentSlug = Utils::slugify($article['title']);
            file_put_contents("{$articleDir}/{$currentSlug}.html", $html);

            // Redirecting index to current slug
            $redirect = "<!doctype html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'><meta name='color-scheme' content='dark light'><link rel='stylesheet' href='../style.css'><meta http-equiv='refresh' content='0;url={$currentSlug}.html'><title>Redirecting</title></head><body>If you're not redirected, <a href='{$currentSlug}.html'>click here</a>.</body></html>";
            file_put_contents("{$articleDir}/index.html", $redirect);

            // Old titles -> symlink to index.html, skipping currentSlug and duplicates
            foreach ($article['titles'] ?? [] as $title) {
                $slug = Utils::slugify($title);
                if ($slug !== $currentSlug) {
                    $oldPath = "{$articleDir}/{$slug}.html";
                    if (!file_exists($oldPath)) {
                        $this->ensureSymlinkOrCopy('index.html', $oldPath, $articleDir);
                    }
                }
            }

            $indexList[] = [
                'id' => $id,
                'title' => htmlspecialchars(explode("\n", $article['title'], 2)[0]),
                'slug' => $currentSlug,
                'folder' => $articleFolderName,
            ];
            $renderedArticles[$id] = [
                'id' => $id,
                'title' => $article['title'],
                'last_edit' => $article['last_edit'],
                'slug' => $currentSlug,
                'folder' => $articleFolderName,
                'html' => $articleContent
            ];

            Utils::debug("✅ Built article $id", $this->userDir);
        }

        // Render index.html and atom.xml in {$userDir}/site
        $this->renderIndex($indexList, $siteRoot);
        $this->renderAtomFeed($renderedArticles, $siteRoot);
    }

    private function renderIndex(array $indexList, string $siteRoot): void
    {
        // Sort newest first
        usort($indexList, fn(array $a, array $b): int => $b['id'] <=> $a['id']);

        $html = "<!doctype html><html><head>"
            . "<meta charset='UTF-8'>"
            . "<meta name='viewport' content='width=device-width,initial-scale=1'>"
            . "<meta name='color-scheme' content='dark light'>"
            . "<link rel='stylesheet' href='style.css'>"
            . "<link rel='alternate' type='application/atom+xml' title='{$this->siteName}' href='atom.xml'>"
            . "<title>{$this->siteName}</title>"
            . "</head><body><h1>{$this->siteName}</h1>";

        $currentHeader = null;

        foreach ($indexList as $it) {
            $yearMonth = gmdate('Y-m', $it['id']);

            // Start a new section when year-month changes
            if ($yearMonth !== $currentHeader) {
                if ($currentHeader !== null) {
                    $html .= "</ul>"; // close previous list
                }
                $currentHeader = $yearMonth;
                $html .= "<h2>{$currentHeader}</h2><ul>";
            }

            // Use precomputed folder and slug
            $href = "{$it['folder']}/{$it['slug']}.html";

            $html .= "<li><a href=\"{$href}\">"
                . htmlspecialchars($it['title'], ENT_NOQUOTES, 'UTF-8')
                . "</a></li>";
        }

        if ($currentHeader !== null) {
            $html .= "</ul>"; // close last list
        }

        $html .= "</body></html>";

        file_put_contents("{$siteRoot}/index.html", $html);
        Utils::debug("Root index generated", $this->userDir);
    }


    private function renderAtomFeed(array $renderedArticles, string $siteRoot): void
    {
        // Sort newest first
        usort($renderedArticles, fn($a, $b) => $b['id'] <=> $a['id']);

        $editDates = array_column($renderedArticles, 'last_edit');
        $editDates[] = 0;
        $feedUpdated = gmdate('c', max($editDates));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <title>' . htmlspecialchars($this->siteName, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</title>\n";
        $xml .= '  <id>tag:' . htmlspecialchars($this->siteName, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</id>\n";
        $xml .= "  <updated>{$feedUpdated}</updated>\n";
        $xml .= '  <link rel="self" href="atom.xml" />' . "\n";
        $xml .= '  <link rel="alternate" href="index.html" />' . "\n";

        foreach ($renderedArticles as $article) {
            $href = "{$article['folder']}/{$article['slug']}.html";
            $xml .= "  <entry>\n";
            $xml .= '    <title>' . htmlspecialchars($article['title'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</title>\n";
            $xml .= "    <link href=\"{$href}\" />\n";
            $xml .= "    <id>{$article['id']}</id>\n";
            $xml .= "    <updated>" . gmdate('c', $article['last_edit']) . "</updated>\n";
            $xml .= "    <published>" . gmdate('c', $article['id']) . "</published>\n";
            $xml .= "    <content type=\"html\" xml:base='https://{$this->config->baseAddress}/site/{$this->siteName}/{$article['folder']}/'><![CDATA[" . $article['html'] . "]]></content>\n";
            $xml .= "  </entry>\n";
        }

        $xml .= "</feed>\n";

        file_put_contents("{$siteRoot}/atom.xml", $xml);
        Utils::debug("Atom feed generated", $this->userDir);
    }

    private function resolveAndDownloadAsset(array $asset, string $assetsRoot): ?array
    {
        $fileId = $asset['file_id'] ?? '';
        $filename = $asset['filename'] ?? '';

        if ($fileId === '' || $filename === '') {
            return null; // nothing to do
        }

        // Determine extension from filename
        $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: '';
        // Use file_id as the canonical base (safe for filesystem)
        $safeId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $fileId);
        $canonicalName = $ext ? "{$safeId}.{$ext}" : $safeId;

        $localPath = "{$assetsRoot}/{$canonicalName}";

        if (!file_exists($localPath)) {
            // Get file info from Telegram
            $infoUrl = "https://api.telegram.org/bot{$this->config->botToken}/getFile?file_id={$fileId}";
            $response = @file_get_contents($infoUrl);
            if ($response === false) {
                return null;
            }
            $fileInfo = json_decode($response, true);
            if (!is_array($fileInfo) || !isset($fileInfo['result']['file_path'])) {
                return null;
            }
            $filePath = $fileInfo['result']['file_path'];
            if (!is_string($filePath) || trim($filePath) === '') {
                return null;
            }

            // Download file
            $fileUrl = "https://api.telegram.org/file/bot{$this->config->botToken}/{$filePath}";
            $data = @file_get_contents($fileUrl);
            if ($data === false) {
                return null;
            }

            if (@file_put_contents($localPath, $data) === false) {
                return null;
            }
            Utils::debug("✅ Asset saved to {$localPath}", $this->userDir);
        } else {
            Utils::debug("🔁 Asset already exists: {$localPath}", $this->userDir);
        }

        return [
            'original' => $filename,
            'canonical' => $canonicalName
        ];
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function cleanSiteFolder(string $siteRoot): void
    {
        foreach (glob(pattern: "{$siteRoot}/*") as $item) {
            if (is_dir($item) && basename($item) === 'assets') {
                continue; // preserve assets
            }
            $this->deleteRecursive($item);
        }
    }

    private function deleteRecursive(string $path): void
    {
        // Resolve absolute paths
        $userDirReal = rtrim(realpath($this->userDir), DIRECTORY_SEPARATOR);
        $targetReal = realpath($path);

        // If the target doesn't exist, nothing to do
        if ($targetReal === false) {
            return;
        }

        // Security check: ensure target is inside the user's directory
        if (strpos($targetReal, $userDirReal . DIRECTORY_SEPARATOR) !== 0 && $targetReal !== $userDirReal) {
            Utils::debug("⚠️ Refusing to delete outside of user directory: {$targetReal}", $this->userDir);
            return;
        }

        // Proceed with deletion
        if (is_dir($targetReal) && !is_link($targetReal)) {
            foreach (scandir($targetReal) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $this->deleteRecursive($targetReal . DIRECTORY_SEPARATOR . $file);
            }
            rmdir($targetReal);
        } else {
            unlink($targetReal);
        }
    }

    private function ensureStyleSymlink(string $siteRoot): void
    {
        $target = '../../../style.css';
        $link = "{$siteRoot}/style.css";
        $this->ensureSymlinkOrCopy($target, $link, $siteRoot);
    }

    private function ensureSymlinkOrCopy(string $targetRelative, string $linkPath, string $baseDir): void
    {
        // Remove existing file/link if present
        if (file_exists($linkPath) || is_link($linkPath)) {
            unlink($linkPath);
        }
        $targetPath = $targetRelative;
        // Try symlink
        if (@symlink($targetPath, $linkPath) === false) {
            // Fallback to copy
            $sourceAbs = realpath($baseDir . '/' . $targetRelative);
            if ($sourceAbs && file_exists($sourceAbs)) {
                copy($sourceAbs, $linkPath);
            }
        }
    }
}
