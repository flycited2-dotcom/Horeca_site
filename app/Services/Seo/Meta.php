<?php

namespace App\Services\Seo;

/**
 * Что страница говорит поисковикам (ТЗ §14): полный заголовок вкладки, описание для выдачи,
 * канонический адрес и указание роботам. Каркас выводит как есть, ничего не дописывая.
 */
final readonly class Meta
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $robots = null,
    ) {}
}
