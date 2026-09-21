<?php

namespace App\View;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Тексты из админки — статические страницы и SEO-блоки — хранятся в Markdown (ТЗ §5.5).
 * HTML внутри текста вырезается, небезопасные ссылки (javascript:) не проходят: страница
 * показывает только то, что можно написать разметкой Markdown. Простой текст без разметки
 * становится абзацами.
 */
final class RichText
{
    public static function html(?string $markdown): HtmlString
    {
        $markdown = trim((string) $markdown);

        if ($markdown === '') {
            return new HtmlString('');
        }

        return new HtmlString(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }
}
