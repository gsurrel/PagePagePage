<?php
declare(strict_types=1);

namespace PagePagePageUtils;

final class Utils
{
    /**
     * Format Telegram message text with HTML entities and inline styles.
     *
     * Handles overlapping entities by planning tag insertions before mutation.
     * Ensures correct nesting and applies special handling for block-level elements.
     *
     * Strategy:
     *  - Separate inline vs block entities.
     *  - Render outside regions as paragraphs with inline tags.
     *  - For block regions:
     *      - 'styled': apply inline tags, split by blank lines into <p>, convert single \n to <br/>, wrap with block tag.
     *      - 'raw': ignore inline inside, escape, and wrap with block tag.
     *  - Ignore nested/overlapping blocks (keep the first, drop subsequent overlaps).
     *
     * @param string $text
     * @param array<int, array> $entities
     * @return string
     */
    public static function formatMessageText(string $text, array $entities = []): string
    {
        if ($text === '') {
            return '';
        }

        // Precompute UTF-16 code-unit -> UTF-8 char index mapping.
        $utf16Map = self::buildUtf16ToUtf8IndexMap($text);
        $lenTxt = mb_strlen($text, 'UTF-8');

        /** @var array<int, array{start:int,end:int,open:string,close:string}> $inlineEntitiesUtf8 */
        $inlineEntitiesUtf8 = [];
        /** @var array<int, array{start:int,end:int,open:string,close:string,type:'styled'|'raw'}> $blockEntitiesUtf8 */
        $blockEntitiesUtf8 = [];

        // Convert and classify entities (single pass)
        foreach ($entities as $e) {
            if (!isset($e['type'], $e['offset'], $e['length'])) {
                continue;
            }
            $start = self::utf16IndexToUtf8($utf16Map, (int) $e['offset']);
            $end = self::utf16IndexToUtf8($utf16Map, (int) $e['offset'] + (int) $e['length']);

            if ($start < 0 || $start >= $lenTxt || $end > $lenTxt || $end <= $start) {
                continue;
            }

            $tag = self::getHtmlTag($e);
            if ($tag === null) {
                continue;
            }

            if ($tag['block'] === null) {
                $inlineEntitiesUtf8[] = [
                    'start' => $start,
                    'end' => $end,
                    'open' => $tag['open'],
                    'close' => $tag['close'],
                ];
            } else {
                $blockEntitiesUtf8[] = [
                    'start' => $start,
                    'end' => $end,
                    'open' => $tag['open'],
                    'close' => $tag['close'],
                    'type' => $tag['block'],
                ];
            }
        }

        // Sort blocks and drop overlapping blocks (keep first non-overlapping)
        usort($blockEntitiesUtf8, static fn($a, $b) => $a['start'] <=> $b['start'] ?: $a['end'] <=> $b['end']);
        $filteredBlocks = [];
        $lastEnd = -1;
        foreach ($blockEntitiesUtf8 as $b) {
            if ($b['start'] >= $lastEnd) {
                $filteredBlocks[] = $b;
                $lastEnd = $b['end'];
            }
        }
        $blockEntitiesUtf8 = $filteredBlocks;

        // Render by walking outside/inside block regions
        $cursor = 0;
        $html = '';

        foreach ($blockEntitiesUtf8 as $b) {
            // Outside segment before block
            if ($cursor < $b['start']) {
                $html .= self::renderParagraphsWithInlineClamped($text, $cursor, $b['start'], $inlineEntitiesUtf8);
            }

            // Block segment
            if ($b['type'] === 'raw') {
                $raw = mb_substr($text, $b['start'], $b['end'] - $b['start'], 'UTF-8');
                $html .= $b['open']
                    . htmlspecialchars($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    . $b['close'];
            } else {
                $html .= $b['open']
                    . self::renderParagraphsWithInlineClamped($text, $b['start'], $b['end'], $inlineEntitiesUtf8)
                    . $b['close'];
            }

            $cursor = $b['end'];
        }

        // Tail after last block
        if ($cursor < $lenTxt) {
            $html .= self::renderParagraphsWithInlineClamped($text, $cursor, $lenTxt, $inlineEntitiesUtf8);
        }

        return $html;
    }

    /**
     * Create a UTF-16 code-unit index -> UTF-8 character index map.
     *
     * The returned array maps cumulative UTF-16 code units consumed -> UTF-8 char index
     * at the boundary. Example: $map[5] gives the UTF-8 char index after consuming 5 UTF-16 code units.
     *
     * Complexity: O(n) where n is the number of UTF-8 characters.
     *
     * @param string $text UTF-8
     * @return array<int,int> map
     */
    private static function buildUtf16ToUtf8IndexMap(string $text): array
    {
        // Split into UTF-8 characters
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $map = [];
        $utf16Count = 0;
        $utf8Index = 0;
        foreach ($chars as $ch) {
            // Determine UTF-16 code unit length: strlen of UTF-16LE bytes / 2
            $utf16Len = intdiv(strlen(mb_convert_encoding($ch, 'UTF-16LE', 'UTF-8')), 2);
            // Record mapping at current cumulative utf16Count
            $map[$utf16Count] = $utf8Index;
            $utf16Count += $utf16Len;
            $utf8Index++;
        }
        // Also map the final boundary
        $map[$utf16Count] = $utf8Index;
        return $map;
    }

    /**
     * Convert a UTF-16 code-unit index into a UTF-8 char index using the precomputed map.
     * If the exact utf16 index does not exist (e.g., falls inside a surrogate pair),
     * return the corresponding UTF-8 index at that boundary (clamped).
     *
     * @param array<int,int> $map
     * @param int $offset16
     * @return int
     */
    private static function utf16IndexToUtf8(array $map, int $offset16): int
    {
        if ($offset16 <= 0) {
            return $map[0] ?? 0;
        }
        if (isset($map[$offset16])) {
            return $map[$offset16];
        }
        // Find the greatest key less than offset16
        $keys = array_keys($map);
        // keys are increasing as built; do binary search for efficiency
        $low = 0;
        $high = count($keys) - 1;
        $resKey = $keys[0];
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $k = $keys[$mid];
            if ($k === $offset16) {
                $resKey = $k;
                break;
            } elseif ($k < $offset16) {
                $resKey = $k;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }
        return $map[$resKey];
    }

    /**
     * Map Telegram entity to HTML tag pair with block hint and URL sanitization.
     *
     * Allowed URL schemes: http, https, mailto, tg
     *
     * @param array<string, mixed> $entity
     * @return array{open: string, close: string, block: 'styled'|'raw'|null}|null
     */
    private static function getHtmlTag(array $entity): ?array
    {
        return match ($entity['type']) {
            'bold' => ['open' => '<b>', 'close' => '</b>', 'block' => null],
            'italic' => ['open' => '<i>', 'close' => '</i>', 'block' => null],
            'underline' => ['open' => '<u>', 'close' => '</u>', 'block' => null],
            'strikethrough' => ['open' => '<s>', 'close' => '</s>', 'block' => null],
            'code' => ['open' => '<code>', 'close' => '</code>', 'block' => null],
            'spoiler' => ['open' => "<span class='spoiler'>", 'close' => '</span>', 'block' => null],
            'text_link' => (isset($entity['url']) && self::isAllowedUrl((string) $entity['url']))
            ? ['open' => "<a href='" . htmlspecialchars((string) $entity['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "' rel='noopener'>", 'close' => '</a>', 'block' => null]
            : null,
            'pre' => [
                'open' => '<pre' . (isset($entity['language']) && $entity['language'] !== '' ? " data-lang='" . htmlspecialchars((string) $entity['language'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "'" : '') . '>',
                'close' => '</pre>',
                'block' => 'raw'
            ], 'blockquote' => ['open' => '<blockquote>', 'close' => '</blockquote>', 'block' => 'styled'],
            default => null,
        };
    }

    /**
     * Simple allowlist URL check. Allows http, https, mailto, tg.
     *
     * @param string $url
     * @return bool
     */
    private static function isAllowedUrl(string $url): bool
    {
        // Normalize whitespace
        $url = trim($url);
        // Basic parse
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        return in_array($scheme, ['http', 'https', 'mailto', 'tg'], true);
    }

    /**
     * Render a text segment [start,end) as paragraphs with inline entities applied (clamping entities to bounds).
     *
     * @param string $text
     * @param int $start
     * @param int $end
     * @param array<int, array{start:int,end:int,open:string,close:string}> $inlineEntities
     * @return string
     */
    private static function renderParagraphsWithInlineClamped(
        string $text,
        int $start,
        int $end,
        array $inlineEntities
    ): string {
        // Apply inline entities and clamp to bounds
        $segment = self::renderInlineSegmentClamped($text, $start, $end, $inlineEntities);

        // Split into paragraphs on 2+ consecutive newlines
        $paragraphs = preg_split("/\n{2,}/u", $segment) ?: [];

        $out = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }

            // Replace single newlines inside the paragraph with <br/>
            $p = str_replace("\n", "<br/>", $p);

            // Heading detection (UTF‑8 safe)
            if (mb_substr($p, 0, 3, 'UTF-8') === '## ') {
                $out .= '<h3>' . mb_substr($p, 3, null, 'UTF-8') . '</h3>';
            } elseif (mb_substr($p, 0, 2, 'UTF-8') === '# ') {
                $out .= '<h2>' . mb_substr($p, 2, null, 'UTF-8') . '</h2>';
            } else {
                $out .= '<p>' . $p . '</p>';
            }
        }

        return $out;
    }

    /**
     * Render a text segment [start,end) with inline entities only (no paragraphing).
     * Inline entities that cross the boundaries are clamped to the segment.
     *
     * Uses event-based rendering to ensure well-formed nesting deterministically.
     *
     * @param string $text
     * @param int $start
     * @param int $end
     * @param array<int, array{start:int,end:int,open:string,close:string}> $inlineEntities
     * @return string
     */
    private static function renderInlineSegmentClamped(string $text, int $start, int $end, array $inlineEntities): string
    {
        // Collect clamped entities that intersect [start,end)
        $events = []; // each event: ['pos'=>int, 'type'=>'open'|'close', 'prio'=>int, 'tag'=>string]
        foreach ($inlineEntities as $e) {
            if ($e['end'] <= $start || $e['start'] >= $end) {
                continue;
            }
            // Clamp to segment
            $s = max($start, $e['start']);
            $t = min($end, $e['end']);
            // Use length as priority (longer opens should come before shorter opens)
            $len = $t - $s;
            $events[] = ['pos' => $s, 'type' => 'open', 'prio' => -$len, 'tag' => $e['open']]; // negative for sorting (longer first)
            $events[] = ['pos' => $t, 'type' => 'close', 'prio' => $len, 'tag' => $e['close']]; // positive for sorting (shorter first)
        }

        // Sort events:
        //  - by pos ascending
        //  - opens before closes at same pos
        //  - for opens: longer first (prio smaller since negative)
        //  - for closes: shorter first (prio smaller positive)
        usort($events, static function ($a, $b) {
            if ($a['pos'] !== $b['pos']) {
                return $a['pos'] <=> $b['pos'];
            }
            if ($a['type'] === $b['type']) {
                return $a['prio'] <=> $b['prio'];
            }
            // open first
            return ($a['type'] === 'open') ? -1 : 1;
        });

        $out = '';
        $cursor = $start;
        foreach ($events as $ev) {
            if ($cursor < $ev['pos']) {
                $out .= htmlspecialchars(mb_substr($text, $cursor, $ev['pos'] - $cursor, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $cursor = $ev['pos'];
            }
            // Inject tag
            $out .= $ev['tag'];
        }

        if ($cursor < $end) {
            $out .= htmlspecialchars(mb_substr($text, $cursor, $end - $cursor, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $out;
    }

    public static function slugify(string $text): string
    {
        // Replace only characters that are *meaningful* in URLs and could break parsing
        // Reserved characters per RFC 3986: : / \ ? # [ ] @ ! $ & ' ( ) * + , ; = %, and spaces
        // We'll replace them with a dash, but leave other Unicode intact
        $text = preg_replace('/[:\/\\\\|?#\[\]@!$&\'()*+,;=%\s]+/u', '-', $text);

        return $text;
    }

    /**
     * Append a debug message to the user’s process log.
     */
    // TODO: Remove in favor of the logger for the ServiceProvider?
    public static function debug(string $msg, string $dir): void
    {
        if (defined('PAGEPAGEPAGE_TESTING')) {
            return; // Skip logging in test mode
        }

        $timestamp = gmdate("Y-m-d H:i:s");
        file_put_contents("$dir/process.log", "[$timestamp] $msg\n", FILE_APPEND);
    }
}
