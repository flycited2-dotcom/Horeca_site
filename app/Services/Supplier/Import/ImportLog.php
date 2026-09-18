<?php

namespace App\Services\Supplier\Import;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Problems of one import run (TZ §6.4).
 *
 * The first messages are kept in import_runs.log and shown on the run page,
 * every message goes to storage/logs/imports/{run}.log, which managers download.
 */
final class ImportLog
{
    /**
     * @var list<string>
     */
    private array $first = [];

    /**
     * @var array<string, true>
     */
    private array $once = [];

    /**
     * @var resource|null
     */
    private $handle = null;

    private bool $writable = true;

    private bool $written = false;

    public function __construct(
        private readonly int $runId,
        private readonly int $limit,
    ) {}

    public function add(string $message): void
    {
        if (count($this->first) < $this->limit) {
            $this->first[] = $message;
        }

        $this->write($message);
    }

    /**
     * Adds a message only the first time this key is seen: one line per unknown
     * category or unknown stock value instead of one line per product.
     */
    public function addOnce(string $key, string $message): void
    {
        if (isset($this->once[$key])) {
            return;
        }

        $this->once[$key] = true;
        $this->add($message);
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return $this->first;
    }

    /**
     * Path relative to the storage directory, or null when there was nothing to write.
     */
    public function relativePath(): ?string
    {
        return $this->written ? self::relativePathFor($this->runId) : null;
    }

    private static function relativePathFor(int $runId): string
    {
        return "logs/imports/{$runId}.log";
    }

    private static function absolutePathFor(int $runId): string
    {
        return storage_path(self::relativePathFor($runId));
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    private function write(string $message): void
    {
        if (! $this->writable) {
            return;
        }

        try {
            $this->handle ??= $this->open();
            fwrite($this->handle, now()->format('H:i:s').' '.$message."\n");
            $this->written = true;
        } catch (RuntimeException $exception) {
            // A missing log file must not stop an import: the first messages are in the database anyway.
            $this->writable = false;
            Log::warning('Не удалось записать журнал импорта.', ['run' => $this->runId, 'reason' => $exception->getMessage()]);
        }
    }

    /**
     * @return resource
     */
    private function open()
    {
        $path = self::absolutePathFor($this->runId);
        $directory = dirname($path);

        // Warnings are replaced by our own exception, which write() turns into a log line.
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Не удалось создать каталог [{$directory}].");
        }

        // A run owns its file: after migrate:fresh the numbers start again, so an old file is replaced.
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Не удалось открыть файл [{$path}].");
        }

        return $handle;
    }
}
