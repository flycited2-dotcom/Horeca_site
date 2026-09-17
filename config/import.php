<?php

use App\Services\Supplier\Sources\Rosholod\RosholodCatalogXmlSource;
use App\Services\Supplier\Sources\Rosholod\RosholodStockXmlSource;

return [

    /*
    |--------------------------------------------------------------------------
    | Supplier feed adapters
    |--------------------------------------------------------------------------
    |
    | import_profiles.source => adapter class implementing SupplierFeedInterface.
    |
    */

    'sources' => [
        'rosholod.catalog_xml' => RosholodCatalogXmlSource::class,
        'rosholod.stock_xml' => RosholodStockXmlSource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Imports run on their own connection with retry_after longer than the job
    | timeout (TZ §17.4): database-imports locally, redis-imports on the server.
    |
    */

    'queue_connection' => env('IMPORTS_QUEUE_CONNECTION', 'database-imports'),

    'queue' => 'imports',

    'job_timeout' => 3600,

    'lock_seconds' => 7200,

    /*
    |--------------------------------------------------------------------------
    | Downloaded files
    |--------------------------------------------------------------------------
    */

    'disk' => 'local',

    'directory' => 'imports',

    'keep_files_per_profile' => 30,

    'download_timeout' => 300,

    'max_download_bytes' => 100 * 1024 * 1024,

    /*
    |--------------------------------------------------------------------------
    | Processing
    |--------------------------------------------------------------------------
    */

    'chunk_size' => 500,

    'log_limit' => 500,

    /*
    |--------------------------------------------------------------------------
    | Default thresholds, overridable in import_profiles.settings (TZ §6.3)
    |--------------------------------------------------------------------------
    */

    'thresholds' => [
        'invalid_rows_max_percent' => 30,
        'min_records_percent_of_previous' => 80,
    ],

];
