<?php

namespace App\Services\Seo;

/**
 * Что страница говорит поисковикам (ТЗ §14): полный заголовок вкладки, описание для выдачи,
 * канонический адрес, указание роботам и картинка для превью ссылки (у товара — его фото,
 * у остальных страниц каркас берёт общую). Каркас выводит как есть, ничего не дописывая.
 */
final readonly class Meta
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $robots = null,
        public ?string $image = null,
    ) {}
}
