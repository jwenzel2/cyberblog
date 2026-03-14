<?php

declare(strict_types=1);

namespace App\Services;

final class HtmlSanitizer
{
    public static function clean(string $html): string
    {
        $allowed = '<p><a><ul><ol><li><strong><em><blockquote><pre><code><h1><h2><h3><h4><h5><h6><img><figure><figcaption><hr><br>';
        $clean = strip_tags($html, $allowed);

        // Remove inline event handlers without attempting full HTML policy parsing.
        $clean = preg_replace('/\son[a-z]+="[^"]*"/i', '', $clean) ?: $clean;
        $clean = preg_replace("/\son[a-z]+='[^']*'/i", '', $clean) ?: $clean;

        return $clean;
    }
}
