<?php

declare(strict_types=1);

namespace ChatGptHtmlExport;

final class HtmlExportApi
{
    private const MAX_MARKDOWN_BYTES = 700000;
    private const MAX_CUSTOM_CSS_BYTES = 80000;
    private const MAX_CHAT_MESSAGES = 500;

    private MarkdownRenderer $renderer;
    private Styles $styles;

    public function __construct()
    {
        $this->renderer = new MarkdownRenderer();
        $this->styles = new Styles();
    }

    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if ($method === 'OPTIONS') {
            $this->sendCorsHeaders();
            http_response_code(204);
            return;
        }

        $this->sendCorsHeaders();

        if ($method === 'GET' && ($path === '/' || $path === '/health')) {
            $this->sendJson([
                'ok' => true,
                'service' => 'chatgpt-html-export-api',
                'endpoints' => ['GET /health', 'GET /styles', 'GET /openapi.json', 'GET /privacy', 'POST /render', 'POST /render-chat'],
            ]);
            return;
        }

        if ($method === 'GET' && $path === '/privacy') {
            $this->sendPrivacyPage();
            return;
        }

        if ($method === 'GET' && $path === '/openapi.json') {
            $this->sendJson($this->buildOpenApiSchema());
            return;
        }

        if ($method === 'GET' && $path === '/styles') {
            $this->requireApiKey();
            $this->sendJson([
                'ok' => true,
                'styles' => Styles::ALLOWED_STYLES,
                'style_modes' => Styles::ALLOWED_STYLE_MODES,
                'default_style' => 'clean',
                'default_style_mode' => 'preset',
            ]);
            return;
        }

        if ($method === 'POST' && $path === '/render') {
            $this->requireApiKey();
            $this->handleRender();
            return;
        }

        if ($method === 'POST' && $path === '/render-chat') {
            $this->requireApiKey();
            $this->handleRenderChat();
            return;
        }

        $this->sendJson(['ok' => false, 'error' => 'Not found'], 404);
    }

    private function handleRender(): void
    {
        $input = $this->readJsonBody();
        $markdown = trim((string) ($input['markdown'] ?? $input['text'] ?? ''));
        $title = trim((string) ($input['title'] ?? 'Exportierte ChatGPT-Antwort'));
        $style = $this->normalizeStyle((string) ($input['style'] ?? 'clean'));
        $styleMode = $this->normalizeStyleMode((string) ($input['style_mode'] ?? 'preset'));
        $customCss = (string) ($input['custom_css'] ?? '');
        $includeRenderers = (bool) ($input['include_renderers'] ?? true);
        $includeExportFooter = (bool) ($input['include_export_footer'] ?? true);

        if ($markdown === '') {
            $this->sendJson(['ok' => false, 'error' => 'Missing required field: markdown'], 400);
            return;
        }
        if (!$this->validatePayloadSizes($markdown, $customCss)) {
            return;
        }

        $contentHtml = $this->renderer->render($markdown);
        $this->sendRenderResponse(
            title: $title !== '' ? $title : 'Exportierte ChatGPT-Antwort',
            style: $style,
            styleMode: $styleMode,
            contentHtml: $contentHtml,
            customCss: $customCss,
            includeRenderers: $includeRenderers,
            includeExportFooter: $includeExportFooter,
            responseType: 'single'
        );
    }

    private function handleRenderChat(): void
    {
        $input = $this->readJsonBody();
        $messages = $input['messages'] ?? null;
        $title = trim((string) ($input['title'] ?? 'Chat-Export'));
        $style = $this->normalizeStyle((string) ($input['style'] ?? 'clean'));
        $styleMode = $this->normalizeStyleMode((string) ($input['style_mode'] ?? 'preset'));
        $customCss = (string) ($input['custom_css'] ?? '');
        $includeRenderers = (bool) ($input['include_renderers'] ?? true);
        $includeExportFooter = (bool) ($input['include_export_footer'] ?? true);
        $includeToc = (bool) ($input['include_toc'] ?? true);

        if (!is_array($messages) || $messages === []) {
            $this->sendJson(['ok' => false, 'error' => 'Missing required field: messages'], 400);
            return;
        }
        if (count($messages) > self::MAX_CHAT_MESSAGES) {
            $this->sendJson(['ok' => false, 'error' => 'Too many chat messages.', 'max_messages' => self::MAX_CHAT_MESSAGES], 413);
            return;
        }
        if (!$this->validateChatPayloadSizes($messages, $customCss)) {
            return;
        }

        $contentHtml = $this->renderChatTranscript($messages, $title !== '' ? $title : 'Chat-Export', $includeToc);
        $this->sendRenderResponse(
            title: $title !== '' ? $title : 'Chat-Export',
            style: $style,
            styleMode: $styleMode,
            contentHtml: $contentHtml,
            customCss: $customCss,
            includeRenderers: $includeRenderers,
            includeExportFooter: $includeExportFooter,
            responseType: 'chat'
        );
    }

    private function renderChatTranscript(array $messages, string $title, bool $includeToc): string
    {
        $sections = [];
        $tocItems = [];
        $messageNumber = 1;

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = $this->normalizeRole((string) ($message['role'] ?? 'unknown'));
            $content = trim((string) ($message['content'] ?? ''));
            $name = trim((string) ($message['name'] ?? ''));
            if ($content === '') {
                continue;
            }
            $label = $this->roleLabel($role, $name);
            $id = 'message-' . $messageNumber . '-' . $this->slugify($label);
            $tocItems[] = ['id' => $id, 'label' => $label, 'number' => $messageNumber];
            $sections[] = '<section class="chat-message chat-message-' . $this->escapeAttr($role) . '" id="' . $this->escapeAttr($id) . '">'
                . '<header class="chat-message-header"><p class="chat-message-meta">Nachricht ' . $messageNumber . '</p><h2>' . $this->escapeHtml($label) . '</h2></header>'
                . '<div class="chat-message-content">' . $this->renderer->render($content) . '</div>'
                . '</section>';
            $messageNumber++;
        }

        if ($sections === []) {
            return '<h1>' . $this->escapeHtml($title) . '</h1><p>Keine exportierbaren Nachrichten vorhanden.</p>';
        }

        $tocHtml = '';
        if ($includeToc) {
            $tocHtml = '<nav class="chat-toc" aria-label="Inhaltsverzeichnis"><h2>Inhaltsverzeichnis</h2><ol>';
            foreach ($tocItems as $item) {
                $tocHtml .= '<li><a href="#' . $this->escapeAttr($item['id']) . '">' . $item['number'] . '. ' . $this->escapeHtml($item['label']) . '</a></li>';
            }
            $tocHtml .= '</ol></nav>';
        }

        return '<h1>' . $this->escapeHtml($title) . '</h1><article class="chat-transcript">' . $tocHtml . implode("\n", $sections) . '</article>';
    }

    private function sendRenderResponse(string $title, string $style, string $styleMode, string $contentHtml, string $customCss, bool $includeRenderers, bool $includeExportFooter, string $responseType): void
    {
        $html = $this->buildStandaloneHtml($title, $style, $styleMode, $contentHtml, $customCss, $includeRenderers, $includeExportFooter);
        $filename = $this->slugify($title) . '.htm';
        $base64 = base64_encode($html);

        $this->sendJson([
            'ok' => true,
            'type' => $responseType,
            'filename' => $filename,
            'mime_type' => 'text/html; charset=utf-8',
            'style' => $style,
            'style_mode' => $styleMode,
            'html' => $html,
            'base64' => $base64,
            'data_url' => 'data:text/html;charset=utf-8;base64,' . $base64,
            'warnings' => [
                'The API can only export content that the Custom GPT sends in the request.',
                'External Mermaid and MathJax renderers require internet access if include_renderers is true.',
                'External media URLs require internet access.',
                'Raw HTML from Markdown input is escaped.',
                'Interactive ChatGPT widgets are exported as readable placeholders, not as live widgets.',
            ],
        ]);
    }

    private function buildStandaloneHtml(string $title, string $style, string $styleMode, string $contentHtml, string $customCss, bool $includeRenderers, bool $includeExportFooter): string
    {
        $baseCss = $styleMode === 'custom_full' ? $this->styles->getMinimalBaseCss() : $this->styles->getStyleCss($style);
        $safeCustomCss = in_array($styleMode, ['custom_append', 'custom_full'], true) ? $this->sanitizeCss($customCss) : '';
        $headerHtml = ($style === 'authority' && $styleMode !== 'custom_full')
            ? '<header class="site-header"><div class="site-header-inner"><p class="site-kicker">Standalone-Webseite</p><p class="site-title">' . $this->escapeHtml($title) . '</p></div></header>'
            : '';
        $footerHtml = $includeExportFooter
            ? '<footer class="export-footer">Exportiert am ' . $this->escapeHtml((new \DateTimeImmutable('now'))->format('d.m.Y')) . '.</footer>'
            : '';

        return '<!doctype html>' . "\n"
            . '<html lang="de">' . "\n<head>\n  <meta charset=\"utf-8\">\n  <meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n"
            . '  <title>' . $this->escapeHtml($title) . "</title>\n  <style>\n"
            . $baseCss . "\n" . $this->styles->getCommonCss() . "\n" . $this->styles->getChatCss() . "\n" . $safeCustomCss . "\n  </style>\n</head>\n<body>\n"
            . $headerHtml . "\n  <main class=\"page\">\n    <article class=\"content\">\n"
            . $contentHtml . "\n    </article>\n" . $footerHtml . "\n  </main>\n"
            . ($includeRenderers ? $this->buildRendererScripts($contentHtml) : '') . "\n</body>\n</html>";
    }

    private function buildRendererScripts(string $contentHtml): string
    {
        $scripts = [];
        if (str_contains($contentHtml, 'class="math-inline"') || str_contains($contentHtml, 'class="math-block"')) {
            $scripts[] = '<script>window.MathJax={tex:{inlineMath:[[\'\\\\(\',\'\\\\)\']],displayMath:[[\'\\\\[\',\'\\\\]\']]},svg:{fontCache:\'global\'}};</script><script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js"></script>';
        }
        if (str_contains($contentHtml, 'class="mermaid"')) {
            $scripts[] = '<script type="module">import mermaid from "https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs";mermaid.initialize({startOnLoad:true,securityLevel:"strict"});</script>';
        }
        return implode("\n", $scripts);
    }

    private function validatePayloadSizes(string $markdown, string $customCss): bool
    {
        if (strlen($markdown) > self::MAX_MARKDOWN_BYTES) {
            $this->sendJson(['ok' => false, 'error' => 'Markdown input is too large.', 'max_bytes' => self::MAX_MARKDOWN_BYTES], 413);
            return false;
        }
        if (strlen($customCss) > self::MAX_CUSTOM_CSS_BYTES) {
            $this->sendJson(['ok' => false, 'error' => 'Custom CSS is too large.', 'max_bytes' => self::MAX_CUSTOM_CSS_BYTES], 413);
            return false;
        }
        return true;
    }

    private function validateChatPayloadSizes(array $messages, string $customCss): bool
    {
        if (strlen($customCss) > self::MAX_CUSTOM_CSS_BYTES) {
            $this->sendJson(['ok' => false, 'error' => 'Custom CSS is too large.', 'max_bytes' => self::MAX_CUSTOM_CSS_BYTES], 413);
            return false;
        }
        $totalBytes = 0;
        foreach ($messages as $message) {
            if (is_array($message)) {
                $totalBytes += strlen((string) ($message['content'] ?? ''));
            }
            if ($totalBytes > self::MAX_MARKDOWN_BYTES) {
                $this->sendJson(['ok' => false, 'error' => 'Chat transcript is too large.', 'max_bytes' => self::MAX_MARKDOWN_BYTES], 413);
                return false;
            }
        }
        return true;
    }

    private function readJsonBody(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            $this->sendJson(['ok' => false, 'error' => 'Invalid JSON body'], 400);
            exit;
        }
        return $data;
    }

    private function requireApiKey(): void
    {
        $configuredKey = getenv('HTML_EXPORT_API_KEY') ?: '';
        if ($configuredKey === '') {
            $this->sendJson(['ok' => false, 'error' => 'Server is missing HTML_EXPORT_API_KEY.'], 500);
            exit;
        }
        if (!hash_equals($configuredKey, $_SERVER['HTTP_X_API_KEY'] ?? '')) {
            $this->sendJson(['ok' => false, 'error' => 'Unauthorized'], 401);
            exit;
        }
    }

    private function sendCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, ['https://chatgpt.com', 'https://chat.openai.com'], true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    }

    private function sendJson(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function sendPrivacyPage(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privacy Policy</title><style>body{font-family:system-ui,sans-serif;line-height:1.6;max-width:860px;margin:0 auto;padding:2rem}</style></head><body><h1>Privacy Policy</h1><p>This API receives text content sent by a Custom GPT and converts it into standalone HTML.</p><p>The API does not require personal accounts and does not intentionally store submitted content.</p><p>Server access logs may contain technical metadata such as IP address, timestamp, user agent and requested path.</p><p>Do not send confidential, personal, sensitive or legally protected content unless you are authorized to process it through this service.</p><p>Contact: replace-this-with-your-contact@example.org</p></body></html>';
    }

    private function buildOpenApiSchema(): array
    {
        $serverUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'example.org');
        return [
            'openapi' => '3.1.0',
            'info' => ['title' => 'ChatGPT HTML Export API', 'version' => '1.2.0', 'description' => 'Renders ChatGPT answers or chat transcripts into standalone HTML pages.'],
            'servers' => [['url' => $serverUrl]],
            'components' => [
                'securitySchemes' => ['ApiKeyAuth' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key']],
                'schemas' => [
                    'RenderRequest' => ['type' => 'object', 'required' => ['markdown'], 'properties' => array_merge(['markdown' => ['type' => 'string']], $this->commonRequestProperties())],
                    'ChatMessage' => ['type' => 'object', 'required' => ['role', 'content'], 'properties' => ['role' => ['type' => 'string', 'enum' => ['user', 'assistant', 'system', 'tool', 'unknown']], 'name' => ['type' => 'string'], 'content' => ['type' => 'string']]],
                    'RenderChatRequest' => ['type' => 'object', 'required' => ['messages'], 'properties' => array_merge(['messages' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ChatMessage']], 'include_toc' => ['type' => 'boolean', 'default' => true]], $this->commonRequestProperties())],
                    'RenderResponse' => ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean'], 'type' => ['type' => 'string'], 'filename' => ['type' => 'string'], 'mime_type' => ['type' => 'string'], 'style' => ['type' => 'string'], 'style_mode' => ['type' => 'string'], 'html' => ['type' => 'string'], 'base64' => ['type' => 'string'], 'data_url' => ['type' => 'string'], 'warnings' => ['type' => 'array', 'items' => ['type' => 'string']]]],
                ],
            ],
            'security' => [['ApiKeyAuth' => []]],
            'paths' => [
                '/render' => ['post' => ['operationId' => 'renderStandaloneHtml', 'summary' => 'Render a single ChatGPT answer into standalone HTML', 'security' => [['ApiKeyAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RenderRequest']]]], 'responses' => ['200' => ['description' => 'Rendered HTML', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RenderResponse']]]]]]],
                '/render-chat' => ['post' => ['operationId' => 'renderChatTranscript', 'summary' => 'Render an available chat transcript into standalone HTML', 'description' => 'The GPT must pass the available chat messages as an array. The API cannot access ChatGPT conversations by itself.', 'security' => [['ApiKeyAuth' => []]], 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RenderChatRequest']]]], 'responses' => ['200' => ['description' => 'Rendered chat transcript HTML', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RenderResponse']]]]]]],
                '/styles' => ['get' => ['operationId' => 'listHtmlStyles', 'summary' => 'List available HTML styles and style modes', 'security' => [['ApiKeyAuth' => []]], 'responses' => ['200' => ['description' => 'Available styles']]]],
            ],
        ];
    }

    private function commonRequestProperties(): array
    {
        return [
            'title' => ['type' => 'string', 'default' => 'Exportierte ChatGPT-Antwort'],
            'style' => ['type' => 'string', 'enum' => Styles::ALLOWED_STYLES, 'default' => 'clean'],
            'style_mode' => ['type' => 'string', 'enum' => Styles::ALLOWED_STYLE_MODES, 'default' => 'preset', 'description' => 'preset uses only the built-in style. custom_append adds custom_css. custom_full uses minimal base CSS plus custom_css.'],
            'custom_css' => ['type' => 'string', 'description' => 'Optional CSS for the exported HTML. Do not include scripts, imports, tracking or external resources.'],
            'include_renderers' => ['type' => 'boolean', 'default' => true, 'description' => 'If true, include Mermaid and MathJax CDN scripts when needed.'],
            'include_export_footer' => ['type' => 'boolean', 'default' => true],
        ];
    }

    private function normalizeStyle(string $style): string
    {
        return in_array(trim($style), Styles::ALLOWED_STYLES, true) ? trim($style) : 'clean';
    }

    private function normalizeStyleMode(string $styleMode): string
    {
        return in_array(trim($styleMode), Styles::ALLOWED_STYLE_MODES, true) ? trim($styleMode) : 'preset';
    }

    private function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        return in_array($role, ['user', 'assistant', 'system', 'tool'], true) ? $role : 'unknown';
    }

    private function roleLabel(string $role, string $name): string
    {
        if ($name !== '') {
            return $name;
        }
        return match ($role) { 'user' => 'Nutzer', 'assistant' => 'ChatGPT', 'system' => 'System', 'tool' => 'Tool', default => 'Unbekannt' };
    }

    private function sanitizeCss(string $css): string
    {
        $css = str_ireplace('</style', '<\/style', $css);
        $css = preg_replace('~@import\b[^;]+;?~i', '/* removed import */', $css) ?? $css;
        $css = preg_replace('~url\s*\(\s*([\'\"]?)\s*javascript:[^)]+\)~i', 'url("")', $css) ?? $css;
        $css = preg_replace('~url\s*\(\s*([\'\"]?)\s*data:text/html[^)]+\)~i', 'url("")', $css) ?? $css;
        $css = preg_replace('~expression\s*\([^)]*\)~i', '/* removed expression */', $css) ?? $css;
        return preg_replace('~behavior\s*:~i', '/* removed behavior: */', $css) ?? $css;
    }

    private function slugify(string $value): string
    {
        $value = trim(strtolower($value));
        if (function_exists('iconv')) {
            $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        }
        $value = trim(preg_replace('~[^a-z0-9]+~', '-', $value) ?? '', '-');
        return $value !== '' ? substr($value, 0, 80) : 'chatgpt-antwort';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function escapeAttr(string $value): string
    {
        return str_replace('`', '&#096;', $this->escapeHtml($value));
    }
}
