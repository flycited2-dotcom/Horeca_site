<?php

namespace App\Services\Supplier\Sources\Rosholod\Api;

use App\Services\Supplier\Exceptions\FeedReadException;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Dealer API of Rosholod, read only (TZ §6): `Authorization: Bearer <токен>`, five requests a
 * second for a token, lists by `next_cursor`. The client keeps the pause between requests,
 * waits as long as `Retry-After` says after a 429 and tries a 503 again a few times; the
 * token never gets into a message, a log or an exception.
 */
final class RosholodApiClient
{
    /**
     * How many times a 429 or a 503 is tried again before the request fails.
     */
    private const int ATTEMPTS = 4;

    /**
     * The longest wait after a 429, in seconds: a longer one means something is wrong.
     */
    private const int MAX_RETRY_AFTER = 60;

    private float $lastRequestAt = 0.0;

    public function configured(): bool
    {
        return filled(config('suppliers.rosholod.api.token'));
    }

    /**
     * One request: the decoded JSON of the answer.
     *
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     *
     * @throws FeedReadException
     */
    public function get(string $path, array $query = []): array
    {
        if (! $this->configured()) {
            throw new FeedReadException(__('import.errors.api_not_configured'));
        }

        $query = array_filter($query, fn (mixed $value): bool => $value !== null && $value !== '');

        for ($attempt = 1; ; $attempt++) {
            $this->pause();

            try {
                $response = $this->request()->get($path, $query);
            } catch (ConnectionException $exception) {
                if ($attempt < self::ATTEMPTS) {
                    Sleep::for(2 ** $attempt)->seconds();

                    continue;
                }

                throw new FeedReadException(__('import.errors.api_failed', ['reason' => $this->withoutToken($exception->getMessage())]));
            }

            if ($response->successful()) {
                $json = $response->json();

                if (! is_array($json)) {
                    throw new FeedReadException(__('import.errors.api_failed', ['reason' => $path]));
                }

                return $json;
            }

            if ($attempt < self::ATTEMPTS && in_array($response->status(), [429, 503], true)) {
                $this->wait($response, $attempt);

                continue;
            }

            throw $this->failure($response, $path);
        }
    }

    /**
     * Every item of a list, page after page by the cursor of the previous answer.
     *
     * @param  array<string, scalar|null>  $query
     * @return Generator<int, array<string, mixed>>
     *
     * @throws FeedReadException
     */
    public function items(string $path, array $query = [], int $limit = 100): Generator
    {
        $cursor = null;

        do {
            $page = $this->get($path, $query + ['limit' => $limit] + ($cursor === null ? [] : ['cursor' => $cursor]));

            foreach (is_array($page['items'] ?? null) ? $page['items'] : [] as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            $cursor = is_string($page['next_cursor'] ?? null) && $page['next_cursor'] !== '' ? $page['next_cursor'] : null;

            if (($page['has_more'] ?? false) === true && $cursor === null) {
                throw new FeedReadException(__('import.errors.api_cursor_missing', ['path' => $path]));
            }
        } while (($page['has_more'] ?? false) === true);
    }

    /**
     * The limit of the supplier is five requests a second for a token: the client leaves a margin.
     */
    private function pause(): void
    {
        $interval = 1000 / max(1, (int) config('suppliers.rosholod.api.requests_per_second'));
        $elapsed = (microtime(true) - $this->lastRequestAt) * 1000;

        if ($this->lastRequestAt > 0.0 && $elapsed < $interval) {
            Sleep::for((int) ceil($interval - $elapsed))->milliseconds();
        }

        $this->lastRequestAt = microtime(true);
    }

    private function wait(Response $response, int $attempt): void
    {
        $retryAfter = (int) $response->header('Retry-After');
        $seconds = $retryAfter > 0 ? min($retryAfter, self::MAX_RETRY_AFTER) : 2 ** $attempt;

        Sleep::for($seconds)->seconds();
    }

    private function failure(Response $response, string $path): FeedReadException
    {
        return new FeedReadException(match ($response->status()) {
            401, 403 => __('import.errors.api_unauthorized', ['status' => $response->status()]),
            default => __('import.errors.api_status', ['status' => $response->status(), 'path' => $path]),
        });
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('suppliers.rosholod.api.base_url'))
            ->withToken((string) config('suppliers.rosholod.api.token'))
            ->withUserAgent((string) config('suppliers.rosholod.api.user_agent'))
            ->timeout((int) config('suppliers.rosholod.api.timeout'))
            ->acceptJson();
    }

    private function withoutToken(string $text): string
    {
        $token = (string) config('suppliers.rosholod.api.token');

        return $token === '' ? $text : str_replace($token, '***', $text);
    }
}
