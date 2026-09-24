<?php

namespace App\Actions\Catalog;

use App\Models\Attribute;

/**
 * The starting set of characteristic filters (TZ §8.2): the import creates every
 * characteristic as not a filter, and these are the ones a HoReCa buyer chooses by —
 * power and voltage, burners and levels, volume and cooling, sinks and frames. Picked on
 * the supplier's catalogue of 24.09.2026 by how many products have them and how much they
 * differ. Slug => place in the panel, smaller first; the panel shows at most eight per page
 * and only those that mean something there (App\Services\Catalog\AttributeFacets).
 *
 * Run once after the first content import; the manager changes the set in the admin
 * («Характеристики» → «Фильтр», «Порядок»). A run turns the listed ones on again.
 */
final class MarkRecommendedFilters
{
    public const array RECOMMENDED = [
        'moshhnost-vt' => 10,
        'moshhnost-kvt' => 11,
        'potrebliaemaia-moshhnost-vt' => 12,
        'napriazenie' => 13,
        'kolicestvo-konforok' => 15,
        'kolicestvo-urovnei' => 16,
        'vnutrennii-obieem-l' => 20,
        'temperaturnyi-rezim' => 21,
        'tip-oxlazdeniia' => 22,
        'ispolnenie-dveri' => 23,
        'kolicestvo-dverei-st' => 24,
        'raspolozenie-agregata' => 25,
        'xolodilnyi-agregat' => 26,
        'xolodoproizvoditelnost-kvt' => 27,
        'xolodoproizvoditelnost-ne-menee-kvt' => 28,
        'ploshhad-vykladki-m2' => 29,
        'konstrukciia' => 30,
        'xladogent' => 31,
        'kolicestvo-emkostei-st' => 40,
        'tip-moiki' => 41,
        'glubina-rakoviny-mm' => 42,
        'nalicie-borta' => 43,
        'material-karkasa' => 44,
        'tip-ispolneniia' => 50,
        'tip-ustanovki' => 51,
        'upravlenie' => 52,
        'strana-proizvodstva' => 90,
    ];

    /**
     * @return array{marked: int, missing: list<string>} missing — not imported yet
     */
    public function handle(): array
    {
        $attributes = Attribute::query()->whereIn('slug', array_keys(self::RECOMMENDED))->get();

        foreach ($attributes as $attribute) {
            $attribute->forceFill(['is_filterable' => true, 'sort' => self::RECOMMENDED[$attribute->slug]])->save();
        }

        return [
            'marked' => $attributes->count(),
            'missing' => array_values(array_diff(array_keys(self::RECOMMENDED), $attributes->pluck('slug')->all())),
        ];
    }
}
