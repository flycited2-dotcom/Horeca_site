<?php

namespace App\Services\Supplier\Import;

use App\Models\ImportProfile;
use App\Services\Supplier\Data\FetchedSource;
use App\Services\Supplier\Exceptions\FeedReadException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Downloads a feed with a conditional GET and keeps the latest copies (TZ §6.3, step 3).
 */
final class SourceFetcher
{
    /**
     * @throws FeedReadException
     */
    public function fetch(ImportProfile $profile, bool $force, string $extension = 'xml'): ?FetchedSource
    {
        if (blank($profile->url)) {
            throw new FeedReadException(__('import.errors.no_url'));
        }

        $headers = [];

        if (! $force && filled($profile->last_etag)) {
            $headers['If-None-Match'] = $profile->last_etag;
        }

        if (! $force && filled($profile->last_modified)) {
            $headers['If-Modified-Since'] = $profile->last_modified;
        }

        try {
            $response = Http::timeout((int) config('import.download_timeout'))
                ->withUserAgent('horeca-shop-import')
                ->withHeaders($headers)
                ->get($profile->url);
        } catch (ConnectionException $exception) {
            throw new FeedReadException(__('import.errors.download_failed', ['reason' => $exception->getMessage()]), previous: $exception);
        }

        if ($response->status() === 304) {
            return null;
        }

        if (! $response->successful()) {
            throw new FeedReadException(__('import.errors.http_status', ['status' => $response->status()]));
        }

        $body = $response->body();
        $maxBytes = (int) config('import.max_download_bytes');

        if ($body === '') {
            throw new FeedReadException(__('import.errors.empty_file'));
        }

        if (strlen($body) > $maxBytes) {
            throw new FeedReadException(__('import.errors.too_large', ['limit' => intdiv($maxBytes, 1024 * 1024)]));
        }

        $disk = Storage::disk(config('import.disk'));
        $relativePath = $this->pathFor($profile, $extension, $disk->exists(...));
        $disk->put($relativePath, $body);
        $this->prune($profile, $extension);

        return new FetchedSource(
            path: $disk->path($relativePath),
            relativePath: $relativePath,
            hash: md5($body),
            etag: $response->header('ETag') ?: null,
            lastModified: $response->header('Last-Modified') ?: null,
        );
    }

    /**
     * @param  callable(string): bool  $exists
     */
    private function pathFor(ImportProfile $profile, string $extension, callable $exists): string
    {
        $base = $this->directory($profile).'/'.now()->format('Y-m-d_His').'_'.$this->sourceSlug($profile);
        $path = "{$base}.{$extension}";

        for ($copy = 2; $exists($path); $copy++) {
            $path = "{$base}-{$copy}.{$extension}";
        }

        return $path;
    }

    /**
     * Keeps the configured number of the latest files of this profile.
     */
    private function prune(ImportProfile $profile, string $extension): void
    {
        $disk = Storage::disk(config('import.disk'));
        $suffix = '_'.$this->sourceSlug($profile);

        collect($disk->files($this->directory($profile)))
            ->filter(fn (string $file): bool => Str::contains(basename($file), $suffix) && Str::endsWith($file, ".{$extension}"))
            ->sortDesc()
            ->slice((int) config('import.keep_files_per_profile'))
            ->each(fn (string $file): bool => $disk->delete($file));
    }

    private function directory(ImportProfile $profile): string
    {
        return config('import.directory').'/'.$profile->supplier->slug;
    }

    private function sourceSlug(ImportProfile $profile): string
    {
        return Str::slug(str_replace('.', '-', $profile->source).'-'.$profile->id);
    }
}
