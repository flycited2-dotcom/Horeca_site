<?php

namespace App\Actions\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ежедневная копия базы (ТЗ §17.5): дамп `mariadb-dump` одной транзакцией, сжатый на лету,
 * 14 копий на сервере и копия вне сервера (§17.9, §19).
 *
 * Дамп пишется во временный файл и появляется в папке копий только целым: последняя строка
 * дампа — «Dump completed», без неё файл отвергается, а прежние копии остаются. Обрезанная
 * копия, которую примут за настоящую, хуже отсутствия копии. Пароль базы передаётся
 * переменной окружения, а не аргументом: его не видно в списке процессов.
 */
final class CreateDatabaseBackup
{
    public const string FILE_PATTERN = '/^horeca-\d{8}-\d{6}\.sql\.gz$/';

    /**
     * @throws BackupFailed
     */
    public function handle(): BackupResult
    {
        $directory = (string) config('backup.directory');
        File::ensureDirectoryExists($directory, 0750);

        $name = 'horeca-'.now()->format('Ymd-His').'.sql.gz';
        $path = $directory.'/'.$name;
        $partial = $path.'.part';

        try {
            $bytes = $this->dump($partial);
            $minimum = (int) config('backup.min_bytes');

            if ($bytes < $minimum) {
                throw BackupFailed::tooSmall($bytes, $minimum);
            }

            if (! @rename($partial, $path)) {
                throw BackupFailed::cannotWrite($path);
            }

            @chmod($path, 0640);
        } finally {
            File::delete($partial);
        }

        $removedLocal = $this->pruneLocal($directory);
        $offsite = $this->copyOffsite($path, $name);

        return new BackupResult($path, filesize($path) ?: 0, $offsite['made'], $removedLocal, $offsite['removed']);
    }

    /**
     * @return int size of the compressed file
     */
    private function dump(string $partial): int
    {
        $connection = (array) config('database.connections.'.config('database.default'));
        $gz = @gzopen($partial, 'wb6');

        if ($gz === false) {
            throw BackupFailed::cannotWrite($partial);
        }

        $tail = '';

        try {
            $result = Process::timeout((int) config('backup.dump_timeout'))
                ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
                ->run([
                    (string) config('backup.dump_binary'),
                    '--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob',
                    '--default-character-set=utf8mb4', '--skip-ssl',
                    '--host='.($connection['host'] ?? '127.0.0.1'),
                    '--port='.($connection['port'] ?? 3306),
                    '--user='.($connection['username'] ?? ''),
                    (string) ($connection['database'] ?? ''),
                ], function (string $type, string $output) use ($gz, &$tail): void {
                    if ($type === 'out') {
                        gzwrite($gz, $output);
                        $tail = substr($tail.$output, -200);
                    }
                });
        } catch (Throwable $exception) {
            throw BackupFailed::dumpFailed($exception->getMessage());
        } finally {
            gzclose($gz);
        }

        if ($result->failed()) {
            throw BackupFailed::dumpFailed(trim($result->errorOutput()) ?: 'код '.$result->exitCode());
        }

        if (! str_contains($tail, 'Dump completed')) {
            throw BackupFailed::truncated();
        }

        return filesize($partial) ?: 0;
    }

    /**
     * Keeps the newest copies on the server, drops the rest.
     */
    private function pruneLocal(string $directory): int
    {
        $files = collect(File::files($directory))
            ->map(fn (\SplFileInfo $file): string => $file->getFilename())
            ->filter(fn (string $name): bool => preg_match(self::FILE_PATTERN, $name) === 1)
            ->sortDesc()
            ->values();

        $old = $files->slice(max(1, (int) config('backup.keep')));

        foreach ($old as $name) {
            File::delete($directory.'/'.$name);
        }

        return $old->count();
    }

    /**
     * @return array{made: bool, removed: int}
     *
     * @throws BackupFailed the local copy stays, the run still counts as failed: without a copy outside the server the backup is not done
     */
    private function copyOffsite(string $path, string $name): array
    {
        $disk = config('backup.disk');

        if (blank($disk)) {
            Log::warning(__('backup.no_offsite'));

            return ['made' => false, 'removed' => 0];
        }

        $folder = trim((string) config('backup.offsite_directory'), '/');
        $remote = $folder.'/'.$name;

        try {
            $storage = Storage::disk((string) $disk);
            $stream = fopen($path, 'rb');

            try {
                $storage->writeStream($remote, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if ($storage->size($remote) !== filesize($path)) {
                throw BackupFailed::offsiteFailed(__('backup.errors.size_mismatch'));
            }

            $names = collect($storage->files($folder))
                ->map(fn (string $file): string => basename($file))
                ->filter(fn (string $file): bool => preg_match(self::FILE_PATTERN, $file) === 1)
                ->sortDesc()
                ->values();

            $old = $names->slice(max(1, (int) config('backup.offsite_keep')));

            foreach ($old as $file) {
                $storage->delete($folder.'/'.$file);
            }
        } catch (BackupFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw BackupFailed::offsiteFailed($exception->getMessage());
        }

        return ['made' => true, 'removed' => $old->count()];
    }
}
