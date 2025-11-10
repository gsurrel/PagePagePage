<?php
declare(strict_types=1);

namespace PagePagePageServices;

use PagePagePageDTO\TelegramMessage;
use PDO;
use PDOException;

/**
 * SiteStorage — Version 4 with SQLite
 * Migrates legacy JSON (v1) and PHP export (v2), stores full site state in normalized SQLite tables.
 * Migrates from SQLite (v3):
 *     - keep the history of article names
 *     - keep trace of deleted articles
 *     - versionning based on `user_version`
 *     - drop columns that can be computed cheaply
 */
final class SiteStorage
{
    private PDO $db;

    public function __construct(
        private string $userDir,
        private LoggerInterface $logger,
        private ?string $username,
        private string $dbFile = 'site.sqlite'
    ) {
        $path = "{$this->userDir}/{$this->dbFile}";
        $this->db = new PDO("sqlite:$path", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->initializeSchema();

        $dbVersion = $this->getDataModelVersion();
        $this->logger->log("DB version $dbVersion", "SiteStorage");

        if ($dbVersion === 3) {
            $this->logger->log("Migrating DB", "SiteStorage");
            $this->migrateV3toV4();
            $dbVersion = $this->getDataModelVersion();
        }

        if ($dbVersion < 4) {
            throw new \RuntimeException("Unsupported data model version: $dbVersion");
        }

        if ($this->isEmpty()) {
            $this->logger->log("🔄 Attempting migration from legacy format", "SiteStorage");

            // Check nickname fallback
            $legacyDir = $this->username
                ? "{$this->username}/"
                : null;

            if ($legacyDir && is_dir($legacyDir)) {
                $data = $this->loadLegacyDataFrom($legacyDir);
                $data['site_name'] = $this->username;

                if ($data) {
                    $this->save($data);
                    $this->logger->log("📜 Migrated legacy data from nickname directory", "SiteStorage");
                }
            } else {
                $this->logger->log("⚠️ No legacy data found for migration", "SiteStorage");
            }
        } else {
            $this->logger->log("✅ Loaded site from SQLite", "SiteStorage");
        }
    }

    // ──────────────────
    // Schema & Migration
    // ──────────────────

    private function initializeSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS meta (
                key TEXT PRIMARY KEY,
                value TEXT
            );
            CREATE TABLE IF NOT EXISTS state (
                key TEXT PRIMARY KEY,
                value TEXT
            );
            CREATE TABLE IF NOT EXISTS articles (
                id INTEGER PRIMARY KEY,
                draft INTEGER NOT NULL DEFAULT 0,
                deleted INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE IF NOT EXISTS article_titles (
                article_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                PRIMARY KEY (article_id, title),
                FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
            );
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                article_id INTEGER,
                position INTEGER,
                raw TEXT
            );
        ");

        // If user_version is 0 (unset), set to 4
        if ($this->getDataModelVersion() === 0) {
            $this->db->exec("PRAGMA user_version = 4;");
        }
    }

    public function getDataModelVersion(): int
    {
        // On data model version 3, the version is stored in the meta table
        $stmt = $this->db->prepare("
            SELECT value FROM meta
            WHERE key = 'data_model_version'
            LIMIT 1;
        ");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            return (int) $val;
        }

        // On data model version 4+, the version is stored as a PRAGMA
        return (int) $this->db->query("PRAGMA user_version;")->fetchColumn();
    }

    private function isEmpty(): bool
    {
        return ($this->db->query("SELECT COUNT(*) FROM articles")->fetchColumn()) === 0;
    }

    private function loadLegacyDataFrom(string $path): ?array
    {
        if (file_exists("$path/site.php")) {
            return $this->loadV2PhpSiteData("$path/site.php");
        }

        if (file_exists("$path/messages.json")) {
            return $this->loadV1JsonMessages("$path/messages.json");
        }

        return null;
    }

    private function loadV1JsonMessages(string $path): ?array
    {
        $raw = json_decode(file_get_contents($path), true);
        if (!is_array($raw))
            return null;

        // 🔍 V0 legacy array format
        if (array_is_list($raw) && isset($raw[0]['message_id'])) {
            $timestamps = array_filter(array_map(
                fn($msg) => is_array($msg) && isset($msg['date']) ? $msg['date'] : null,
                $raw
            ));
            if (empty($timestamps))
                return null;

            $articleId = min($timestamps);
            $lastEdit = max(array_merge(
                $timestamps,
                array_filter(array_map(fn($msg) => $msg['edit_date'] ?? null, $raw))
            ));

            $messages = array_values(array_filter(array_map(
                fn($msg) => is_array($msg) ? TelegramMessage::fromArray($msg) : null,
                $raw
            )));

            return [
                'version' => 2,
                'site_offline' => false,
                'state' => [
                    'selected_article_id' => $articleId,
                    'last_interaction' => $lastEdit,
                ],
                'articles' => [
                    $articleId => [
                        'title' => 'Imported legacy messages',
                        'last_edit' => $lastEdit,
                        'draft' => false,
                        'hashtags' => [],
                        'messages' => $messages,
                    ],
                ],
            ];
        }

        // 🔍 V1 structured article format
        if (isset($raw['articles']) && is_array($raw['articles'])) {
            $articles = [];

            foreach ($raw['articles'] as $id => $article) {
                if (!is_array($article))
                    continue;

                $messageObjs = array_values(array_filter(array_map(
                    fn($msg) => is_array($msg) ? TelegramMessage::fromArray($msg) : null,
                    $article['messages'] ?? []
                )));

                $articles[$id] = [
                    'title' => is_string($article['title'] ?? null) ? $article['title'] : 'Untitled',
                    'last_edit' => is_numeric($article['last_edit'] ?? null) ? $article['last_edit'] : time(),
                    'draft' => !empty($article['draft']),
                    'hashtags' => is_array($article['hashtags'] ?? null) ? $article['hashtags'] : [],
                    'messages' => $messageObjs,
                ];
            }

            if (empty($articles))
                return null;

            return [
                'version' => $raw['version'] ?? 1,
                'site_offline' => $raw['site_offline'] ?? false,
                'state' => [
                    'selected_article_id' => $raw['state']['selected_article_id'] ?? array_key_first($articles),
                    'last_interaction' => $raw['state']['last_interaction'] ?? time(),
                ],
                'articles' => $articles,
            ];
        }

        return null; // 🧨 Unknown format
    }

    private function loadV2PhpSiteData(string $phpPath): ?array
    {
        $raw = require $phpPath;
        $data = $this->rehydrate($raw);
        $this->logger->log("📜 Migrated from site.php (v2)", "SiteStorage");

        return $data;
    }

    private function migrateV3toV4(): void
    {
        $this->logger->log("🔧 Migrating schema v3 → v4", "SiteStorage");
        $this->db->beginTransaction();
        try {
            // 1) Build the new articles table WITHOUT title
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS articles_new (
                    id INTEGER PRIMARY KEY,
                    draft INTEGER NOT NULL DEFAULT 0,
                    deleted INTEGER NOT NULL DEFAULT 0
                );
            ");

            // 2) Copy core data from old articles (v3 had id, title, draft; no deleted)
            $this->db->exec("
                INSERT INTO articles_new (id, draft, deleted)
                SELECT id, draft, 0
                FROM articles;
            ");

            // 3) Rename current articles to articles_old, and promote the new table
            $this->db->exec("ALTER TABLE articles RENAME TO articles_old;");
            $this->db->exec("ALTER TABLE articles_new RENAME TO articles;");

            // 4) Create article_titles and migrate titles from articles_old
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS article_titles (
                    article_id INTEGER NOT NULL,
                    title TEXT NOT NULL,
                    PRIMARY KEY (article_id, title),
                    FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
                );
            ");

            // Carry over the single title per article as the first/only title
            $this->db->exec("
                INSERT INTO article_titles (article_id, title)
                SELECT id AS article_id, title
                FROM articles_old
                WHERE title IS NOT NULL AND TRIM(title) <> '';
            ");

            // 5) Recreate indexes for the new schema
            $this->db->exec("
                CREATE INDEX IF NOT EXISTS idx_articles_id_deleted
                    ON articles(id, deleted);
            ");

            // 6) Remove legacy version tracking and set authoritative version
            $this->db->exec("DELETE FROM meta WHERE key = 'data_model_version';");
            $this->db->exec("PRAGMA user_version = 4;");

            // 7) Drop the old table now that data is migrated
            $this->db->exec("DROP TABLE articles_old;");

            $this->db->commit();
            $this->db->exec("PRAGMA wal_checkpoint(TRUNCATE);");

            $this->logger->log("✅ Migration to v4 complete", "SiteStorage");
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->log("❌ Migration failed: " . $e->getMessage(), "SiteStorage");
            throw $e;
        }
    }

    private function rehydrate(array $raw): array
    {
        foreach ($raw['articles'] as $id => &$article) {
            $article['last_edit'] = (int) $article['last_edit'];
            $article['messages'] = array_map(
                fn(array $msg) => TelegramMessage::fromArray($msg),
                $article['messages']
            );
        }

        return [
            'version' => 2,
            'site_offline' => !empty($raw['site_offline']),
            'state' => array_map('strval', $raw['state'] ?? []),
            'articles' => $raw['articles'],
        ];
    }

    private function save(array $data): void
    {
        $this->db->beginTransaction();
        try {
            // META
            $this->setMeta('site_offline', $data['site_offline'] ? 'true' : 'false');
            if (!empty($data['site_name'])) {
                $this->setMeta('site_name', $data['site_name']);
            }

            // STATE
            $this->db->exec("DELETE FROM state");
            $stmt = $this->db->prepare("INSERT OR REPLACE INTO state VALUES (:k, :v)");
            foreach ($data['state'] as $k => $v) {
                $stmt->execute([':k' => $k, ':v' => $v]);
            }

            // ARTICLES
            $this->db->exec("DELETE FROM article_titles");
            $this->db->exec("DELETE FROM articles");

            $articleStmt = $this->db->prepare("
                INSERT INTO articles (id, draft, deleted)
                VALUES (:id, :draft, 0)
            ");

            $titleStmt = $this->db->prepare("
                INSERT INTO article_titles (article_id, title)
                VALUES (:aid, :title)
            ");

            foreach ($data['articles'] as $id => $a) {
                $articleStmt->execute([
                    ':id' => $id,
                    ':draft' => $a['draft'] ? 1 : 0
                ]);

                // Store current title first
                $titleStmt->execute([
                    ':aid' => $id,
                    ':title' => $a['title']
                ]);

                // Store any extra titles if your data array contains them
                if (!empty($a['titles']) && is_array($a['titles'])) {
                    foreach ($a['titles'] as $t) {
                        if ($t !== $a['title']) {
                            $titleStmt->execute([
                                ':aid' => $id,
                                ':title' => $t
                            ]);
                        }
                    }
                }
            }

            // MESSAGES
            $this->db->exec("DELETE FROM messages");
            $stmt = $this->db->prepare("
                INSERT INTO messages (article_id, position, raw)
                VALUES (:aid, :pos, :raw)
            ");
            foreach ($data['articles'] as $id => $a) {
                foreach ($a['messages'] as $i => $msg) {
                    $stmt->execute([
                        ':aid' => $id,
                        ':pos' => $i,
                        ':raw' => json_encode($msg->getRaw()),
                    ]);
                }
            }
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ────────────
    // State + Meta
    // ────────────

    public function setMeta(string $key, string $value): void
    {
        $stmt = $this->db->prepare("REPLACE INTO meta (key, value) VALUES (:key, :value)");
        $stmt->execute([':key' => $key, ':value' => $value]);
    }

    public function getMeta(string $key): ?string
    {
        $stmt = $this->db->prepare("SELECT value FROM meta WHERE key = :key");
        $stmt->execute([':key' => $key]);
        return $stmt->fetchColumn() ?: null;
    }

    public function setState(array $values): void
    {
        $this->db->exec("DELETE FROM state");
        $stmt = $this->db->prepare("INSERT INTO state (key, value) VALUES (:key, :value)");
        foreach ($values as $k => $v) {
            $stmt->execute([':key' => $k, ':value' => $v]);
        }
    }

    public function getState(): array
    {
        $rows = $this->db->query("SELECT key, value FROM state")->fetchAll();
        return array_column($rows, 'value', 'key');
    }

    public function clearStateKeys(array $keys): void
    {
        $stmt = $this->db->prepare("DELETE FROM state WHERE key = :key");
        foreach ($keys as $k) {
            $stmt->execute([':key' => $k]);
        }
    }

    // ───────────────────
    // Articles + Messages
    // ───────────────────

    public function createArticle(int $id, string $title, bool $draft = false): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare(" INSERT INTO articles (id, draft, deleted) VALUES (:id, :draft, 0) ")->execute([':id' => $id, ':draft' => $draft ? 1 : 0]);
            $this->db->prepare(" INSERT INTO article_titles (article_id, title) VALUES (:id, :title) ")->execute([':id' => $id, ':title' => $title]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    public function updateArticleMeta(int $id, array $fields): void
    {
        $this->db->beginTransaction();
        try {
            $allowed = ['draft', 'deleted'];
            $setParts = [];
            $params = [':id' => $id];

            foreach ($fields as $k => $v) {
                if (in_array($k, $allowed, true)) {
                    $setParts[] = "$k = :$k";
                    $params[":$k"] = (int) $v;
                }
            }

            if ($setParts) {
                $sql = "UPDATE articles SET " . implode(', ', $setParts) . " WHERE id = :id";
                $this->db->prepare($sql)->execute($params);
            }

            if (isset($fields['title']) && is_string($fields['title'])) {
                // Remove any existing title match for this article
                $this->db->prepare("
                    DELETE FROM article_titles
                    WHERE article_id = :id AND title = :title
                ")->execute([
                            ':id' => $id,
                            ':title' => $fields['title']
                        ]);

                // Insert the new title
                $this->db->prepare("
                    INSERT INTO article_titles (article_id, title)
                    VALUES (:id, :title)
                ")->execute([
                            ':id' => $id,
                            ':title' => $fields['title']
                        ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getArticles(): array
    {
        $articles = [];
        $stmt = $this->db->query("SELECT * FROM articles WHERE deleted = 0");

        foreach ($stmt->fetchAll() as $a) {
            $messages = $this->getMessagesForArticle($a['id']);
            $titles = $this->getAllTitles($a['id']);

            $articles[(int) $a['id']] = [
                'titles' => $titles,
                'title' => $titles[0] ?? 'null',
                'last_edit' => $this->computeLastEdit($messages),
                'draft' => (bool) $a['draft'],
                'hashtags' => $this->computeHashtags($messages),
                'messages' => $messages,
            ];
        }

        return $articles;
    }

    public function getArticle(int $articleId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM articles WHERE id = :id");
        $stmt->execute([':id' => $articleId]);
        $a = $stmt->fetch();

        if (!$a) {
            return null;
        }

        $messages = $this->getMessagesForArticle($articleId);
        $titles = $this->getAllTitles($articleId);

        return [
            'titles' => $titles,
            'title' => $titles[0] ?? null,
            'last_edit' => $this->computeLastEdit($messages),
            'draft' => (bool) $a['draft'],
            'hashtags' => $this->computeHashtags($messages),
            'messages' => $messages,
        ];
    }

    private function getAllTitles(int $articleId): array
    {
        $stmt = $this->db->prepare("
        SELECT title
        FROM article_titles
        WHERE article_id = :id
        ORDER BY rowid DESC
    ");
        $stmt->execute([':id' => $articleId]);
        return array_column($stmt->fetchAll(), 'title');
    }

    public function deleteArticle(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE articles SET deleted = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function hasArticle(int $articleId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM articles WHERE id = :id");
        $stmt->execute([':id' => $articleId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function appendMessage(int $articleId, TelegramMessage $msg): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM messages WHERE article_id = :id");
        $stmt->execute([':id' => $articleId]);
        $position = (int) $stmt->fetchColumn();

        $this->db->prepare("
            INSERT INTO messages (article_id, position, raw)
            VALUES (:aid, :pos, :raw)
        ")->execute([
                    ':aid' => $articleId,
                    ':pos' => $position,
                    ':raw' => json_encode($msg->getRaw()),
                ]);
    }

    public function getUserDir(): string
    {
        return $this->userDir;
    }

    public function replaceMessage(int $articleId, TelegramMessage $msg): bool
    {
        $stmt = $this->db->prepare("
            SELECT id, raw FROM messages
            WHERE article_id = :aid
        ");
        $stmt->execute([':aid' => $articleId]);

        $targetRowId = null;
        $incomingId = $msg->getMessageId();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $raw = json_decode($row['raw'], true);
            if (isset($raw['message_id']) && (int) $raw['message_id'] === $incomingId) {
                $targetRowId = (int) $row['id'];
                break;
            }
        }

        if ($targetRowId !== null) {
            $this->db->prepare("
                UPDATE messages SET raw = :raw WHERE id = :rid
            ")->execute([
                        ':raw' => json_encode($msg->getRaw()),
                        ':rid' => $targetRowId,
                    ]);
            return true;
        }

        return false;
    }

    // ────────────────────────
    // Helpers for Derived Data
    // ────────────────────────

    private function getMessagesForArticle(int $articleId): array
    {
        $msgStmt = $this->db->prepare("SELECT raw FROM messages WHERE article_id = :id ORDER BY position");
        $msgStmt->execute([':id' => $articleId]);
        return array_map(
            static fn($r) => TelegramMessage::fromArray(json_decode($r['raw'], true)),
            $msgStmt->fetchAll()
        );
    }

    private function computeLastEdit(array $messages): int
    {
        $times = [];
        foreach ($messages as $msg) {
            $raw = $msg->getRaw();
            if (isset($raw['edit_date'])) {
                $times[] = $raw['edit_date'];
            }
            if (isset($raw['date'])) {
                $times[] = $raw['date'];
            }
        }
        return $times ? max($times) : time();
    }

    private function computeHashtags(array $messages): array
    {
        $tags = [];
        foreach ($messages as $msg) {
            $text = $msg->getText() ?? '';
            if ($text !== '') {
                preg_match_all('/#(\w+)/u', $text, $matches);
                if (!empty($matches[1])) {
                    $tags = array_merge($tags, $matches[1]);
                }
            }
        }
        return array_values(array_unique($tags));
    }
}
