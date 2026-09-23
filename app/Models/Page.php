<?php

namespace App\Models;

use App\Http\Controllers\WholesaleController;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'title', 'content', 'meta_title', 'meta_description', 'is_active', 'sort'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * Required pages (TZ §5.5): slug => title. The footer, the forms and the wholesale page link
     * to them, so their addresses stay and they are never deleted.
     */
    public const array REQUIRED = [
        'dostavka' => 'Доставка',
        'oplata' => 'Оплата',
        'garantiya' => 'Гарантия',
        'optovikam' => 'Оптовикам',
        'o-kompanii' => 'О компании',
        'kontakty' => 'Контакты',
        'politika-konfidencialnosti' => 'Политика конфиденциальности',
        'soglasie-na-obrabotku-personalnyh-dannyh' => 'Согласие на обработку персональных данных',
        'polzovatelskoe-soglashenie' => 'Пользовательское соглашение',
    ];

    /**
     * Where the page opens on the site: «Оптовикам» is the text of the wholesale page with its
     * application form (TZ §11), every other page lives at its slug.
     */
    public function url(): string
    {
        return $this->slug === WholesaleController::BENEFITS_PAGE ? route('wholesale') : url($this->slug);
    }

    public function isRequired(): bool
    {
        return array_key_exists((string) $this->getOriginal('slug', $this->slug), self::REQUIRED);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
