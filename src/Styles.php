<?php

declare(strict_types=1);

namespace ChatGptHtmlExport;

final class Styles
{
    public const ALLOWED_STYLES = ['clean', 'authority', 'documentation', 'landing', 'cards', 'print'];
    public const ALLOWED_STYLE_MODES = ['preset', 'custom_append', 'custom_full'];

    public function getStyleCss(string $style): string
    {
        return match ($style) {
            'authority' => $this->authorityCss(),
            'documentation' => $this->documentationCss(),
            'landing' => $this->landingCss(),
            'cards' => $this->cardsCss(),
            'print' => $this->printCss(),
            default => $this->cleanCss(),
        };
    }

    public function getMinimalBaseCss(): string
    {
        return <<<'CSS'
:root{--text:#172033;--muted:#667085;--border:#d8e0eb;--accent:#285ea8}
body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.6;color:var(--text);background:#fff}
.page{max-width:960px;margin:0 auto;padding:clamp(1rem,4vw,4rem)}
.content{max-width:100%}
a{color:var(--accent)}
pre{overflow:auto}
code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid var(--border);padding:.65rem;vertical-align:top}
CSS;
    }

    public function getCommonCss(): string
    {
        return <<<'CSS'
img,video,iframe{max-width:100%}
figure{margin:1.5rem 0}
figure img{display:block;width:auto;max-width:100%;height:auto;border-radius:12px}
figcaption{margin-top:.55rem;color:var(--muted,#667085);font-size:.92rem}
.media-embed{margin:1.5rem 0}
.media-embed iframe{display:block;width:100%;aspect-ratio:16/9;height:auto;border:0;border-radius:14px;background:#000}
.media-embed video{display:block;width:100%;border-radius:14px;background:#000}
.media-embed audio{display:block;width:100%}
.mermaid{margin:1.5rem 0;padding:1rem;overflow:auto;border-radius:14px;background:rgba(127,127,127,.08)}
.math-block{margin:1.5rem 0;overflow-x:auto}
.contains-task-list{list-style:none;padding-left:0}
.task-list-item{display:flex;gap:.5rem;align-items:flex-start}
.task-list-item input{margin-top:.35rem}
.export-footer{margin-top:2rem;color:inherit;opacity:.68;font-size:.9rem}
.callout{padding:1rem;border-radius:14px;border-left:.4rem solid var(--accent,#285ea8);background:rgba(127,127,127,.08)}
.callout-note{border-left-color:#285ea8}.callout-tip{border-left-color:#1f6b3a}.callout-important{border-left-color:#6d28d9}.callout-warning,.callout-caution{border-left-color:#b45309}
.chatgpt-special-token{display:inline-block;padding:.1rem .38rem;border:1px solid var(--border,#d8e0eb);border-radius:999px;background:rgba(127,127,127,.08);font-size:.85em;color:var(--muted,#667085)}
CSS;
    }

    public function getChatCss(): string
    {
        return <<<'CSS'
.chat-transcript{display:grid;gap:1.25rem}
.chat-toc{margin:1.5rem 0;padding:1rem;border:1px solid var(--border,#d8e0eb);border-radius:14px;background:rgba(127,127,127,.06)}
.chat-toc h2{margin-top:0}.chat-toc ol{margin-bottom:0}
.chat-message{margin:1.5rem 0;padding:1rem;border:1px solid var(--border,#d8e0eb);border-radius:16px;background:rgba(255,255,255,.04)}
.chat-message-header{margin-bottom:.75rem;padding-bottom:.5rem;border-bottom:1px solid var(--border,#d8e0eb)}
.chat-message-header h2{margin:.15rem 0 0}.chat-message-meta{margin:0;color:var(--muted,#667085);font-size:.9rem}
.chat-message-user{border-left:6px solid #285ea8}.chat-message-assistant{border-left:6px solid #1f6b3a}.chat-message-system,.chat-message-tool{border-left:6px solid #8a5a00}
.chat-message-content>:first-child{margin-top:0}.chat-message-content>:last-child{margin-bottom:0}
CSS;
    }

    private function cleanCss(): string
    {
        return <<<'CSS'
:root{--bg:#f6f8fb;--surface:#fff;--text:#172033;--muted:#617089;--accent:#285ea8;--border:#d8e0eb}
body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.65;color:var(--text);background:var(--bg)}
.page{max-width:940px;margin:0 auto;padding:clamp(1.25rem,4vw,4rem)}
.content{background:var(--surface);border:1px solid var(--border);border-radius:22px;padding:clamp(1.25rem,4vw,3rem);box-shadow:0 20px 50px rgba(23,32,51,.08)}
h1,h2,h3,h4{line-height:1.2;color:#111827}h1{font-size:clamp(2rem,6vw,4rem);margin-top:0}h2{margin-top:2.4rem;padding-top:.4rem;border-top:1px solid var(--border)}
a{color:var(--accent);text-decoration-thickness:.12em;text-underline-offset:.18em}code{padding:.1rem .3rem;border-radius:.35rem;background:#eef2f7;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
pre{overflow:auto;padding:1rem;border-radius:14px;background:#101827;color:#f8fafc}pre code{padding:0;background:transparent;color:inherit}
blockquote{margin:1.5rem 0;padding:.2rem 1rem;border-left:.35rem solid var(--accent);color:var(--muted);background:#f1f5fb}
table{width:100%;border-collapse:collapse;margin:1.5rem 0}th,td{border:1px solid var(--border);padding:.65rem;vertical-align:top}th{background:#eef2f7;text-align:left}
CSS;
    }

    private function authorityCss(): string
    {
        return <<<'CSS'
:root{--blue:#004b8d;--light-blue:#e8f1fa;--text:#1b1f24;--border:#ccd6e0;--muted:#536070}
body{margin:0;font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:var(--text);background:#f4f6f8}.site-header{background:var(--blue);color:#fff;border-bottom:6px solid #8db9df}.site-header-inner{max-width:1100px;margin:0 auto;padding:1.5rem clamp(1rem,3vw,2rem)}.site-kicker{margin:0 0 .4rem;font-weight:700;letter-spacing:.02em}.site-title{margin:0;font-size:clamp(1.7rem,4vw,3rem);line-height:1.15}
.page{max-width:1100px;margin:0 auto;padding:clamp(1rem,3vw,2.5rem)}.content{background:#fff;border:1px solid var(--border);padding:clamp(1rem,3vw,2rem)}h1{display:none}h2{margin-top:2.2rem;padding-bottom:.35rem;border-bottom:3px solid var(--blue);color:var(--blue)}h3{color:#254766}a{color:var(--blue);font-weight:700}code{background:var(--light-blue);padding:.12rem .35rem;border-radius:.25rem}pre{overflow:auto;padding:1rem;border-left:6px solid var(--blue);background:#f7f9fb}pre code{background:transparent;padding:0}blockquote{margin:1.25rem 0;padding:.9rem 1rem;background:var(--light-blue);border-left:6px solid var(--blue)}table{width:100%;border-collapse:collapse;margin:1.5rem 0}th,td{border:1px solid var(--border);padding:.65rem;text-align:left;vertical-align:top}th{background:var(--light-blue)}
CSS;
    }

    private function documentationCss(): string
    {
        return <<<'CSS'
:root{--bg:#0f172a;--text:#e5e7eb;--muted:#a5b4fc;--border:#334155;--accent:#93c5fd}
body{margin:0;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.7;color:var(--text);background:var(--bg)}.page{max-width:1120px;margin:0 auto;padding:clamp(1rem,4vw,4rem)}.content{background:linear-gradient(180deg,rgba(17,24,39,.98),rgba(15,23,42,.98));border:1px solid var(--border);border-radius:20px;padding:clamp(1.2rem,4vw,3rem)}h1,h2,h3{line-height:1.2}h1{font-size:clamp(2rem,6vw,4.6rem);margin-top:0;color:#fff}h2{margin-top:2.4rem;color:var(--accent)}h3{color:var(--muted)}a{color:var(--accent)}code{color:#fef3c7;background:#1f2937;border:1px solid #374151;padding:.12rem .35rem;border-radius:.35rem}pre{overflow:auto;padding:1rem;border-radius:14px;background:#020617;border:1px solid #1e293b}pre code{border:0;padding:0;color:#e5e7eb;background:transparent}blockquote{margin:1.5rem 0;padding:.5rem 1rem;border-left:.35rem solid var(--accent);background:rgba(147,197,253,.08)}table{width:100%;border-collapse:collapse;margin:1.5rem 0}th,td{border:1px solid var(--border);padding:.65rem;vertical-align:top}th{background:#1e293b}
CSS;
    }

    private function landingCss(): string
    {
        return <<<'CSS'
:root{--text:#1f2937;--accent:#ea580c;--accent2:#2563eb;--border:rgba(31,41,55,.14);--muted:#596273}body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.65;color:var(--text);background:radial-gradient(circle at top left,#fff7ed,transparent 42%),radial-gradient(circle at top right,#eff6ff,transparent 45%),#fff}.page{max-width:1180px;margin:0 auto;padding:clamp(1.25rem,4vw,5rem)}.content{background:rgba(255,255,255,.84);border:1px solid var(--border);border-radius:32px;padding:clamp(1.4rem,5vw,4rem);box-shadow:0 28px 70px rgba(31,41,55,.12)}h1{margin-top:0;max-width:12ch;font-size:clamp(2.6rem,8vw,6.5rem);line-height:.95;letter-spacing:-.06em}h2{margin-top:3rem;font-size:clamp(1.5rem,3.5vw,2.7rem)}h2::before{content:"";display:block;width:4rem;height:.35rem;margin-bottom:.7rem;border-radius:999px;background:linear-gradient(90deg,var(--accent),var(--accent2))}a{color:var(--accent2);font-weight:800}code{background:#fff7ed;color:#9a3412;padding:.12rem .35rem;border-radius:.35rem}pre{overflow:auto;padding:1rem;border-radius:18px;color:#fff;background:#1f2937}pre code{padding:0;color:inherit;background:transparent}blockquote{margin:1.5rem 0;padding:1rem 1.2rem;border-radius:18px;background:#eff6ff;border:1px solid #bfdbfe}table{width:100%;border-collapse:collapse;margin:1.5rem 0;background:#fff}th,td{border:1px solid var(--border);padding:.7rem;vertical-align:top}th{background:#fff7ed}
CSS;
    }

    private function cardsCss(): string
    {
        return <<<'CSS'
:root{--bg:#f2f5f9;--surface:#fff;--text:#202938;--muted:#5c6b80;--accent:#4f46e5;--border:#d9e1ee}body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.65;color:var(--text);background:var(--bg)}.page{max-width:1200px;margin:0 auto;padding:clamp(1rem,4vw,4rem)}.content{display:grid;grid-template-columns:repeat(12,1fr);gap:1rem}.content>*{grid-column:1/-1}h1{margin:0 0 1rem;padding:clamp(1.2rem,4vw,3rem);border-radius:28px;color:#fff;background:linear-gradient(135deg,#4f46e5,#06b6d4);box-shadow:0 18px 40px rgba(79,70,229,.22);font-size:clamp(2rem,6vw,4.5rem);line-height:1}h2{margin:1rem 0 0;padding:1.2rem 1.4rem;border-radius:22px 22px 0 0;background:var(--surface);border:1px solid var(--border);border-bottom:0}p,ul,ol,blockquote,pre,table,figure,.media-embed,.math-block,.chat-message{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:1rem 1.2rem;box-shadow:0 10px 24px rgba(32,41,56,.06)}ul,ol{padding-left:2.5rem}a{color:var(--accent);font-weight:800}code{background:#eef2ff;padding:.1rem .32rem;border-radius:.35rem}pre{overflow:auto;background:#111827;color:#f9fafb}pre code{background:transparent;color:inherit;padding:0}blockquote{border-left:.4rem solid var(--accent);color:var(--muted)}table{width:100%;border-collapse:collapse;padding:0;overflow:hidden}th,td{border:1px solid var(--border);padding:.75rem;text-align:left;vertical-align:top}th{background:#eef2ff}
CSS;
    }

    private function printCss(): string
    {
        return <<<'CSS'
body{margin:0;font-family:Georgia,"Times New Roman",serif;line-height:1.55;color:#111;background:#fff}.page{max-width:760px;margin:0 auto;padding:2.5rem 1.25rem}h1,h2,h3{line-height:1.2;page-break-after:avoid}h1{margin-top:0;font-size:2.4rem;border-bottom:2px solid #111;padding-bottom:.5rem}h2{margin-top:2rem;font-size:1.6rem}a{color:#111;text-decoration:underline}a[href]::after{content:" (" attr(href) ")";font-size:.85em;word-break:break-all}code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.92em}pre{overflow:auto;padding:.8rem;border:1px solid #999;background:#f7f7f7;white-space:pre-wrap}blockquote{margin:1rem 0;padding-left:1rem;border-left:4px solid #777}table{width:100%;border-collapse:collapse;margin:1.2rem 0}th,td{border:1px solid #777;padding:.5rem;text-align:left;vertical-align:top}@media print{.page{max-width:none;padding:0}}
CSS;
    }
}
