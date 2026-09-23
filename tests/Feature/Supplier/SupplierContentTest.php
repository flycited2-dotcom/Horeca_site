<?php

use App\Actions\Catalog\ContentSyncResult;
use App\Actions\Catalog\SyncSupplierPhotos;
use App\Jobs\SyncSupplierContentPage;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Supplier\Data\SupplierProductPhotos;
use App\Services\Supplier\Exceptions\FeedReadException;
use App\Services\Supplier\Sources\Rosholod\RosholodSiteSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

const PHOTO_A = 'https://rosholod.org/media/products_images/0eb83628-3339-11ed-9cd4-00155d0a5704/CR7-L_rtNrMQr.jpg';
const PHOTO_B = 'https://rosholod.org/media/products_images/0eb83628-3339-11ed-9cd4-00155d0a5704/%D0%98%D0%B7%D0%BE%D0%B1%D1%80.jpg';

beforeEach(function () {
    Storage::fake('public');
    Sleep::fake();
    config(['media-library.disk_name' => 'public']);

    $this->supplier = Supplier::factory()->create(['slug' => 'rosholod']);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'external_id' => '0eb83628-3339-11ed-9cd4-00155d0a5704']);

    $image = imagecreatetruecolor(80, 60);
    ob_start();
    imagejpeg($image);
    $this->jpeg = (string) ob_get_clean();

    // One stub for the supplier's site that answers what a test has set: stubs pile up in Http::fake().
    $this->list = supplierPhotoList([]);
    $this->photoStatus = 200;
    Http::preventStrayRequests();
    Http::fake([
        'rosholod.org/api/v1/prices/*' => fn () => Http::response($this->list),
        'rosholod.org/media/*' => fn () => Http::response($this->photoStatus === 200 ? $this->jpeg : '', $this->photoStatus, ['Content-Type' => 'image/jpeg']),
    ]);
});

/**
 * The supplier's list: one page of products with their photos.
 *
 * @param  list<array<string, mixed>>  $results
 */
function supplierPhotoList(array $results, int $page = 1, int $pages = 1): array
{
    return ['count' => count($results), 'num_pages' => $pages, 'current_page' => $page, 'per_page' => 20, 'results' => $results];
}

it('reads the GUID and the supplier photos from a page of the list', function () {
    $this->list = supplierPhotoList([
        ['product_id' => '0eb83628-3339-11ed-9cd4-00155d0a5704', 'images' => [PHOTO_A, 'https://evil.example/x.jpg', PHOTO_A]],
        ['product_id' => '', 'images' => [PHOTO_B]],
    ], pages: 718);

    $page = app(RosholodSiteSource::class)->page(1);

    expect($page->lastPage)->toBe(718)
        ->and($page->isLast())->toBeFalse()
        ->and($page->products)->toHaveCount(1)
        ->and($page->products[0]->externalId)->toBe('0eb83628-3339-11ed-9cd4-00155d0a5704')
        ->and($page->products[0]->urls)->toBe([PHOTO_A]);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'page=1') && str_contains($request->header('User-Agent')[0], 'Gastrosnab'));
});

it('keeps the photos with their sizes and marks them as the supplier ones, then goes to the next page', function () {
    Queue::fake();
    $this->list = supplierPhotoList([['product_id' => $this->product->external_id, 'images' => [PHOTO_A, PHOTO_B]]], pages: 2);

    app()->call([new SyncSupplierContentPage($this->supplier->id, 1), 'handle']);

    $media = $this->product->refresh()->getMedia(Product::IMAGES);

    expect($media)->toHaveCount(2)
        ->and($media->map->getCustomProperty('source')->all())->toBe(['supplier', 'supplier'])
        ->and($media->map->getCustomProperty('source_url')->all())->toBe([PHOTO_A, PHOTO_B])
        ->and($media[0]->file_name)->toBe('cr7-l-rtnrmqr.jpg')
        ->and($media[1]->file_name)->toBe('izobr.jpg');
    Storage::disk('public')->assertExists($media[0]->getPathRelativeToRoot('card'));

    Queue::assertPushed(SyncSupplierContentPage::class, 1);
});

it('does not download the same photos twice', function () {
    $sync = app(SyncSupplierPhotos::class);
    $photos = new SupplierProductPhotos($this->product->external_id, [PHOTO_A]);

    expect($sync->handle($this->supplier, $photos))->toBe(ContentSyncResult::Updated)
        ->and($sync->handle($this->supplier, $photos))->toBe(ContentSyncResult::Unchanged);

    Http::assertSentCount(1);
});

it('leaves the manual photos first and untouched', function () {
    $this->product->addMedia(UploadedFile::fake()->image('ruchnoe.jpg'))->withCustomProperties(['source' => 'manual'])->toMediaCollection(Product::IMAGES);

    app(SyncSupplierPhotos::class)->handle($this->supplier, new SupplierProductPhotos($this->product->external_id, [PHOTO_A]));
    app(SyncSupplierPhotos::class)->handle($this->supplier, new SupplierProductPhotos($this->product->external_id, [PHOTO_B]));

    $media = $this->product->refresh()->getMedia(Product::IMAGES);

    expect($media->map->getCustomProperty('source')->all())->toBe(['manual', 'supplier'])
        ->and($media[1]->getCustomProperty('source_url'))->toBe(PHOTO_B);
});

it('keeps the old photos when a new one does not download', function () {
    app(SyncSupplierPhotos::class)->handle($this->supplier, new SupplierProductPhotos($this->product->external_id, [PHOTO_A]));

    $this->photoStatus = 404;

    expect(fn () => app(SyncSupplierPhotos::class)->handle($this->supplier, new SupplierProductPhotos($this->product->external_id, [PHOTO_B])))
        ->toThrow(FeedReadException::class);

    $media = $this->product->refresh()->getMedia(Product::IMAGES);

    expect($media)->toHaveCount(1)
        ->and($media[0]->getCustomProperty('source_url'))->toBe(PHOTO_A);
});

it('skips a product the catalog does not have and a photo from elsewhere', function () {

    expect(app(SyncSupplierPhotos::class)->handle($this->supplier, new SupplierProductPhotos('no-such-guid', [PHOTO_A])))->toBe(ContentSyncResult::NoProduct)
        ->and(fn () => app(RosholodSiteSource::class)->download('https://evil.example/x.jpg'))->toThrow(FeedReadException::class);
});

it('queues the photos from the page asked for', function () {
    Queue::fake();

    $this->artisan('supplier:content', ['--page' => 5])->assertSuccessful();

    Queue::assertPushed(SyncSupplierContentPage::class, 1);
});

it('does not start a second run over one still going, and --force takes over', function () {
    Queue::fake();

    $this->artisan('supplier:content')->assertSuccessful();
    $first = SyncSupplierContentPage::running($this->supplier->id)['run'];

    $this->artisan('supplier:content')->expectsOutputToContain('уже идёт, дошла до страницы 1')->assertSuccessful();
    Queue::assertPushed(SyncSupplierContentPage::class, 1);

    $this->artisan('supplier:content', ['--page' => 40, '--force' => true])->assertSuccessful();
    Queue::assertPushed(SyncSupplierContentPage::class, 2);

    // Страница прежней цепочки видит, что её сменили, и ничего не делает.
    app()->call([new SyncSupplierContentPage($this->supplier->id, 3, $first), 'handle']);

    Http::assertNothingSent();
    Queue::assertPushed(SyncSupplierContentPage::class, 2);
    expect(SyncSupplierContentPage::running($this->supplier->id)['page'])->toBe(40);
});

it('moves the mark of the run along the pages and clears it after the last one', function () {
    Queue::fake();
    SyncSupplierContentPage::markRun($this->supplier->id, 'run-1', 1);

    $this->list = supplierPhotoList([], page: 1, pages: 2);
    app()->call([new SyncSupplierContentPage($this->supplier->id, 1, 'run-1'), 'handle']);

    expect(SyncSupplierContentPage::running($this->supplier->id))->toBe(['run' => 'run-1', 'page' => 2]);

    $this->list = supplierPhotoList([], page: 2, pages: 2);
    app()->call([new SyncSupplierContentPage($this->supplier->id, 2, 'run-1'), 'handle']);

    expect(SyncSupplierContentPage::running($this->supplier->id))->toBeNull();
    $this->artisan('supplier:content')->expectsOutputToContain('поставлена в очередь')->assertSuccessful();
});
