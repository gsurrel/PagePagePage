# Contributing to PagePagePage

Welcome! 🎉 We're thrilled you're interested in contributing to PagePagePage — a minimalist Telegram-powered blogging engine. This guide will help you understand the project's structure, coding standards, and how to contribute effectively.

---

## 📁 Project Structure

```
PagePagePage/
├── PagePagePage.php                  # Entry point for Telegram webhook (bootstrap + kernel)
├── style.css                         # Shared stylesheet for all generated HTML pages
│
├── [username]/                       # Per-user data directory
│   ├── site.php                          # Serialized site state (articles, flags, etc.)
│   ├── index.html                        # Homepage listing all articles
│   └── [article_id]/                     # One folder per article
│       ├── index.html                        # Rendered HTML for the article
│       └── [media].jpg                       # Downloaded media files
│
├── PagePagePageBot/
│   ├── BotKernel.php                     # Main orchestrator for Telegram updates
│   ├── CommandRouter.php                 # Routes commands and callbacks to command classes
│   ├── TelegramClient.php                # Sends/edit messages via Telegram API
│   ├── DummyTelegramClient.php           # Mock/test client for Telegram API
│   ├── TelegramClientInterface.php       # Interface for Telegram API clients
│   └── UpdateParser.php                  # Parses Telegram update into DTOs
├── PagePagePageDTO/                  # Typed representations of data: Telegram objects, rendered messages, etc.
│   ├── RenderedBlock.php                # Encapsulates rendered HTML with assets, styles, scripts
│   ├── TelegramCallback.php             # Callback query
│   ├── TelegramChat.php                 # Chat metadata
│   ├── TelegramMessage.php              # Message
│   ├── TelegramUpdate.php               # Root DTO for a Telegram update
│   └── TelegramUser.php                 # User
│
├── PagePagePageCommands/             # One class per command
│   ├── ArticleMenuCommand.php            # Shows options for a selected article
│   ├── Command.php                       # Interface: all commands implement execute(): void
│   ├── CommandBus.php                    # Dispatches commands implementing Command
│   ├── CreateArticleCommand.php          # Prompts for and creates a new article
│   ├── DeleteArticleCommand.php          # Deletes a specific article
│   ├── DeleteSiteCommand.php             # Deletes all user data
│   ├── EditTitleCommand.php              # Prompts for a new title
│   ├── ListArticlesCommand.php           # Lists all articles with inline buttons
│   ├── RegenerateCommand.php             # Rebuilds static HTML from stored articles
│   ├── RenameArticleCommand.php          # Renames an article
│   ├── SelectArticleCommand.php          # Selects an article for appending messages
│   ├── SetDraftCommand.php               # Marks an article as draft
│   ├── ShowMenuCommand.php               # Displays the main action menu
│   ├── StartCommand.php                  # Handles /start onboarding
│   └── UnsetDraftCommand.php             # Removes draft status
│
├── PagePagePageServices/
│   ├── BotMessenger.php                  # Sends replies and menus back to user
│   ├── CommandContext.php                # Encapsulates message context (chat, user, etc.)
│   ├── Config.php                        # Immutable config object (bot token, salt)
│   ├── HtmlRenderer.php                  # Generates static HTML pages
│   ├── LoggerInterface.php               # Unified logging interface
│   ├── FileSystemLogger.php              # Logger writing to disk
│   ├── MemoryLogger.php                  # Logger for testing and in-memory logs
│   ├── ServiceProvider.php               # Dependency injection container
│   ├── SiteManager.php                   # Coordinates articles and site logic
│   ├── SiteStorage.php                   # Stores site data in SQLite DB
│   └── Renderers/
│       ├── GpxRenderer.php                  # Renders GPX map blocks
│       ├── MediaGroupRenderer.php           # Renders grouped photo carousels
│       ├── MessageRendererInterface.php     # Interface for rendering logic
│       ├── PhotoRenderer.php                # Renders individual photo blocks
│       ├── QuoteRenderer.php                # Renders forwarded or quoted messages
│       └── TextRenderer.php                 # Renders plain text messages
│
├── PagePagePageUtils/
│   └── Utils.php                         # Formatting, debug logging, entity helpers
│
├── phpunit.xml.dist                      # PHPUnit config
├── tests/
│   ├── bootstrap.php                     # Test bootstrap
│   ├── Commands/
│   │   ├── StartCommandTest.php
│   │   └── (to complete)
│   ├── Services/
│   │   └── (not yet implemented)
│   ├── Utils/
│   │   └── UtilsTest.php
│   └── helpers/
│       └── TestContextFactory.php        # Builder for test command contexts
```



---

## ✅ Coding Standards

### Language & Version
- PHP 8.1+ required
- Use `declare(strict_types=1);` at the top of every file

### Type Safety
- All functions and methods must have parameter and return types
- Use `readonly` properties for immutable objects (DTOs, Config)
- Avoid `mixed` or untyped arrays unless shape is documented

### File Structure
- One class per file
- Group files by responsibility (Bot/, Commands/, Services/, Utils/, DTO/)

### DTOs
- Use DTOs for structured data (Telegram updates, messages, users)
- Avoid raw arrays in business logic

---

## 🧪 Testing & Validation

- Validate all external input (Telegram payloads, config values)
- Avoid assumptions about array keys or structure
- Use null coalescing (`??`) or `isset()` for optional fields

---

## 🚫 What to Avoid

- No `exit()` or `die()` — use exceptions or return early
- No `@` error suppression — handle errors explicitly
- No static state or global variables
- No HTML generation outside `HtmlRenderer`

---

## 🧰 Tools & Suggestions

- Use `devsense.phptools-vscode` for static analysis
- Use PHPUnit for unit testing (optional)
- Use GitHub Actions or similar for CI (optional)

---

## 🤝 How to Contribute

1. Fork the repository
2. Create a new branch: `git checkout -b feature/my-feature`
3. Make your changes
4. Run tests and static analysis (if configured)
5. Commit with a clear message
6. Push and open a pull request

---

## 🙏 Thank You

Your contributions help make PagePagePage better for everyone. Whether it's fixing a bug, improving documentation, or adding a new feature — we appreciate your help!

Happy hacking! 🚀

