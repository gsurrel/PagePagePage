<?php
declare(strict_types=1);

namespace PagePagePageServices;

use PagePagePageDTO\TelegramMessage;

final class SiteManager
{
    public function __construct(
        private SiteStorage $storage,
        private LoggerInterface $logger
    ) {
    }

    public function getUserDir(): string
    {
        return $this->storage->getUserDir();
    }

    // ────────────────
    // State Management
    // ────────────────

    public function isOffline(): bool
    {
        return $this->storage->getMeta('site_offline') === 'true';
    }

    public function setOffline(bool $flag): void
    {
        $this->storage->setMeta('site_offline', $flag ? 'true' : 'false');
        $this->logger->log("Offline state set to " . ($flag ? 'true' : 'false'), "SiteManager");
    }

    public function isAwaitingTitle(): bool
    {
        return ($this->storage->getState()['awaiting_title'] ?? 'false') === 'true';
    }

    public function isAwaitingSitename(): bool
    {
        return ($this->storage->getState()['awaiting_sitename'] ?? 'false') === 'true';
    }

    public function getSiteName(): ?string
    {
        return $this->storage->getMeta('site_name');
    }

    public function setSiteName(string $name): void
    {
        $this->storage->setMeta('site_name', $name);
    }

    public function setAwaitingTitle(bool $flag): void
    {
        $state = $this->storage->getState();
        $state['awaiting_title'] = $flag ? 'true' : 'false';
        $this->storage->setState($state);
        $this->logger->log("Awaiting title set to " . ($flag ? 'true' : 'false'), "SiteManager");
    }

    public function setAwaitingSiteName(bool $flag): void
    {
        $state = $this->storage->getState();
        $state['awaiting_sitename'] = $flag ? 'true' : 'false';
        $this->storage->setState($state);
        $this->logger->log("Awaiting sitename set to " . ($flag ? 'true' : 'false'), "SiteManager");
    }

    public function clearSelection(): void
    {
        $this->storage->clearStateKeys(['selected_article_id', 'last_interaction']);
        $this->logger->log("Cleared selection", "SiteManager");
    }

    public function getSelectedArticleId(int $timestamp): ?int
    {
        $state = $this->storage->getState();
        if (!isset($state['selected_article_id'], $state['last_interaction'])) {
            return null;
        }

        if (($timestamp - (int) $state['last_interaction']) >= 7200) {
            return null;
        }

        return (int) $state['selected_article_id'];
    }

    public function selectArticle(int $articleId, int $timestamp): void
    {
        $this->storage->setState([
            'selected_article_id' => $articleId,
            'last_interaction' => $timestamp
        ]);
        $this->logger->log("Selected article $articleId", "SiteManager");
    }

    // ─────────────────
    // Article Lifecycle
    // ─────────────────

    public function createArticle(string $title, int $timestamp): int
    {
        $id = $timestamp;
        $this->storage->createArticle($id, $title, false);
        $this->selectArticle($timestamp, $timestamp);
        $this->logger->log("Created article at $timestamp", "SiteManager");
        return $timestamp;
    }

    public function deleteArticle(int $articleId): void
    {
        $this->storage->deleteArticle($articleId);
        $this->logger->log("Deleted article $articleId", "SiteManager");
        $this->clearSelection();
    }

    public function hasArticle(int $articleId): bool
    {
        return $this->storage->hasArticle($articleId);
    }

    public function getArticles(): array
    {
        return $this->storage->getArticles();
    }

    public function getArticle(int $articleId): ?array
    {
        return $this->storage->getArticle($articleId);
    }

    public function getTitle(int $articleId): string
    {
        return $this->getArticle($articleId)['title'] ?? 'Untitled';
    }

    public function renameArticle(int $articleId, string $newTitle): void
    {
        $this->storage->updateArticleMeta($articleId, ['title' => $newTitle]);
        $this->logger->log("Renamed article $articleId to \"$newTitle\"", "SiteManager");
    }

    public function setDraft(int $articleId, bool $flag): void
    {
        $this->storage->updateArticleMeta($articleId, ['draft' => $flag]);
        $this->logger->log("Set draft state on article $articleId to $flag", "SiteManager");
    }

    public function isDraft(int $articleId): bool
    {
        return $this->getArticle($articleId)['draft'] ?? true;
    }

    public function appendMessage(TelegramMessage $message): ?string
    {
        $timestamp = $message->getTimestamp();
        $articleId = $this->getSelectedArticleId($timestamp);
        if (!$articleId) {
            $this->logger->log("Message ignored — no article selected", "SiteManager");
            return "Message ignored — no article selected";
        }

        $article = $this->getArticle($articleId);
        if (!$article) {
            $this->logger->log("Message ignored — selected article not found", "SiteManager");
            return "Message ignored — selected article not found";
        }

        $this->storage->appendMessage($articleId, $message);
        $this->storage->updateArticleMeta($articleId, ['last_edit' => $timestamp]);

        if ($message->getText()) {
            preg_match_all('/#\w+/u', $message->getText(), $matches);
            $tags = array_unique(array_merge($article['hashtags'], $matches[0]));
            $this->storage->updateArticleMeta($articleId, ['hashtags' => array_values($tags)]);
        }

        $this->selectArticle($articleId, $timestamp);
        $this->logger->log("Appended message to article $articleId", "SiteManager");

        return null;
    }

    public function replaceMessage(TelegramMessage $message): ?string
    {
        $timestamp = $message->getTimestamp();
        $articleId = $this->getSelectedArticleId($timestamp);
        if (!$articleId) {
            $this->logger->log("Edited message ignored — no article selected", "SiteManager");
            return "Edited message ignored — no article selected";
        }

        $article = $this->getArticle($articleId);
        if (!$article) {
            $this->logger->log("Edited message ignored — selected article not found", "SiteManager");
            return "Edited message ignored — selected article not found";
        }

        $success = $this->storage->replaceMessage($articleId, $message);
        if (!$success) {
            $this->logger->log("Edited message not found in article $articleId", "SiteManager");
            return "⚠️ Edited message not found in current article.";
        }

        $this->storage->updateArticleMeta($articleId, ['last_edit' => $timestamp]);
        $this->selectArticle($articleId, $timestamp);
        $this->logger->log("Replaced message in article $articleId", "SiteManager");

        return null;
    }
}
