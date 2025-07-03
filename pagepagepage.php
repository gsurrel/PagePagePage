<?php
/**
 * pagepagepage.php — Telegram → Static Site Bot Handler
 *
 * Features:
 * - Logs every raw update to messages.log (append-only)
 * - Processes new, edited, and deleted messages in-memory
 * - Groups messages by 2-hour gaps into articles
 * - Generates per-article index.html under $username/YYYY-MM-DD--HH-mm/
 * - Builds a root index.html from the in-memory group list
 * - Writes step-by-step logs to $username/process.log for debugging
 */

date_default_timezone_set("UTC");

// Bot token
$BOT_TOKEN = "redacted";

// Read incoming update from webhook
$input = file_get_contents("php://input");
$update = json_decode($input, true);
if (!$update) exit;

// Handle new or edited messages only
$message = $update['edited_message'] ?? $update['message'] ?? null;
if (!$message || empty($message['from']['username'])) exit;

// Prepare user directory based on username
$username = $message['from']['username'];
$userDir  = __DIR__ . "/$username";
if (!is_dir($userDir)) mkdir($userDir, 0775, true);

// Simple logger
function debug(string $msg, string $dir) {
    file_put_contents("$dir/process.log",
        "[".gmdate("Y-m-d H:i:s")."] $msg\n",
        FILE_APPEND
    );
}

// Reply helper
function replyToUser($chatId, $text) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot$BOT_TOKEN/sendMessage";
    $data = json_encode([
        'chat_id' => $chatId,
        'text'    => $text,
    ]);
    file_get_contents($url, false, stream_context_create([
        'http'=>[
            'method'  => "POST",
            'header'  => "Content-Type: application/json",
            'content' => $data
        ]
    ]));
}

// Convert a UTF-16 code-unit offset into a UTF-8 character index
function utf16le_to_utf8_index(string $text, int $offset16): int {
    // Convert entire string to UTF-16LE raw bytes
    $utf16le = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    // Each code unit is 2 bytes
    $byteLen = $offset16 * 2;
    // Take that many bytes (prefix in UTF-16LE)
    $prefixLe = substr($utf16le, 0, $byteLen);
    // Convert back to UTF-8 and measure length in chars
    $prefixUtf8 = mb_convert_encoding($prefixLe, 'UTF-8', 'UTF-16LE');
    return mb_strlen($prefixUtf8, 'UTF-8');
}

// Format text with Telegram entities
function formatMessageText(string $text, array $entities = []): string {
    if ($text === '') return '';

    // Sort by offset16 ascending
    usort($entities, fn($a,$b)=> $a['offset'] <=> $b['offset']);

    $parts  = [];
    $cursor = 0; // Current UTF-8 index
    $lenTxt = mb_strlen($text, 'UTF-8');

    foreach ($entities as $e) {
        $off16 = $e['offset'];
        $len16 = $e['length'];

        // Map to UTF-8
        $start = utf16le_to_utf8_index($text, $off16);
        $end   = utf16le_to_utf8_index($text, $off16 + $len16);

        // Sanity check
        if ($start < 0 || $start > $lenTxt || $end > $lenTxt || $end <= $start) {
            continue;
        }

        // Grab text up to this entity
        if ($start > $cursor) {
            $before = mb_substr($text, $cursor, $start - $cursor, 'UTF-8');
            $parts[] = htmlspecialchars($before, ENT_QUOTES|ENT_HTML5,'UTF-8');
        }

        // Entity substring
        $frag = mb_substr($text, $start, $end - $start, 'UTF-8');
        $esc  = htmlspecialchars($frag, ENT_QUOTES|ENT_HTML5,'UTF-8');

        // Wrap according to type
        switch ($e['type']) {
            case 'bold':          $esc = "<b>$esc</b>"; break;
            case 'italic':        $esc = "<i>$esc</i>"; break;
            case 'underline':     $esc = "<u>$esc</u>"; break;
            case 'strikethrough': $esc = "<s>$esc</s>"; break;
            case 'code':          $esc = "<code>$esc</code>"; break;
            case 'pre':
                $lang = $e['language'] ?? '';
                $esc  = "<pre><code class='language-$lang'>$esc</code></pre>";
                break;
            case 'spoiler':       $esc = "<span class='spoiler'>$esc</span>"; break;
            case 'text_link':
                $url = htmlspecialchars($e['url'], ENT_QUOTES|ENT_HTML5,'UTF-8');
                $esc = "<a href='$url'>$esc</a>"; break;
            case 'blockquote':    $esc = "<blockquote>$esc</blockquote>"; break;
        }

        $parts[] = $esc;
        $cursor = $end;
    }

    // Anything left after last entity
    if ($cursor < mb_strlen($text, 'UTF-8')) {
        $tail = mb_substr($text, $cursor, null, 'UTF-8');
        $parts[] = htmlspecialchars($tail, ENT_QUOTES|ENT_HTML5,'UTF-8');
    }

    // Handle paragraphs & line breaks
    $joined = implode('', $parts);
    $paras  = explode("\n\n", $joined);
    $out    = '';
    foreach ($paras as $p) {
        $out .= '<p>'. str_replace("\n","<br>", $p) .'</p>';
    }
    return $out;
}

// Load & dedupe by message_id
$jsonPath = "$userDir/messages.json";
$messages = file_exists($jsonPath)
    ? json_decode(file_get_contents($jsonPath), true)
    : [];

// Index by ID so edits overwrite originals
$index = [];
foreach ($messages as $m) {
    $index[$m['message_id']] = $m;
}
$index[$message['message_id']] = $message;
$messages = array_values($index);
file_put_contents($jsonPath, json_encode($messages));
debug("Stored message {$message['message_id']}", $userDir);

// Group into time-windows and render HTML pages
usort($messages, fn($a,$b)=> $a['date'] <=> $b['date']);
$groups=[]; $current=[]; $lastTs=null;
foreach ($messages as $m) {
    if ($lastTs===null || $m['date'] - $lastTs <= 7200) {
        $current[]=$m;
    } else {
        $groups[]=$current; $current=[$m];
    }
    $lastTs=$m['date'];
}
if ($current) $groups[]=$current;

// Write out each article
$indexList=[];
foreach ($groups as $grp) {
    $folder = gmdate("Y-m-d--H-i", $grp[0]['date']);
    $dir    = "$userDir/$folder";
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $html = "<!doctype html><html><head><meta charset='UTF-8'><title>$folder</title></head><body>";
    foreach ($grp as $msg) {
        $html .= formatMessageText($msg['text'] ?? '', $msg['entities'] ?? []);

        // Media support (photos only)
        if (!empty($msg["photo"])) {
            $photo = end($msg["photo"]);
            $fileId = $photo["file_id"];
            $mediaPath = "$folderDir/$fileId.jpg";
            if (!file_exists($mediaPath)) {
                $fileInfo = json_decode(
                    file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/getFile?file_id=$fileId"),
                    true
                );
                $filePath = $fileInfo["result"]["file_path"];
                $fileUrl = "https://api.telegram.org/file/bot$BOT_TOKEN/$filePath";
                file_put_contents($mediaPath, file_get_contents($fileUrl));
                debug("Downloaded media: $mediaPath", $userDir);
            }
            $html .= "<img src='$fileId.jpg'><br>";
        }
    }
    
    $html .= "</body></html>";
    file_put_contents("$dir/index.html", $html);

    $title = htmlspecialchars(explode(".", ($grp[0]['text'] ?? ''))[0]);
    $indexList[] = ['folder'=>$folder,'title'=>$title];
    debug("Built $folder", $userDir);
}

// Generate index
usort($indexList, fn($a,$b)=> strcmp($b['folder'],$a['folder']));
$root = "<!doctype html><html><head><meta charset='UTF-8'><title>Articles</title></head><body><ul>";
foreach ($indexList as $it) {
    $root .= "<li><a href=\"{$it['folder']}/\">{$it['title']}</a></li>";
}
$root .= "</ul></body></html>";
file_put_contents("$userDir/index.html", $root);
debug("Root index generated", $userDir);

// Acknowledge
replyToUser($message['chat']['id'], "✅ Saved & site updated in /$username/.");
