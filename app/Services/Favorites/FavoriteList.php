<?php

namespace App\Services\Favorites;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * Избранное (ТЗ §5, §8, §11): у вошедшего клиента — строки favorites с user_id, у гостя —
 * с session_id. Это не id сессии сайта: он меняется при входе, а избранное должно дожить
 * до объединения. Гостю выдаётся своя метка, она хранится в сессии и живёт вместе с ней.
 * При входе гостевой список переходит к клиенту (MergeGuestFavorites).
 */
final class FavoriteList
{
    private const string SESSION_KEY = 'favorites_token';

    /**
     * Lists already read in this request, by owner key.
     *
     * @var array<string, list<int>>
     */
    private array $read = [];

    public function __construct(private readonly Session $session) {}

    /**
     * Product ids, the latest added first.
     *
     * @return list<int>
     */
    public function ids(?User $user): array
    {
        $owner = $this->owner($user);

        if ($owner === null) {
            return [];
        }

        return $this->read[$owner[0].':'.$owner[1]] ??= Favorite::query()
            ->where($owner[0], $owner[1])
            ->orderByDesc('id')
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function contains(int $productId, ?User $user): bool
    {
        return in_array($productId, $this->ids($user), true);
    }

    public function count(?User $user): int
    {
        return count($this->ids($user));
    }

    public function add(int $productId, ?User $user): void
    {
        $owner = $this->owner($user) ?? ['session_id', $this->issueToken()];

        Favorite::query()->firstOrCreate([$owner[0] => $owner[1], 'product_id' => $productId]);
        $this->read = [];
    }

    public function remove(int $productId, ?User $user): void
    {
        $owner = $this->owner($user);

        if ($owner !== null) {
            Favorite::query()->where($owner[0], $owner[1])->where('product_id', $productId)->delete();
            $this->read = [];
        }
    }

    /**
     * Drops the products that can no longer be shown: removed from the site or hidden by
     * the manager — the header count must match the page.
     *
     * @param  list<int>  $available
     */
    public function keepOnly(array $available, ?User $user): void
    {
        $owner = $this->owner($user);
        $gone = array_values(array_diff($this->ids($user), $available));

        if ($owner !== null && $gone !== []) {
            Favorite::query()->where($owner[0], $owner[1])->whereIn('product_id', $gone)->delete();
            $this->read = [];
        }
    }

    /**
     * A guest signs in: what they marked as a guest joins the customer's favorites, nothing twice.
     */
    public function mergeGuestList(User $user): void
    {
        $token = $this->token();

        if ($token === null) {
            return;
        }

        $own = Favorite::query()->where('user_id', $user->id)->pluck('product_id')->all();

        Favorite::query()->where('session_id', $token)->whereIn('product_id', $own)->delete();
        Favorite::query()->where('session_id', $token)->update(['user_id' => $user->id, 'session_id' => null]);

        $this->session->forget(self::SESSION_KEY);
        $this->read = [];
    }

    /**
     * Whose rows to read: the customer's, or the guest's by the token, or nobody's yet.
     *
     * @return array{string, int|string}|null
     */
    private function owner(?User $user): ?array
    {
        if ($user !== null) {
            return ['user_id', $user->id];
        }

        $token = $this->token();

        return $token === null ? null : ['session_id', $token];
    }

    private function token(): ?string
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function issueToken(): string
    {
        $token = Str::random(40);
        $this->session->put(self::SESSION_KEY, $token);

        return $token;
    }
}
