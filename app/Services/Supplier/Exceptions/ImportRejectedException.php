<?php

namespace App\Services\Supplier\Exceptions;

use RuntimeException;

/**
 * The data was read but failed a safety threshold, so the catalog is left untouched (TZ §6.3).
 */
class ImportRejectedException extends RuntimeException {}
