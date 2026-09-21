<?php

use App\Enums\ImportRunStatus;
use App\Enums\ImportTrigger;
use App\Enums\UserRole;
use App\Jobs\RunSupplierImport;
use App\Jobs\SendTelegramMessage;
use App\Mail\ImportFailedMail;
use App\Models\ImportRun;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->supplier = rosholodSupplier();
    $this->profile = rosholodProfile('rosholod.catalog_xml');

    fakeRosholodFeeds();
});

it('imports a profile by its source key from the console', function () {
    $this->artisan('supplier:import', ['profile' => 'rosholod.catalog_xml'])
        ->assertSuccessful();

    expect(Product::query()->count())->toBe(56)
        ->and(ImportRun::query()->sole()->trigger)->toBe(ImportTrigger::Cli);
});

it('imports a profile by its number', function () {
    $this->artisan('supplier:import', ['profile' => (string) $this->profile->id])
        ->assertSuccessful();

    expect(Product::query()->count())->toBe(56);
});

it('says so when there is no such profile', function () {
    $this->artisan('supplier:import', ['profile' => '999999'])
        ->expectsOutputToContain(__('import.command.profile_not_found', ['id' => '999999']))
        ->assertFailed();
});

it('shows the dry run report and leaves the catalog empty', function () {
    $this->artisan('supplier:import', ['profile' => 'rosholod.catalog_xml', '--dry-run' => true])
        ->assertSuccessful();

    expect(Product::query()->count())->toBe(0)
        ->and(ImportRun::query()->sole()->is_dry_run)->toBeTrue();
});

it('exits with an error when the run failed', function () {
    fakeRosholodFeeds(catalog: substr(rosholodFixture('catalog_sample.xml'), 0, 20_000));

    $this->artisan('supplier:import', ['profile' => 'rosholod.catalog_xml'])
        ->assertFailed();
});

it('queues only the profiles whose schedule is due', function () {
    Queue::fake();

    $this->profile->forceFill(['is_active' => true, 'schedule' => '* * * * *'])->save();
    rosholodProfile('rosholod.stock_xml')->forceFill(['is_active' => true, 'schedule' => '0 3 1 1 *'])->save();

    $this->artisan('supplier:dispatch-due')->assertSuccessful();

    Queue::assertPushed(RunSupplierImport::class, 1);
});

it('leaves a switched-off profile alone', function () {
    Queue::fake();

    $this->profile->forceFill(['is_active' => false, 'schedule' => '* * * * *'])->save();

    $this->artisan('supplier:dispatch-due')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('does not trip over a broken schedule', function () {
    Queue::fake();

    $this->profile->forceFill(['is_active' => true, 'schedule' => 'каждый час'])->save();

    $this->artisan('supplier:dispatch-due')
        ->expectsOutputToContain(__('import.command.invalid_schedule', ['profile' => $this->profile->name]))
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('sends the daily digest to Telegram', function () {
    Queue::fake();
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.chat_id', '-100500');

    runImport($this->profile);

    $this->artisan('imports:digest')->assertSuccessful();

    Queue::assertPushed(SendTelegramMessage::class);
});

it('says in the digest when nothing ran today', function () {
    Queue::fake();
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.chat_id', '-100500');

    $this->artisan('imports:digest')->assertSuccessful();

    Queue::assertPushed(SendTelegramMessage::class);
});

it('writes the message to the log while Telegram is not set up', function () {
    Queue::fake();
    config()->set('services.telegram.token', null);

    $this->artisan('imports:digest')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('tells the staff about a failed import', function () {
    Mail::fake();
    Queue::fake();
    config()->set('services.telegram.token', 'test-token');
    config()->set('services.telegram.chat_id', '-100500');

    $manager = User::factory()->create(['role' => UserRole::Manager, 'email' => 'manager@horeca.test']);
    User::factory()->create(['role' => UserRole::Customer]);

    fakeRosholodFeeds(catalog: substr(rosholodFixture('catalog_sample.xml'), 0, 20_000));

    $run = runImport($this->profile);

    expect($run->status)->toBe(ImportRunStatus::Failed);

    // One alarm per failure: a listener registered twice would send everything twice.
    Queue::assertPushed(SendTelegramMessage::class, 1);
    Mail::assertQueued(ImportFailedMail::class, 1);
    Mail::assertQueued(ImportFailedMail::class, fn (ImportFailedMail $mail): bool => $mail->hasTo($manager->email));
});

it('keeps quiet about a failed dry run', function () {
    Mail::fake();
    Queue::fake();

    User::factory()->create(['role' => UserRole::Manager]);

    fakeRosholodFeeds(catalog: substr(rosholodFixture('catalog_sample.xml'), 0, 20_000));

    runImport($this->profile, dryRun: true);

    Mail::assertNothingQueued();
    Queue::assertNotPushed(SendTelegramMessage::class);
});

it('runs an import from the queue and gives it back when the supplier is busy', function () {
    $job = new RunSupplierImport($this->profile->id, ImportTrigger::Schedule);

    app()->call([$job, 'handle']);

    expect(Product::query()->count())->toBe(56)
        ->and(ImportRun::query()->sole()->trigger)->toBe(ImportTrigger::Schedule);
});
