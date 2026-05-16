# ChatGPT HTML Export API

PHP API for rendering ChatGPT answers or available chat transcripts as standalone HTML pages. It is designed for use as a Custom GPT Action.

## Features

- Render a single ChatGPT answer with `POST /render`
- Render an available chat transcript with `POST /render-chat`
- Built-in style presets: `clean`, `authority`, `documentation`, `landing`, `cards`, `print`
- Style modes: `preset`, `custom_append`, `custom_full`
- Optional custom CSS per request
- Markdown rendering via `league/commonmark`
- GitHub Flavored Markdown tables and task lists
- Code blocks, Mermaid blocks, MathJax-compatible formulas
- Controlled media handling for images, direct audio/video URLs, YouTube and Vimeo
- Escapes raw HTML input
- API key protection through the `X-API-Key` header
- Dynamic OpenAPI schema at `/openapi.json`
- Simple privacy page at `/privacy`

## Requirements

- PHP 8.1 or newer
- Composer
- PHP extensions: `dom`, `json`
- HTTPS endpoint for Custom GPT Actions

## Installation

```bash
composer install --no-dev --optimize-autoloader
```

Configure the environment variable on your server:

```bash
HTML_EXPORT_API_KEY=""
```

Use a long random value as the environment variable value. Do not commit it to the repository.

Point your web server document root to `public/`.

For Apache, `public/.htaccess` routes all requests to `public/index.php`.

## Endpoints

```text
GET  /health
GET  /privacy
GET  /openapi.json
GET  /styles
POST /render
POST /render-chat
```

`/styles`, `/render`, and `/render-chat` require the `X-API-Key` header.

## Render a single answer

```bash
curl -X POST "https://your-domain.example/render" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: <value-from-your-server-config>" \
  -d '{
    "title": "Test page",
    "style": "documentation",
    "style_mode": "preset",
    "include_renderers": true,
    "markdown": "# Hello\n\nThis is **Markdown**."
  }'
```

## Render a chat transcript

```bash
curl -X POST "https://your-domain.example/render-chat" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: <value-from-your-server-config>" \
  -d '{
    "title": "Chat Export",
    "style": "clean",
    "style_mode": "preset",
    "include_toc": true,
    "messages": [
      {"role": "user", "content": "Can this be a Custom GPT?"},
      {"role": "assistant", "content": "Yes. Use a Custom GPT Action."}
    ]
  }'
```

## Custom CSS

Use `style_mode` to control how CSS is applied:

```text
preset        Built-in style only
custom_append Built-in style plus custom_css
custom_full   Minimal base CSS plus custom_css
```

Example:

```json
{
  "title": "Custom style",
  "style": "clean",
  "style_mode": "custom_append",
  "custom_css": ".content{max-width:1200px} h1{color:#7c2d12}",
  "markdown": "# Custom style\n\nRendered with additional CSS."
}
```

The API removes obvious dangerous CSS patterns such as `@import`, `javascript:` URLs, `data:text/html` URLs, old IE `expression()` and `behavior`.

## Custom GPT setup

In the GPT editor:

1. Create or edit your GPT.
2. Add an Action.
3. Import the schema from:

```text
https://your-domain.example/openapi.json
```

4. Configure authentication:

```text
Authentication: API Key
Auth Type: Custom Header
Custom Header Name: X-API-Key
API Key: value from your server configuration
```

5. Use your privacy URL:

```text
https://your-domain.example/privacy
```

Replace the privacy page text in `src/HtmlExportApi.php` before publishing publicly.

## Suggested GPT instructions

```text
You are a universal HTML exporter for ChatGPT answers and chat transcripts.

When the user wants to export a single answer as HTML, webpage, .htm, or standalone page, use renderStandaloneHtml.

When the user asks to export the whole chat, current chat, chat history, questions and answers, or conversation, use renderChatTranscript.

The API cannot access ChatGPT conversations by itself. You must pass the available conversation context as a messages array. If the available context may not contain the complete chat, say so honestly.

Default values:
- style = clean
- style_mode = preset
- include_renderers = true
- include_export_footer = true
- include_toc = true for chat transcripts

Allowed styles:
- clean
- authority
- documentation
- landing
- cards
- print

If the user provides CSS, pass it as custom_css. Use style_mode = custom_append unless the user says that the CSS should define the whole look; then use style_mode = custom_full.

After rendering, tell the user the filename. If file creation is available, provide a downloadable .htm file. Otherwise provide the returned HTML or base64 content.
```

## ChatGPT compatibility notes

The renderer supports common ChatGPT text output: Markdown, GFM tables, task lists, code blocks, Mermaid blocks, LaTeX-like formulas, Markdown images and common media links.

Interactive ChatGPT UI widgets such as product carousels, weather cards, stock widgets, navigation lists or generated UI references cannot be reproduced as live widgets. They are exported as readable placeholders.

Images generated by ChatGPT can only be embedded if the GPT passes them as a direct URL or data URL.

## Security notes

- Raw HTML from Markdown input is escaped.
- Script, style, object, embed and form tags are removed during post-processing.
- Event attributes and inline style attributes are removed from rendered content.
- Iframes are only generated for `www.youtube-nocookie.com/embed/...` and `player.vimeo.com/video/...`.
- Do not send confidential or personal content to a public deployment unless your own data processing rules allow it.
- Server access logs may still contain technical metadata.

## License

No license has been defined yet.
