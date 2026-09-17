<?php

namespace App\Services\Supplier\Exceptions;

use RuntimeException;

/**
 * The source could not be downloaded or read. The message is shown to managers as is.
 */
class FeedReadException extends RuntimeException {}
