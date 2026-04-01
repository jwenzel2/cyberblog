<?php

declare(strict_types=1);

namespace App\Services;

final class HtmlSanitizer
{
    public static function clean(string $html): string
    {
        $allowed = '<p><a><ul><ol><li><strong><em><blockquote><pre><code><h1><h2><h3><h4><h5><h6><img><figure><figcaption><hr><br>';
        $clean = strip_tags($html, $allowed);

        // Remove all event handlers: quoted, unquoted, and backtick-quoted.
        $clean = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $clean) ?: $clean;
        $clean = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $clean) ?: $clean;
        $clean = preg_replace('/\son[a-z]+\s*=\s*[^\s>]*/i', '', $clean) ?: $clean;

        // Remove javascript:, vbscript:, and data: URIs from href and src attributes.
        $clean = preg_replace('/(<a\s[^>]*?)href\s*=\s*["\']?\s*(?:javascript|vbscript|data)\s*:[^"\'>\s]*/i', '$1href="about:blank"', $clean) ?: $clean;
        $clean = preg_replace('/(<img\s[^>]*?)src\s*=\s*["\']?\s*(?:javascript|vbscript|data)\s*:[^"\'>\s]*/i', '$1src=""', $clean) ?: $clean;

        return $clean;
    }
}
