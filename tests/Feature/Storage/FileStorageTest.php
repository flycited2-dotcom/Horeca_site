<?php

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Product;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Psr\Http\Message\RequestInterface;

it('writes to the store folder of the bucket: photos open, invoices closed, no extra checksums', function () {
    // The SDK mock handler answers instead of the storage and keeps the requests: nothing leaves the test.
    $requests = [];
    $handler = new MockHandler;

    foreach (['photo', 'invoice'] as $upload) {
        $handler->append(function (CommandInterface $command, RequestInterface $request) use (&$requests): Result {
            $requests[] = $request;

            return new Result([]);
        });
    }

    foreach (['s3', 's3-private'] as $disk) {
        config()->set("filesystems.disks.{$disk}", [
            'key' => 'test-key',
            'secret' => 'test-secret',
            'region' => 'spb',
            'bucket' => 's3-968732',
            'root' => 'gastrosnab',
            'url' => 'https://s3.spb.sprinthost.ru/s3-968732',
            'endpoint' => 'https://s3.spb.sprinthost.ru',
            'use_path_style_endpoint' => true,
            'handler' => $handler,
        ] + config("filesystems.disks.{$disk}"));
    }

    Storage::forgetDisk(['s3', 's3-private']);

    Storage::disk('s3')->put('12/card.webp', 'photo');
    Storage::disk('s3-private')->put('invoices/schet.pdf', 'invoice');

    [$photo, $invoice] = $requests;

    expect($photo->getMethod())->toBe('PUT')
        ->and((string) $photo->getUri())->toBe('https://s3.spb.sprinthost.ru/s3-968732/gastrosnab/12/card.webp')
        ->and($photo->getHeaderLine('x-amz-acl'))->toBe('public-read')
        ->and($photo->hasHeader('x-amz-checksum-crc32'))->toBeFalse()
        ->and((string) $invoice->getUri())->toBe('https://s3.spb.sprinthost.ru/s3-968732/gastrosnab/invoices/schet.pdf')
        ->and($invoice->getHeaderLine('x-amz-acl'))->toBe('private')
        ->and(Storage::disk('s3')->url('12/card.webp'))->toBe('https://s3.spb.sprinthost.ru/s3-968732/gastrosnab/12/card.webp');
});

it('keeps product photos with all their sizes on the disk from the settings', function () {
    Storage::fake('s3');
    config(['media-library.disk_name' => 's3', 'media-library.queue_conversions_by_default' => false]);

    $media = Product::factory()->create()
        ->addMedia(UploadedFile::fake()->image('shkaf.jpg', 1200, 900))
        ->toMediaCollection(Product::IMAGES);

    expect($media->disk)->toBe('s3');

    foreach (['', 'thumb', 'card', 'full'] as $conversion) {
        Storage::disk('s3')->assertExists($media->getPathRelativeToRoot($conversion));
    }
});

it('keeps invoices on the private disk from the settings', function () {
    // Livewire holds the upload on the local disk until the form is saved.
    Storage::fake('local');
    Storage::fake('s3-private');
    config(['filesystems.invoices' => 's3-private']);
    $this->actingAs(staffUser());
    $order = Order::factory()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('attach_invoice', data: ['invoice' => UploadedFile::fake()->create('schet.pdf', 120, 'application/pdf')])
        ->assertHasNoActionErrors();

    $path = $order->refresh()->invoice_path;

    Storage::disk('s3-private')->assertExists($path);
    Storage::disk('local')->assertMissing($path);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('download_invoice')
        ->assertFileDownloaded("schet-{$order->number}.pdf");
});
