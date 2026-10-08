<?php

use App\View\HomeIcon;
use Illuminate\Support\HtmlString;

it('draws an icon as a decorative svg that takes the colour of the text', function (string $name) {
    $svg = HomeIcon::svg($name);

    expect($svg)->toBeInstanceOf(HtmlString::class)
        ->and((string) $svg)->toStartWith('<svg class="gl-ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false">')
        ->and((string) $svg)->toEndWith('</svg>');
})->with(['arrow', 'cart', 'search']);

it('adds the class of the place to the class of the icon and escapes it', function () {
    expect((string) HomeIcon::svg('search', 'gl-ic--lead'))->toStartWith('<svg class="gl-ic gl-ic--lead" ')
        ->and((string) HomeIcon::svg('arrow', '"><script>'))->not->toContain('<script>');
});

it('does not draw an icon it does not know', function () {
    HomeIcon::svg('trophy');
})->throws(InvalidArgumentException::class, 'Unknown home icon [trophy].');
