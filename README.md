# PagePagePage

[PagePagePage](https://pagepagepage.org) is a minimalist blogging engine powered by Telegram. It turns your messages into a clean, static website. Just you, your words, and a bot that listens.

## What is it?

PagePagePage lets you publish articles, photo galleries, or microblog entries directly from Telegram. You chat with the bot, and it builds your site. Each article becomes a folder of HTML and media files, styled with a shared CSS file. The result is a fast, lightweight website that’s entirely yours.

You can see it in action at [pagepagepage.org](https://pagepagepage.org), or read the introductory post [here](https://pagepagepage.org/site/PagePagePageBlog/2025-09-01..09.15.54/Introducing-PagePagePage.html).

## Why use it?

Most blogging platforms are either too heavy or too social. PagePagePage is neither, on purpose. It’s designed for people who want to publish without friction, especially from their phone. There are no likes, comments, or algorithms. Just content, rendered as static HTML, hosted wherever you choose. You can choose to use the provided [PagePagePage.org](https://pagepagepage.org) server if you want to.

It’s perfect for:

- Writers who want a personal blog without the overhead
- Travelers sharing photo galleries on the go
- Developers who love simplicity and control
- Anyone tired of chasing engagement metrics

## How does it work?

Start a conversation with the Telegram bot. It will ask for your blog name, then guide you through creating your first article. Every message you send becomes part of the article. When you're ready, tell the bot to regenerate your site. It will update your homepage and render each article as a standalone HTML page.

The backend is written in PHP 8.1+, with strict typing and a modular architecture. It uses DTOs to represent Telegram updates, a command bus to handle interactions, and a renderer to generate clean HTML.

## Contributing

We welcome contributions! If you're interested in improving the bot, fixing bugs, or adding features, check out the [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on coding standards, project structure, and how to get started.

## License

PagePagePage is open source and available under the GPLv3 License.

---

Created by [Grégoire Surrel](https://gregoire.surrel.org), who occasionally turns ideas into code.
