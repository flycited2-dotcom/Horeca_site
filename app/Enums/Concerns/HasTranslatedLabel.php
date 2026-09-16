<?php

namespace App\Enums\Concerns;

/**
 * Resolves an enum label from lang/{locale}/enums.php.
 *
 * The using enum declares the TRANSLATION_KEY constant, e.g. 'order_status'.
 */
trait HasTranslatedLabel
{
    public function getLabel(): string
    {
        return __('enums.'.static::TRANSLATION_KEY.'.'.$this->value);
    }
}
