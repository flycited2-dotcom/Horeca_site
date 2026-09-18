<?php

use App\Enums\Availability;
use App\Enums\ImportRunStatus;
use App\Enums\ImportTrigger;
use App\Models\Category;
use App\Models\ImportRow;
use App\Models\Product;
use App\Services\Supplier\Exceptions\ImportAlreadyRunningException;
use App\Services\Supplier\Import\ImportRunner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->supplier = rosholodSupplier();
    $this->profile = rosholodProfile('rosholod.catalog_xml');

    fakeRosholodFeeds();
});

it('does nothing when the supplier answers 304', function () {
    fakeRosholodFeeds(status: 304);

    $run = runImport($this->profile);

    expect($run->status)->toBe(ImportRunStatus::Skipped)
        ->and($run->log)->toContain(__('import.messages.not_modified'))
        ->and(Product::query()->count())->toBe(0);
});

it('remembers the ETag only after a run that worked', function () {
    runImport($this->profile);

    expect($this->profile->refresh()->last_etag)->toBe('"catalog-1"');
});

it('does not remember the ETag of a file it refused', function () {
    fakeRosholodFeeds(catalog: substr(rosholodFixture('catalog_sample.xml'), 0, 20_000));

    $run = runImport($this->profile);

    expect($run->status)->toBe(ImportRunStatus::Failed)
        ->and($this->profile->refresh()->last_etag)->toBeNull();
});

it('refuses a truncated file and leaves the catalog as it was', function () {
    runImport($this->profile);

    $before = Product::query()->count();

    fakeRosholodFeeds(catalog: substr(rosholodFixture('catalog_sample.xml'), 0, 20_000));

    $run = runImport($this->profile);

    expect($run->status)->toBe(ImportRunStatus::Failed)
        ->and($run->error_message)->toContain('повреждён или обрезан')
        ->and(Product::query()->count())->toBe($before);
});

it('refuses a file that suddenly has too few products', function () {
    runImport($this->profile);

    $before = Product::query()->count();

    // Обрезанная на сервере выгрузка выглядит целой, но записей в ней заметно меньше.
    $short = editRosholodFixture('catalog_sample.xml', function (string $xml): string {
        $xml = (string) preg_replace('~(<ДетальнаяЗапись>.*?</ДетальнаяЗапись>\s*){50}~su', '', $xml, 1);

        return (string) preg_replace('~(<category [^>]*>.*?</category>\s*){200}~su', '', $xml, 1);
    });

    fakeRosholodFeeds(catalog: $short);

    $run = runImport($this->profile);

    expect($run->status)->toBe(ImportRunStatus::Failed)
        ->and($run->error_message)->toContain('Подозрительно мало записей')
        ->and(Product::query()->count())->toBe($before);
});

it('refuses a file where too many records are broken', function () {
    $this->profile->forceFill(['settings' => ['invalid_rows_max_percent' => 10]])->save();

    $broken = str_replace(['<ID>', '</ID>'], ['<IDX>', '</IDX>'], rosholodFixture('catalog_sample.xml'));

    fakeRosholodFeeds(catalog: $broken);

    $run = runImport($this->profile);

    expect($run->status)->toBe(ImportRunStatus::Failed)
        ->and($run->error_message)->toContain('Слишком много ошибочных записей')
        ->and(Product::query()->count())->toBe(0);
});

it('refuses a file that lost its encoding', function () {
    $mojibake = mb_convert_encoding(rosholodFixture('catalog_sample.xml'), 'UTF-8', 'windows-1251');

    fakeRosholodFeeds(catalog: $mojibake);

    $run = runImport($this->profile);

    expect($run->status)->toBe(ImportRunStatus::Failed)
        ->and(Product::query()->count())->toBe(0);
});

it('clears staging even after a failure', function () {
    fakeRosholodFeeds(catalog: substr(rosholodFixture('catalog_sample.xml'), 0, 20_000));

    $run = runImport($this->profile);

    expect(ImportRow::query()->where('import_run_id', $run->id)->count())->toBe(0);
});

it('refuses to start a second run of the same supplier', function () {
    $lock = Cache::lock('import:supplier:'.$this->supplier->id, 60);
    $lock->get();

    expect(fn () => app(ImportRunner::class)->run($this->profile, ImportTrigger::Cli))
        ->toThrow(ImportAlreadyRunningException::class);

    $lock->release();
});

it('lets the next run start once the lock is free', function () {
    runImport($this->profile);

    expect(Cache::lock('import:supplier:'.$this->supplier->id, 60)->get())->toBeTrue();
});

it('shows what a dry run would do without touching anything', function () {
    $run = runImport($this->profile, dryRun: true);

    expect($run->status)->toBe(ImportRunStatus::Success)
        ->and($run->is_dry_run)->toBeTrue()
        ->and($run->created)->toBe(56)
        ->and(Product::query()->count())->toBe(0)
        ->and(Category::query()->count())->toBe(0);
});

it('does not let a dry run stand in the way of a real one', function () {
    runImport($this->profile, dryRun: true);

    $real = runImport($this->profile);

    expect($real->status)->toBe(ImportRunStatus::Success)
        ->and($real->created)->toBe(56);
});

it('marks a product as discontinued after three snapshots without it', function () {
    runImport($this->profile);

    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();

    $without = editRosholodFixture('catalog_sample.xml', fn (string $xml): string => (string) preg_replace(
        '~<ДетальнаяЗапись>(?:(?!</ДетальнаяЗапись>).)*?<ID>f1592632-5228-11ea-bd2e-ac1f6b2ca8fb</ID>.*?</ДетальнаяЗапись>~su',
        '',
        $xml,
    ));

    expect($without)->not->toContain('f1592632-5228-11ea-bd2e-ac1f6b2ca8fb');

    fakeRosholodFeeds(catalog: $without);

    foreach ([1, 2] as $attempt) {
        $run = runImport($this->profile, force: true);

        expect($run->status)->toBe(ImportRunStatus::Success)
            ->and($product->refresh()->missing_runs)->toBe($attempt)
            ->and($product->availability)->not->toBe(Availability::Discontinued);
    }

    $run = runImport($this->profile, force: true);

    expect($run->discontinued)->toBe(1)
        ->and($product->refresh()->availability)->toBe(Availability::Discontinued)
        ->and($product->availability_rank)->toBe(4);
});

it('puts a product back on sale when the supplier sends it again', function () {
    runImport($this->profile);

    $product = Product::query()->where('external_id', 'f1592632-5228-11ea-bd2e-ac1f6b2ca8fb')->sole();
    $product->forceFill(['missing_runs' => 5, 'availability' => Availability::Discontinued])->save();

    runImport($this->profile, force: true);

    expect($product->refresh()->availability)->toBe(Availability::OnOrder)
        ->and($product->missing_runs)->toBe(0);
});
