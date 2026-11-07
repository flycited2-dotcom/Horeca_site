<?php

return [
    'origin' => env('SEO_CANONICAL_ORIGIN', env('APP_ENV') === 'production' ? 'https://gastrosnab.ru' : null),
    'articles_directory' => storage_path('app/articles/gastrosnab.ru'),
    'articles_index' => storage_path('app/articles/published.json'),
];
