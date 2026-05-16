<?php

declare(strict_types=1);

namespace ChatGptHtmlExport;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

final class MarkdownRenderer
{
    private const IMAGE_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.avif', '.svg'];
    private const VIDEO_EXTENSIONS = ['.mp4', '.webm', '.ogv', '.ogg', '.mov', '.m4v'];
    private const AUDIO_EXTENSIONS = ['.mp3', '.wav', '.ogg', '.oga', '.m4a', '.aac', '.flac'];

    public function render(string $markdown): string
    {
        $markdown = $this->normalizeChatGptText($markdown);
        $specialState = $this->extractSpecialChatGptTokens($markdown);
        $mathState = $this->extractMath($specialState['markdown']);

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'max_delimiters_per_line' => 150,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $converter = new MarkdownConverter($environment);
        $html = (string) $converter->convert($mathState['markdown']);
        $html = $this->restoreMath($html, $mathState);
        $html = $this->restoreSpecialChatGptTokens($html, $specialState);
        $html = $this->convertMermaidFences($html);
        $html = $this->enhanceMediaBlocks($html);
        $html = $this->convertCallouts($html);
        return $this->postSanitizeHtml($html);
    }

    private function normalizeChatGptText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return preg_replace_callback('~:::writing\{([^\n]*)\}\n(.*?)\n:::~su', function (array $match): string {
            $meta = trim($match[1]);
            $content = trim($match[2]);
            $label = 'Schreibblock';
            if (preg_match('~variant="([^"]+)"~', $meta, $variantMatch)) {
                $label .= ': ' . $variantMatch[1];
            }
            return "\n\n> [!NOTE]\n> **" . $label . "**\n> \n> " . str_replace("\n", "\n> ", $content) . "\n\n";
        }, $text) ?? $text;
    }

    private function extractSpecialChatGptTokens(string $markdown): array
    {
        $tokens = [];
        $patterns = [
            '~cite([^]+)~u' => 'Quelle',
            '~filecite([^]+)~u' => 'Dateiquelle',
            '~(?:forecast|schedule|standing|finance|products|navlist|i|genui)([^]+)~u' => 'Interaktives ChatGPT-Element',
        ];
        foreach ($patterns as $pattern => $label) {
            $markdown = preg_replace_callback($pattern, function (array $match) use (&$tokens, $label): string {
                $token = 'CHATGPTSPECIALTOKEN' . count($tokens) . 'X';
                $tokens[$token] = ['label' => $label, 'raw' => $match[0]];
                return $token;
            }, $markdown) ?? $markdown;
        }
        return ['markdown' => $markdown, 'tokens' => $tokens];
    }

    private function restoreSpecialChatGptTokens(string $html, array $specialState): string
    {
        foreach (($specialState['tokens'] ?? []) as $token => $data) {
            $replacement = '<span class="chatgpt-special-token" title="' . $this->escapeAttr((string) $data['raw']) . '">' . $this->escapeHtml((string) $data['label']) . '</span>';
            $html = str_replace($token, $replacement, $html);
        }
        return $html;
    }

    private function extractMath(string $markdown): array
    {
        $lines = preg_split("~\r\n|\n|\r~", $markdown) ?: [];
        $resultLines = [];
        $items = [];
        $inFence = false;
        $fenceMarker = '';

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (preg_match('~^\s*(```+|~~~+)~', $line, $match)) {
                $marker = substr($match[1], 0, 3);
                if (!$inFence) {
                    $inFence = true;
                    $fenceMarker = $marker;
                } elseif (str_starts_with(trim($line), $fenceMarker)) {
                    $inFence = false;
                    $fenceMarker = '';
                }
                $resultLines[] = $line;
                continue;
            }
            if ($inFence) {
                $resultLines[] = $line;
                continue;
            }
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '$$')) {
                $content = '';
                if (preg_match('~^\$\$(.+)\$\$\s*$~s', $trimmed, $singleLineMatch)) {
                    $content = trim($singleLineMatch[1]);
                } else {
                    $content = trim(substr($trimmed, 2));
                    while (($i + 1) < count($lines)) {
                        $i++;
                        if (preg_match('~^(.*?)\$\$\s*$~', $lines[$i], $endMatch)) {
                            $content .= "\n" . $endMatch[1];
                            break;
                        }
                        $content .= "\n" . $lines[$i];
                    }
                }
                $token = 'MATHBLOCKTOKEN' . count($items) . 'X';
                $items[$token] = trim($content);
                $resultLines[] = $token;
                continue;
            }
            $resultLines[] = preg_replace_callback('~(?<!\\\\)\$(?!\s)([^$\n]*?\S)(?<!\\\\)\$~', function (array $match) use (&$items): string {
                $token = 'MATHINLINETOKEN' . count($items) . 'X';
                $items[$token] = $match[1];
                return $token;
            }, $line) ?? $line;
        }
        return ['markdown' => implode("\n", $resultLines), 'math' => $items];
    }

    private function restoreMath(string $html, array $mathState): string
    {
        foreach (($mathState['math'] ?? []) as $token => $formula) {
            if (str_starts_with($token, 'MATHBLOCKTOKEN')) {
                $replacement = '<div class="math-block">\\[' . $this->escapeHtml($formula) . '\\]</div>';
                $html = str_replace('<p>' . $token . '</p>', $replacement, $html);
                $html = str_replace($token, $replacement, $html);
            } else {
                $html = str_replace($token, '<span class="math-inline">\\(' . $this->escapeHtml($formula) . '\\)</span>', $html);
            }
        }
        return $html;
    }

    private function convertMermaidFences(string $html): string
    {
        return preg_replace_callback('~<pre><code class="language-mermaid">(.*?)</code></pre>~s', static function (array $match): string {
            return '<pre class="mermaid">' . $match[1] . '</pre>';
        }, $html) ?? $html;
    }

    private function enhanceMediaBlocks(string $html): string
    {
        $dom = $this->loadHtmlFragment($html);
        $xpath = new DOMXPath($dom);
        foreach (iterator_to_array($xpath->query('//p') ?: []) as $paragraph) {
            if (!$paragraph instanceof DOMElement) {
                continue;
            }
            $link = $this->getSingleChildLink($paragraph);
            if (!$link) {
                continue;
            }
            $href = $link->getAttribute('href');
            $label = trim($link->textContent);
            $mediaHtml = $this->renderMediaHtml($href, $label === $href ? '' : $label);
            if ($mediaHtml !== '') {
                $this->replaceNodeWithHtml($paragraph, $mediaHtml, $dom);
            }
        }
        foreach (iterator_to_array($xpath->query('//img') ?: []) as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }
            $src = $image->getAttribute('src');
            $alt = $image->getAttribute('alt');
            if (!$this->isSafeImageUrl($src)) {
                $image->parentNode?->removeChild($image);
                continue;
            }
            $mediaHtml = $this->renderMediaHtml($src, $alt);
            if ($mediaHtml !== '') {
                $this->replaceNodeWithHtml($image, $mediaHtml, $dom);
            }
        }
        return $this->saveRootChildren($dom);
    }

    private function convertCallouts(string $html): string
    {
        $dom = $this->loadHtmlFragment($html);
        $xpath = new DOMXPath($dom);
        foreach (iterator_to_array($xpath->query('//blockquote') ?: []) as $blockquote) {
            if (!$blockquote instanceof DOMElement) {
                continue;
            }
            $firstParagraph = null;
            foreach ($blockquote->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'p') {
                    $firstParagraph = $child;
                    break;
                }
            }
            if (!$firstParagraph || !preg_match('~^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]~i', trim($firstParagraph->textContent), $match)) {
                continue;
            }
            $type = strtolower($match[1]);
            $blockquote->setAttribute('class', trim($blockquote->getAttribute('class') . ' callout callout-' . $type));
            $newText = trim(preg_replace('~^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*~i', '', trim($firstParagraph->textContent)) ?? '');
            if ($newText === '') {
                $firstParagraph->parentNode?->removeChild($firstParagraph);
            } else {
                while ($firstParagraph->firstChild) {
                    $firstParagraph->removeChild($firstParagraph->firstChild);
                }
                $firstParagraph->appendChild($dom->createTextNode($newText));
            }
        }
        return $this->saveRootChildren($dom);
    }

    private function postSanitizeHtml(string $html): string
    {
        $dom = $this->loadHtmlFragment($html);
        $xpath = new DOMXPath($dom);
        foreach (['script', 'style', 'object', 'embed', 'form'] as $tag) {
            foreach (iterator_to_array($xpath->query('//' . $tag) ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        foreach (iterator_to_array($xpath->query('//*') ?: []) as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }
            $this->removeEventAttributes($element);
            $this->cleanElementByType($element);
        }
        return $this->saveRootChildren($dom);
    }

    private function removeEventAttributes(DOMElement $element): void
    {
        $attributesToRemove = [];
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || $name === 'style') {
                $attributesToRemove[] = $attribute->name;
            }
        }
        foreach ($attributesToRemove as $attributeName) {
            $element->removeAttribute($attributeName);
        }
    }

    private function cleanElementByType(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        if ($tag === 'a') {
            $href = $element->getAttribute('href');
            if (!$this->isSafeLinkUrl($href)) {
                $element->removeAttribute('href');
            } else {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
            return;
        }
        if ($tag === 'img' && !$this->isSafeImageUrl($element->getAttribute('src'))) {
            $element->parentNode?->removeChild($element);
            return;
        }
        if ($tag === 'video' && !$this->isSafeVideoUrl($element->getAttribute('src'))) {
            $element->parentNode?->removeChild($element);
            return;
        }
        if ($tag === 'audio' && !$this->isSafeAudioUrl($element->getAttribute('src'))) {
            $element->parentNode?->removeChild($element);
            return;
        }
        if ($tag === 'iframe' && !$this->isSafeIframeSrc($element->getAttribute('src'))) {
            $element->parentNode?->removeChild($element);
            return;
        }
        if ($tag === 'input') {
            if (strtolower($element->getAttribute('type')) !== 'checkbox') {
                $element->parentNode?->removeChild($element);
                return;
            }
            $element->setAttribute('disabled', 'disabled');
        }
    }

    private function renderMediaHtml(string $url, string $label): string
    {
        $url = $this->stripUrlNoise($url);
        $safeLabel = trim($label);
        $caption = $safeLabel !== '' ? '<figcaption>' . $this->escapeHtml($safeLabel) . '</figcaption>' : '';
        $youtubeId = $this->getYoutubeId($url);
        if ($youtubeId !== '') {
            return '<div class="media-embed"><iframe src="https://www.youtube-nocookie.com/embed/' . $this->escapeAttr($youtubeId) . '" title="' . $this->escapeAttr($safeLabel !== '' ? $safeLabel : 'YouTube-Video') . '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen="allowfullscreen"></iframe>' . $caption . '</div>';
        }
        $vimeoId = $this->getVimeoId($url);
        if ($vimeoId !== '') {
            return '<div class="media-embed"><iframe src="https://player.vimeo.com/video/' . $this->escapeAttr($vimeoId) . '" title="' . $this->escapeAttr($safeLabel !== '' ? $safeLabel : 'Vimeo-Video') . '" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen="allowfullscreen"></iframe>' . $caption . '</div>';
        }
        if ($this->isSafeImageUrl($url)) {
            return '<figure><img src="' . $this->escapeAttr($url) . '" alt="' . $this->escapeAttr($safeLabel) . '" loading="lazy">' . $caption . '</figure>';
        }
        if ($this->isSafeVideoUrl($url)) {
            return '<div class="media-embed"><video controls="controls" preload="metadata" src="' . $this->escapeAttr($url) . '"></video>' . $caption . '</div>';
        }
        if ($this->isSafeAudioUrl($url)) {
            return '<div class="media-embed"><audio controls="controls" preload="metadata" src="' . $this->escapeAttr($url) . '"></audio>' . $caption . '</div>';
        }
        return '';
    }

    private function loadHtmlFragment(string $html): DOMDocument
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        return $dom;
    }

    private function replaceNodeWithHtml(DOMNode $node, string $html, DOMDocument $targetDom): void
    {
        $sourceDom = $this->loadHtmlFragment($html);
        $sourceRoot = $sourceDom->getElementById('root');
        if (!$sourceRoot || !$node->parentNode) {
            return;
        }
        $fragment = $targetDom->createDocumentFragment();
        foreach ($sourceRoot->childNodes as $child) {
            $fragment->appendChild($targetDom->importNode($child, true));
        }
        $node->parentNode->replaceChild($fragment, $node);
    }

    private function saveRootChildren(DOMDocument $dom): string
    {
        $root = $dom->getElementById('root');
        if (!$root) {
            return '';
        }
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        return $result;
    }

    private function getSingleChildLink(DOMElement $paragraph): ?DOMElement
    {
        $meaningfulNodes = [];
        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof DOMText && trim($child->textContent) === '') {
                continue;
            }
            $meaningfulNodes[] = $child;
        }
        if (count($meaningfulNodes) !== 1) {
            return null;
        }
        $onlyChild = $meaningfulNodes[0];
        return ($onlyChild instanceof DOMElement && strtolower($onlyChild->tagName) === 'a') ? $onlyChild : null;
    }

    private function isSafeLinkUrl(string $url): bool
    {
        $url = trim($url);
        $lowerUrl = strtolower($url);
        return str_starts_with($url, '#') || str_starts_with($lowerUrl, 'mailto:') || $this->isHttpUrl($url);
    }

    private function isSafeImageUrl(string $url): bool
    {
        return $this->isDataImageUrl($url) || ($this->isHttpUrl($url) && $this->hasExtension($url, self::IMAGE_EXTENSIONS));
    }

    private function isSafeVideoUrl(string $url): bool
    {
        return $this->isHttpUrl($url) && $this->hasExtension($url, self::VIDEO_EXTENSIONS);
    }

    private function isSafeAudioUrl(string $url): bool
    {
        return $this->isHttpUrl($url) && $this->hasExtension($url, self::AUDIO_EXTENSIONS);
    }

    private function isSafeIframeSrc(string $url): bool
    {
        $parts = $this->parseUrl($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        return ($host === 'www.youtube-nocookie.com' && str_starts_with($path, '/embed/')) || ($host === 'player.vimeo.com' && str_starts_with($path, '/video/'));
    }

    private function isHttpUrl(string $url): bool
    {
        $parts = $this->parseUrl($url);
        if (!$parts) {
            return false;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        return $scheme === 'https' || $scheme === 'http';
    }

    private function isDataImageUrl(string $url): bool
    {
        return (bool) preg_match('~^data:image/(?:png|jpeg|jpg|gif|webp|avif);base64,[a-z0-9+/=\s]+$~i', trim($url));
    }

    private function hasExtension(string $url, array $extensions): bool
    {
        $parts = $this->parseUrl($url);
        $path = strtolower((string) ($parts['path'] ?? $url));
        foreach ($extensions as $extension) {
            if (str_ends_with($path, $extension)) {
                return true;
            }
        }
        return false;
    }

    private function getYoutubeId(string $url): string
    {
        $parts = $this->parseUrl($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https') {
            return '';
        }
        $host = strtolower(preg_replace('~^www\.~', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);
        if ($host === 'youtu.be') {
            return $this->safeMediaId(trim($path, '/'));
        }
        if (str_ends_with($host, 'youtube.com')) {
            if ($path === '/watch') {
                return $this->safeMediaId((string) ($query['v'] ?? ''));
            }
            if (str_starts_with($path, '/embed/') || str_starts_with($path, '/shorts/')) {
                $segments = explode('/', trim($path, '/'));
                return $this->safeMediaId($segments[1] ?? '');
            }
        }
        return '';
    }

    private function getVimeoId(string $url): string
    {
        $parts = $this->parseUrl($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https') {
            return '';
        }
        $host = strtolower(preg_replace('~^www\.~', '', $parts['host'] ?? ''));
        if (!str_ends_with($host, 'vimeo.com')) {
            return '';
        }
        return preg_match('~/(\d+)~', $parts['path'] ?? '', $match) ? $match[1] : '';
    }

    private function safeMediaId(string $value): string
    {
        return preg_match('~^[a-zA-Z0-9_-]+$~', $value) ? $value : '';
    }

    private function parseUrl(string $url): ?array
    {
        $parts = parse_url($this->stripUrlNoise($url));
        return is_array($parts) ? $parts : null;
    }

    private function stripUrlNoise(string $url): string
    {
        return preg_replace('~[),.;!?]+$~', '', trim($url)) ?? trim($url);
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
