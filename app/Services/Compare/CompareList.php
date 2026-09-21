<?php

namespace App\Services\Compare;

use App\Models\CompareItem;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Модели в сравнении (ТЗ §8.5): не больше LIMIT, в порядке добавления. У гостя список
 * живёт в сессии, у вошедшего клиента — в compare_items и переживает выход и вход;
 * гостевой список переносится при входе.
 */
final class CompareList
{
    public const int LIMIT = 4;

    private const string SESSION_KEY = 'compare';

    /**
     * Lists of signed-in customers already read in this request.
     *
     * @var array<int, list<int>>
     */
    private array $read = [];

    public function __construct(private readonly Session $session) {}

    /**
     * Product ids in the order they were added.
     *
     * @return list<int>
     */
    public function ids(?User $user): array
    {
        if ($user === null) {
            return $this->guestIds();
        }

        return $this->read[$user->id] ??= CompareItem::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
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

    /**
     * Puts a product into the comparison; a full list is left as it is.
     */
    public function add(int $productId, ?User $user): CompareResult
    {
        if ($this->contains($productId, $user)) {
            return CompareResult::Added;
        }

        if ($this->count($user) >= self::LIMIT) {
            return CompareResult::Full;
        }

        if ($user === null) {
            $this->session->put(self::SESSION_KEY, [...$this->guestIds(), $productId]);
        } else {
            CompareItem::query()->firstOrCreate(['user_id' => $user->id, 'product_id' => $productId]);
            unset($this->read[$user->id]);
        }

        return CompareResult::Added;
    }

    public function remove(int $productId, ?User $user): CompareResult
    {
        if ($user === null) {
            $this->session->put(self::SESSION_KEY, array_values(array_diff($this->guestIds(), [$productId])));
        } else {
            CompareItem::query()->where('user_id', $user->id)->where('product_id', $productId)->delete();
            unset($this->read[$user->id]);
        }

        return CompareResult::Removed;
    }

    public function clear(?User $user): void
    {
        if ($user === null) {
            $this->session->forget(self::SESSION_KEY);
        } else {
            CompareItem::query()->where('user_id', $user->id)->delete();
            unset($this->read[$user->id]);
        }
    }

    /**
     * Drops the products that can no longer be compared: removed from the site or hidden
     * by the manager.
     *
     * @param  list<int>  $available
     */
    public function keepOnly(array $available, ?User $user): void
    {
        foreach (array_diff($this->ids($user), $available) as $productId) {
            $this->remove($productId, $user);
        }
    }

    /**
     * A guest signs in: the models compared as a guest join the customer's list, the
     * customer's own go first, and the list stays within the limit.
     */
    public function mergeGuestList(User $user): void
    {
        $guest = $this->guestIds();

        if ($guest === []) {
            return;
        }

        foreach ($guest as $productId) {
            if ($this->add($productId, $user) === CompareResult::Full) {
                break;
            }
        }

        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * @return list<int>
     */
    private function guestIds(): array
    {
        $ids = $this->session->get(self::SESSION_KEY, []);

        return is_array($ids)
            ? array_values(array_unique(array_map(intval(...), array_filter($ids, is_numeric(...)))))
            : [];
    }
}
