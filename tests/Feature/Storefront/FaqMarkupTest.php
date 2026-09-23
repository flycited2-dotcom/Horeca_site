<?php

use App\Models\Page;
use App\Support\StructuredData;

/**
 * The FAQPage markup of a page, decoded.
 *
 * @return array<string, mixed>|null
 */
function faqOf(string $html): ?array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $scripts);

    foreach ($scripts[1] as $json) {
        $data = json_decode($json, true);

        if (($data['@type'] ?? null) === 'FAQPage') {
            return $data;
        }
    }

    return null;
}

it('turns question headings of the delivery page into FAQPage markup', function () {
    Page::factory()->create([
        'slug' => 'dostavka',
        'title' => 'Доставка',
        'is_active' => true,
        'content' => implode("\n", [
            'Доставляем по Крыму и России.',
            '',
            '## Сколько стоит доставка по городу?',
            '',
            'Бесплатно от **15 000 ₽**, иначе — 500 ₽.',
            '',
            '### Можно ли забрать самому?',
            '',
            '- Да, со склада в Симферополе.',
            '',
            '## Сроки',
            '',
            'Обычно 1–3 дня.',
            '',
            '## Вопрос без ответа?',
        ]),
    ]);

    $faq = faqOf($this->get('/dostavka')->assertOk()->getContent());

    expect($faq)->toBe([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            ['@type' => 'Question', 'name' => 'Сколько стоит доставка по городу?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Бесплатно от 15 000 ₽, иначе — 500 ₽.']],
            ['@type' => 'Question', 'name' => 'Можно ли забрать самому?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Да, со склада в Симферополе.']],
        ],
    ]);
});

it('adds no FAQ markup to other pages or to a text without questions', function () {
    Page::factory()->create(['slug' => 'o-kompanii', 'is_active' => true, 'content' => "## Кто мы?\n\nПоставщик оборудования."]);
    Page::factory()->create(['slug' => 'oplata', 'is_active' => true, 'content' => "## Способы\n\nПо счёту."]);

    expect(faqOf($this->get('/o-kompanii')->getContent()))->toBeNull()
        ->and(faqOf($this->get('/oplata')->getContent()))->toBeNull()
        ->and(StructuredData::faq(null))->toBeNull();
});
