<?php

namespace App\Support;

/**
 * «Температура» раздела для облика «Холод и жар» (ТЗ §9): холодильное оборудование —
 * голубое, тепловое — оранжевое, остальное — нейтральное. Решает пиктограмма, которую
 * менеджер задал разделу; без неё — название раздела из каталога поставщика.
 */
final class CategoryZone
{
    public const string COLD = 'cold';

    public const string HOT = 'hot';

    public const string NEUTRAL = 'neutral';

    private const array ICONS = [
        'refrigeration' => self::COLD,
        'thermal' => self::HOT,
    ];

    /**
     * Word stems that name the zone in a section name: «Холодильное», «Морозильные лари»,
     * «Льдогенераторы»; «Тепловое», «Печи», «Плиты», «Фритюрницы», «Грили».
     */
    private const array STEMS = [
        self::COLD => ['холод', 'мороз', 'льдо', 'охлажд'],
        self::HOT => ['тепло', 'печ', 'плит', 'жароч', 'фритюр', 'гриль', 'пароконвект'],
    ];

    public static function of(?string $icon, ?string $name = null): string
    {
        if ($icon !== null && $icon !== '') {
            return self::ICONS[$icon] ?? self::NEUTRAL;
        }

        $name = mb_strtolower((string) $name);

        foreach (self::STEMS as $zone => $stems) {
            foreach ($stems as $stem) {
                if (preg_match('/(^|[^\p{L}])'.preg_quote($stem, '/').'/u', $name) === 1) {
                    return $zone;
                }
            }
        }

        return self::NEUTRAL;
    }
}
