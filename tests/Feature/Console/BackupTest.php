<?php

use App\Actions\Backup\BackupFailed;
use App\Actions\Backup\CreateDatabaseBackup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/horeca-backup-test-'.bin2hex(random_bytes(4));
    config(['backup.directory' => $this->directory, 'backup.disk' => null, 'backup.keep' => 3, 'backup.min_bytes' => 100]);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

/**
 * The real client of the database: the backup is checked against the test database itself.
 */
function dumpClientIsThere(): bool
{
    return (new ExecutableFinder)->find((string) config('backup.dump_binary')) !== null;
}

/**
 * @return list<string> names of the copies in the backup folder, oldest first
 */
function copiesOnServer(): array
{
    return collect(File::files(config('backup.directory')))->map->getFilename()->sort()->values()->all();
}

it('dumps the database into a whole compressed file', function () {
    $result = app(CreateDatabaseBackup::class)->handle();

    $dump = gzdecode((string) file_get_contents($result->path));

    expect(copiesOnServer())->toHaveCount(1)
        ->and($result->bytes)->toBeGreaterThan(100)
        ->and($result->offsite)->toBeFalse()
        // Дамп идёт отдельным соединением и не видит незакоммиченных строк теста: данные
        // в нём — то, что записала миграция.
        ->and($dump)->toContain('CREATE TABLE `settings`', 'INSERT INTO `migrations`', 'Dump completed')
        ->and(File::glob($this->directory.'/*.part'))->toBe([]);
})->skip(fn () => ! dumpClientIsThere(), 'нужен mariadb-dump');

it('keeps the newest copies only', function () {
    File::ensureDirectoryExists($this->directory);

    foreach (['20260101-030000', '20260102-030000', '20260103-030000', '20260104-030000'] as $stamp) {
        file_put_contents($this->directory."/horeca-{$stamp}.sql.gz", 'old');
    }

    file_put_contents($this->directory.'/notes.txt', 'not a copy');

    $result = app(CreateDatabaseBackup::class)->handle();

    expect($result->removedLocal)->toBe(2)
        ->and(copiesOnServer())->toHaveCount(4)
        ->and(copiesOnServer())->toContain('notes.txt', 'horeca-20260104-030000.sql.gz', basename($result->path))
        ->and(copiesOnServer())->not->toContain('horeca-20260101-030000.sql.gz');
})->skip(fn () => ! dumpClientIsThere(), 'нужен mariadb-dump');

it('sends the copy outside the server and trims the old ones there', function () {
    Storage::fake('backups');
    config(['backup.disk' => 'backups', 'backup.offsite_keep' => 2]);

    foreach (['20260101-030000', '20260102-030000', '20260103-030000'] as $stamp) {
        Storage::disk('backups')->put("database/horeca-{$stamp}.sql.gz", 'old');
    }

    $result = app(CreateDatabaseBackup::class)->handle();

    expect($result->offsite)->toBeTrue()
        ->and($result->removedOffsite)->toBe(2);

    Storage::disk('backups')->assertExists('database/'.basename($result->path));
    Storage::disk('backups')->assertMissing('database/horeca-20260101-030000.sql.gz');
    expect(Storage::disk('backups')->size('database/'.basename($result->path)))->toBe($result->bytes);
})->skip(fn () => ! dumpClientIsThere(), 'нужен mariadb-dump');

it('reports a copy that is only on the server, and still counts it as made', function () {
    Log::spy();

    $this->artisan('backup:database')
        ->expectsOutputToContain('Копия базы готова')
        ->expectsOutputToContain('только на сервере')
        ->assertSuccessful();

    Log::shouldHaveReceived('warning')->once();
})->skip(fn () => ! dumpClientIsThere(), 'нужен mariadb-dump');

it('leaves no file and old copies alone when the dump fails', function () {
    File::ensureDirectoryExists($this->directory);
    file_put_contents($this->directory.'/horeca-20260101-030000.sql.gz', 'previous');
    Process::fake(['*' => Process::result(output: '', errorOutput: 'Access denied for user', exitCode: 2)]);

    expect(fn () => app(CreateDatabaseBackup::class)->handle())->toThrow(BackupFailed::class, 'Access denied for user')
        ->and(copiesOnServer())->toBe(['horeca-20260101-030000.sql.gz']);
});

it('refuses a dump that stopped half way, though the client exited quietly', function () {
    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

    expect(fn () => app(CreateDatabaseBackup::class)->handle())->toThrow(BackupFailed::class, 'Dump completed')
        ->and(copiesOnServer())->toBe([]);
});

it('never hands the password to the dump in the arguments', function () {
    Process::fake(['*' => Process::result(exitCode: 2)]);
    config(['database.connections.mariadb.password' => 'secret-from-env']);

    try {
        app(CreateDatabaseBackup::class)->handle();
    } catch (BackupFailed) {
    }

    Process::assertRan(fn ($process): bool => ! str_contains(implode(' ', (array) $process->command), 'secret-from-env')
        && ($process->environment['MYSQL_PWD'] ?? null) === 'secret-from-env');
});

it('raises a critical alarm when the run fails, so that it reaches Telegram', function () {
    Process::fake(['*' => Process::result(errorOutput: 'no route to host', exitCode: 2)]);
    Log::spy();

    $this->artisan('backup:database')->assertFailed();

    Log::shouldHaveReceived('critical')->withArgs(fn (string $message): bool => str_contains($message, 'Резервная копия базы не сделана') && str_contains($message, 'no route to host'))->once();
});

it('runs every day at 03:30', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'backup:database'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 3 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
