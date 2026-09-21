<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Cookie;

/**
 * Плитка или список (ТЗ §8.2): выбор запоминается в cookie и действует во всех листингах.
 * Без скриптов вид переключает ссылка с ?view=.
 */
trait ChoosesView
{
    public const string VIEW_COOKIE = 'listing_view';

    public const array VIEWS = ['grid', 'list'];

    public string $view = 'grid';

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) {
            $this->rememberView($view);
        }
    }

    /**
     * The view asked for in the address (and remembered), otherwise the remembered one.
     */
    protected function chooseView(): void
    {
        $requested = request()->query('view');

        if (is_string($requested) && in_array($requested, self::VIEWS, true)) {
            $this->rememberView($requested);

            return;
        }

        $saved = request()->cookie(self::VIEW_COOKIE);
        $this->view = is_string($saved) && in_array($saved, self::VIEWS, true) ? $saved : 'grid';
    }

    private function rememberView(string $view): void
    {
        $this->view = $view;
        Cookie::queue(self::VIEW_COOKIE, $view, 60 * 24 * 365);
    }
}
