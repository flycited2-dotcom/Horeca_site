<?php

// Резервные копии базы (ТЗ §17.5, §17.9): ежедневный дамп, 14 копий на сервере и копия
// вне сервера. Место внешней копии — диск `backups` (config/filesystems.php): любое
// S3-совместимое хранилище с закрытым бакетом. Бакет с фото не годится: провайдер отдаёт
// такой бакет целиком, а в дампе — данные клиентов (§15.10).

return [

    'directory' => storage_path('app/backups'),

    'keep' => (int) env('BACKUP_KEEP', 14),

    // Имя диска внешней копии; пусто — копия остаётся только на сервере, и об этом
    // каждый раз пишет предупреждение в журнал.
    'disk' => env('BACKUP_DISK') ?: null,

    'offsite_directory' => 'database',

    'offsite_keep' => (int) env('BACKUP_OFFSITE_KEEP', 30),

    'dump_binary' => env('BACKUP_DUMP_BINARY', 'mariadb-dump'),

    'dump_timeout' => 1800,

    // Дамп меньше этого размера — не дамп: копия отвергается, а не откладывается «на потом».
    'min_bytes' => (int) env('BACKUP_MIN_BYTES', 1024),

];
