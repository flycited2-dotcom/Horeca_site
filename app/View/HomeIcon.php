<?php

namespace App\View;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Пиктограммы главной в облике «Свечение» (штрих 2 px, currentColor): стрелка круглой кнопки,
 * корзина и лупа. Рисуются прямо в разметке, без сети и без спрайта; пути — статичные строки,
 * поэтому готовую разметку можно отдавать как HtmlString, в том числе подписью кнопки.
 */
final class HomeIcon
{
    private const array PATHS = [
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'cart' => '<circle cx="9.5" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.8l2.3 11.2h10.4l2-8.2H6.2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
    ];

    public static function svg(string $name, string $class = ''): HtmlString
    {
        $paths = self::PATHS[$name] ?? throw new InvalidArgumentException("Unknown home icon [{$name}].");
        $class = trim('gl-ic '.$class);

        return new HtmlString('<svg class="'.e($class).'" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.$paths.'</svg>');
    }
}
