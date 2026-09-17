<?php

namespace App\Services\Supplier\Exceptions;

use App\Models\Supplier;
use RuntimeException;

class ImportAlreadyRunningException extends RuntimeException
{
    public function __construct(public readonly Supplier $supplier)
    {
        parent::__construct(__('import.errors.already_running', ['supplier' => $supplier->name]));
    }
}
