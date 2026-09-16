<?php

namespace Database\Seeders;

use App\Actions\Catalog\RecalculateCategoryCounts;
use App\Actions\Orders\GenerateOrderNumber;
use App\Enums\AttributeType;
use App\Enums\Availability;
use App\Enums\CompanySegment;
use App\Enums\CompanyStatus;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Enums\WarehouseStockStatus;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Money;
use App\Support\Percent;
use Carbon\CarbonImmutable;
use Faker\Generator;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo catalog for local development and design review (TZ §16).
 *
 * The Faker seed is fixed, so every run produces the same catalog.
 */
class DemoSeeder extends Seeder
{
    public const int PRODUCTS = 150;

    public const string PASSWORD = 'password';

    private const int FAKER_SEED = 20260916;

    /**
     * Root category => [icon, [subcategory => model prefix]]. Names follow the real supplier tree.
     */
    private const array TREE = [
        'Тепловое и технологическое оборудование' => ['thermal', [
            'Пароконвектомат' => 'ПКА', 'Печь конвекционная' => 'КЭП', 'Плита индукционная' => 'КИП', 'Котел пищеварочный' => 'КПЭМ',
        ]],
        'Холодильное оборудование' => ['refrigeration', [
            'Холодильный шкаф' => 'ШХ', 'Витрина холодильная' => 'ВХС', 'Стол холодильный' => 'СХС', 'Ларь морозильный' => 'ЛМ',
        ]],
        'Нейтральное оборудование' => ['neutral', [
            'Стол производственный' => 'СП', 'Стеллаж кухонный' => 'СК', 'Ванна моечная' => 'ВМ',
        ]],
        'Электромеханическое оборудование' => ['electromechanical', [
            'Тестомесильная машина' => 'ТММ', 'Мясорубка' => 'МИМ', 'Слайсер' => 'СЛ',
        ]],
        'Посудомоечное оборудование' => ['dishwashing', [
            'Фронтальная посудомоечная машина' => 'МПК', 'Купольная посудомоечная машина' => 'МПК-К',
        ]],
        'Линия раздачи' => ['serving-line', [
            'Мармит' => 'ЭМК', 'Прилавок-витрина' => 'ПВ',
        ]],
        'Фаст-фуд' => ['fast-food', [
            'Фритюрница электрическая' => 'ФЭ', 'Гриль контактный' => 'ГК',
        ]],
        'Вентиляционное оборудование' => ['ventilation', [
            'Зонт вентиляционный' => 'ЗВ',
        ]],
    ];

    /**
     * Price range in rubles by root category icon.
     */
    private const array PRICE_RANGES = [
        'thermal' => [20_000, 600_000],
        'refrigeration' => [25_000, 350_000],
        'neutral' => [5_000, 60_000],
        'electromechanical' => [15_000, 250_000],
        'dishwashing' => [150_000, 970_000],
        'serving-line' => [30_000, 200_000],
        'fast-food' => [8_000, 90_000],
        'ventilation' => [10_000, 80_000],
    ];

    /**
     * Root icons whose products are powered and therefore have power and voltage.
     */
    private const array POWERED = ['thermal', 'refrigeration', 'electromechanical', 'dishwashing', 'serving-line', 'fast-food'];

    private const array BRANDS = [
        'Abat' => 'Россия',
        'Hicold' => 'Россия',
        'Полаир' => 'Россия',
        'Атеси' => 'Россия',
        'Robot Coupe' => 'Франция',
        'UNOX' => 'Италия',
    ];

    /**
     * Warehouse => [delivery days to Simferopol: min, max].
     */
    private const array WAREHOUSES = [
        'Волжск' => [5, 8],
        'Москва (ЦФО)' => [6, 9],
        'Новосибирск' => [10, 14],
        'Симферополь' => [1, 2],
    ];

    /**
     * The first products get each availability in turn, so every state is present.
     */
    private const array GUARANTEED_AVAILABILITY = [
        Availability::InStock,
        Availability::Low,
        Availability::Incoming,
        Availability::OnOrder,
        Availability::Discontinued,
    ];

    private Generator $faker;

    public function run(GenerateOrderNumber $generateOrderNumber, RecalculateCategoryCounts $recalculateCategoryCounts): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        $this->call(ProductionSeeder::class);

        if (Product::query()->exists()) {
            $this->command?->warn('Каталог уже заполнен, демо-данные не добавлены. Полное пересоздание: php artisan migrate:fresh --seed');

            return;
        }

        $this->faker = fake('ru_RU');
        $this->faker->seed(self::FAKER_SEED);

        config(['media-library.queue_conversions_by_default' => false]);

        // One transaction instead of hundreds of separately flushed inserts.
        DB::transaction(function () use ($generateOrderNumber, $recalculateCategoryCounts): void {
            $supplier = Supplier::query()->where('slug', ProductionSeeder::ROSHOLOD_SLUG)->firstOrFail();

            $warehouses = $this->seedWarehouses($supplier);
            $brands = $this->seedBrands();
            $subcategories = $this->seedCategories();
            $attributes = $this->seedAttributes();
            $products = $this->seedProducts($supplier, $subcategories, $brands, $warehouses, $attributes);

            $this->seedCollections($products);
            $wholesaler = $this->seedUsers();
            $this->seedProductPrices($products, $wholesaler->company->priceTier);
            $this->seedOrders($products, $wholesaler, $generateOrderNumber);

            $recalculateCategoryCounts->handle();
        });
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function seedWarehouses(Supplier $supplier): Collection
    {
        $warehouses = collect();
        $sort = 0;

        foreach (self::WAREHOUSES as $name => [$minDays, $maxDays]) {
            $warehouses->push(Warehouse::query()->create([
                'supplier_id' => $supplier->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'city' => Str::before($name, ' ('),
                'delivery_days_min' => $minDays,
                'delivery_days_max' => $maxDays,
                'is_visible' => true,
                'sort' => $sort += 10,
            ]));
        }

        return $warehouses;
    }

    /**
     * @return Collection<int, Brand>
     */
    private function seedBrands(): Collection
    {
        return collect(self::BRANDS)->map(fn (string $country, string $name): Brand => Brand::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'country' => $country,
            'is_active' => true,
        ]))->values();
    }

    /**
     * @return Collection<int, array{category: Category, prefix: string, icon: string}>
     */
    private function seedCategories(): Collection
    {
        $subcategories = collect();
        $rootSort = 0;

        foreach (self::TREE as $rootName => [$icon, $children]) {
            $root = Category::query()->create([
                'name' => $rootName,
                'slug' => Str::slug($rootName),
                'icon' => $icon,
                'show_on_home' => true,
                'sort' => $rootSort += 10,
                'is_active' => true,
            ]);

            $childSort = 0;

            foreach ($children as $childName => $prefix) {
                $subcategories->push([
                    'category' => Category::query()->create([
                        'parent_id' => $root->id,
                        'name' => $childName,
                        'slug' => Str::slug($childName),
                        'sort' => $childSort += 10,
                        'is_active' => true,
                    ]),
                    'prefix' => $prefix,
                    'icon' => $icon,
                ]);
            }
        }

        return $subcategories;
    }

    /**
     * @return array{power: Attribute, voltage: Attribute, width: Attribute}
     */
    private function seedAttributes(): array
    {
        return [
            'power' => Attribute::query()->create([
                'name' => 'Мощность', 'slug' => 'moshchnost', 'unit' => 'кВт', 'type' => AttributeType::Number,
                'is_filterable' => true, 'is_main' => true, 'sort' => 10,
            ]),
            'voltage' => Attribute::query()->create([
                'name' => 'Напряжение', 'slug' => 'napryazhenie', 'unit' => 'В', 'type' => AttributeType::Number,
                'is_filterable' => true, 'is_main' => true, 'sort' => 20,
            ]),
            'width' => Attribute::query()->create([
                'name' => 'Ширина', 'slug' => 'shirina', 'unit' => 'мм', 'type' => AttributeType::Number,
                'is_filterable' => true, 'is_main' => false, 'sort' => 30,
            ]),
        ];
    }

    /**
     * @param  Collection<int, array{category: Category, prefix: string, icon: string}>  $subcategories
     * @param  Collection<int, Brand>  $brands
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  array{power: Attribute, voltage: Attribute, width: Attribute}  $attributes
     * @return Collection<int, Product>
     */
    private function seedProducts(
        Supplier $supplier,
        Collection $subcategories,
        Collection $brands,
        Collection $warehouses,
        array $attributes,
    ): Collection {
        $products = collect();
        $usedSlugs = [];

        for ($index = 1; $index <= self::PRODUCTS; $index++) {
            $subcategory = $subcategories[($index - 1) % $subcategories->count()];
            /** @var Brand $brand */
            $brand = $this->faker->randomElement($brands->all());
            $model = $subcategory['prefix'].'-'.$this->faker->numberBetween(10, 99).$this->faker->randomElement(['', '-01', '-02', 'Н', 'М']);
            $name = "{$subcategory['category']->name} {$brand->name} {$model}";
            [$minPrice, $maxPrice] = self::PRICE_RANGES[$subcategory['icon']];
            $price = $index % 10 === 0 ? null : Money::ofRubles($this->faker->numberBetween($minPrice, $maxPrice));
            $availability = self::GUARANTEED_AVAILABILITY[$index - 1] ?? $this->randomAvailability();

            $product = Product::query()->create([
                'supplier_id' => $supplier->id,
                'external_id' => $this->faker->uuid(),
                'supplier_code' => 'ЦБ-Ц'.str_pad((string) (1000000 + $index), 7, '0', STR_PAD_LEFT),
                'sku' => $index % 5 === 0 ? null : $this->faker->numerify('1##########'),
                'model' => $model,
                'name' => $name,
                'slug' => $this->uniqueSlug($name, $usedSlugs),
                'category_id' => $subcategory['category']->id,
                'brand_id' => $brand->id,
                'description' => $index % 8 === 0 ? null : $this->faker->realText(280),
                'unit' => 'шт',
                'rrp_price' => $price,
                'retail_price' => $price,
                'availability' => $availability,
                'is_visible' => true,
                'is_new' => $index % 10 === 3,
                'is_hit' => $index % 10 === 7,
            ]);

            $this->seedStocks($product, $warehouses);

            if ($index % 10 < 7) {
                $this->attachAttributes($product, $subcategory['icon'], $attributes);
            }

            if ($index % 5 === 1) {
                $this->attachImage($product, $subcategory['icon'], $index);
            }

            $products->push($product);
        }

        return $products;
    }

    private function randomAvailability(): Availability
    {
        $roll = $this->faker->numberBetween(1, 100);

        return match (true) {
            $roll <= 15 => Availability::InStock,
            $roll <= 20 => Availability::Low,
            $roll <= 25 => Availability::Incoming,
            $roll <= 95 => Availability::OnOrder,
            default => Availability::Discontinued,
        };
    }

    /**
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function seedStocks(Product $product, Collection $warehouses): void
    {
        $stocks = match ($product->availability) {
            Availability::InStock => collect($this->faker->randomElements($warehouses->all(), $this->faker->numberBetween(1, 3)))
                ->map(fn (Warehouse $warehouse, int $position): array => $position === 0
                    ? [$warehouse, WarehouseStockStatus::InStock, $this->faker->randomElement(['много', 'в наличии'])]
                    : [$warehouse, WarehouseStockStatus::Low, 'несколько']),
            Availability::Low => collect([[$this->faker->randomElement($warehouses->all()), WarehouseStockStatus::Low, 'несколько']]),
            default => collect(),
        };

        foreach ($stocks as [$warehouse, $status, $rawValue]) {
            ProductStock::query()->create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'status' => $status,
                'raw_value' => $rawValue,
                'unit' => 'шт',
                'synced_at' => now(),
            ]);
        }
    }

    /**
     * @param  array{power: Attribute, voltage: Attribute, width: Attribute}  $attributes
     */
    private function attachAttributes(Product $product, string $icon, array $attributes): void
    {
        $width = $this->faker->randomElement([300, 400, 530, 600, 700, 800, 900, 1000, 1200, 1500, 1800]);
        $values = [
            $attributes['width']->id => ['value_number' => "{$width}.000", 'raw_value' => "{$width} мм"],
        ];

        if (in_array($icon, self::POWERED, true)) {
            $power = $this->faker->randomElement(['0.500', '1.200', '2.500', '3.500', '4.000', '5.000', '6.000', '7.500', '9.000', '12.000', '18.000']);
            $voltage = $this->faker->randomElement([220, 380]);

            $values[$attributes['power']->id] = [
                'value_number' => $power,
                'raw_value' => str_replace('.', ',', rtrim(rtrim($power, '0'), '.'))."\u{00A0}кВт",
            ];
            $values[$attributes['voltage']->id] = ['value_number' => "{$voltage}.000", 'raw_value' => "{$voltage}\u{00A0}В"];
        }

        $product->attributeValues()->attach($values);
    }

    private function attachImage(Product $product, string $icon, int $index): void
    {
        $product->addMediaFromString($this->drawEquipment($icon, $index))
            ->usingFileName("demo-{$product->id}.jpg")
            ->withCustomProperties(['source' => 'manual', 'sort' => 1])
            ->toMediaCollection(Product::IMAGES);
    }

    /**
     * Draws a simple equipment silhouette on a white background as JPEG bytes.
     */
    private function drawEquipment(string $icon, int $variant): string
    {
        $size = 800;
        $image = imagecreatetruecolor($size, $size);
        $white = $this->color($image, 0xFF, 0xFF, 0xFF);
        $body = $this->color($image, 0xD3, 0xD9, 0xDE);
        $panel = $this->color($image, 0xE7, 0xEB, 0xEE);
        $edge = $this->color($image, 0x8A, 0x95, 0x9E);
        $dark = $this->color($image, 0x15, 0x19, 0x1D);
        imagefill($image, 0, 0, $white);
        imagesetthickness($image, 4);

        $shift = ($variant % 3) * 20;

        switch ($icon) {
            case 'neutral':
                imagefilledrectangle($image, 120, 300, 680, 340, $body);
                imagerectangle($image, 120, 300, 680, 340, $edge);
                foreach ([150, 630] as $x) {
                    imagefilledrectangle($image, $x, 340, $x + 20, 640, $edge);
                }
                imagefilledrectangle($image, 150, 520, 650, 540, $panel);
                imagerectangle($image, 150, 520, 650, 540, $edge);
                break;

            case 'electromechanical':
                imagefilledrectangle($image, 250, 420, 550, 660, $body);
                imagerectangle($image, 250, 420, 550, 660, $edge);
                imagefilledellipse($image, 400, 330, 360 - $shift, 200, $panel);
                imageellipse($image, 400, 330, 360 - $shift, 200, $edge);
                imagefilledellipse($image, 320, 540, 40, 40, $dark);
                break;

            case 'serving-line':
            case 'fast-food':
                imagefilledrectangle($image, 110, 360 - $shift, 690, 600, $body);
                imagerectangle($image, 110, 360 - $shift, 690, 600, $edge);
                imagefilledrectangle($image, 140, 390 - $shift, 660, 470 - $shift, $panel);
                imagerectangle($image, 140, 390 - $shift, 660, 470 - $shift, $edge);
                for ($knob = 0; $knob < 4; $knob++) {
                    imagefilledellipse($image, 220 + $knob * 120, 540, 36, 36, $dark);
                }
                break;

            case 'ventilation':
                imagefilledpolygon($image, [300, 220, 500, 220, 690, 520, 110, 520], $body);
                imagepolygon($image, [300, 220, 500, 220, 690, 520, 110, 520], $edge);
                imagefilledrectangle($image, 340, 120, 460, 220, $panel);
                imagerectangle($image, 340, 120, 460, 220, $edge);
                break;

            default:
                imagefilledrectangle($image, 220 - $shift, 120, 580 + $shift, 700, $body);
                imagerectangle($image, 220 - $shift, 120, 580 + $shift, 700, $edge);
                imagefilledrectangle($image, 250 - $shift, 220, 550 + $shift, 670, $panel);
                imagerectangle($image, 250 - $shift, 220, 550 + $shift, 670, $edge);
                imagefilledrectangle($image, 510 + $shift, 360, 526 + $shift, 520, $edge);
                for ($knob = 0; $knob < 3; $knob++) {
                    imagefilledellipse($image, 290 + $knob * 60, 170, 30, 30, $dark);
                }
                break;
        }

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    private function color(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $red, $green, $blue);

        if ($color === false) {
            throw new RuntimeException('Could not allocate a GD color.');
        }

        return $color;
    }

    /**
     * @param  array<string, true>  $usedSlugs
     */
    private function uniqueSlug(string $name, array &$usedSlugs): string
    {
        $base = Str::limit(Str::slug($name), 120, '');
        $slug = $base;
        $suffix = 2;

        while (isset($usedSlugs[$slug])) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        $usedSlugs[$slug] = true;

        return $slug;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedCollections(Collection $products): void
    {
        $collections = [
            ['Кафе до 50 посадок', 'kafe-do-50-posadok', 'cafe', ['thermal', 'refrigeration', 'neutral', 'dishwashing'], 8,
                'Базовый набор для кухни небольшого кафе: тепловое, холодильное и моечное оборудование.'],
            ['Бар', 'bar', 'bar', ['refrigeration', 'fast-food', 'electromechanical'], 6,
                'Холодильное оборудование и компактная техника для барной стойки.'],
            ['Кондитерский цех', 'konditerskiy-tsekh', 'bakery', ['thermal', 'electromechanical', 'neutral'], 7,
                'Печи, тестомесы и производственные столы для кондитерского производства.'],
        ];

        $sort = 0;

        foreach ($collections as [$name, $slug, $icon, $categoryIcons, $count, $description]) {
            $collection = ProductCollection::query()->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'icon' => $icon,
                'is_active' => true,
                'sort' => $sort += 10,
            ]);

            $candidates = $products
                ->filter(fn (Product $product): bool => $this->isOrderable($product)
                    && in_array($product->category->parent->icon, $categoryIcons, true))
                ->values();

            $position = 0;

            foreach ($this->faker->randomElements($candidates->all(), min($count, $candidates->count())) as $product) {
                $collection->products()->attach($product->id, ['sort' => $position += 10]);
            }
        }
    }

    private function seedUsers(): User
    {
        $admin = $this->createUser('admin@horeca.test', 'Администратор', UserRole::Admin);
        $this->createUser('manager@horeca.test', 'Менеджер', UserRole::Manager);

        $company = new Company([
            'legal_name' => 'ООО «Вкусная точка»',
            'brand_name' => 'Кафе «Причал»',
            'inn' => '9102000001',
            'kpp' => '910201001',
            'ogrn' => '1149102000001',
            'legal_address' => 'Республика Крым, г. Симферополь, ул. Пушкина, д. 1',
            'delivery_address' => 'Республика Крым, г. Симферополь, ул. Набережная, д. 10',
            'city' => 'Симферополь',
            'contact_person' => 'Ирина Смирнова',
            'phone' => '+79780000001',
            'email' => 'opt@horeca.test',
            'segment' => CompanySegment::Cafe,
        ]);
        $company->forceFill([
            'status' => CompanyStatus::Approved,
            'price_tier_id' => PriceTier::query()->where('slug', 'opt-1')->value('id'),
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ])->save();

        return $this->createUser('opt@horeca.test', 'Ирина Смирнова', UserRole::Customer, $company);
    }

    private function createUser(string $email, string $name, UserRole $role, ?Company $company = null): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
        $user->forceFill([
            'role' => $role,
            'is_active' => true,
            'company_id' => $company?->id,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedProductPrices(Collection $products, PriceTier $tier): void
    {
        $products
            ->filter(fn (Product $product): bool => $this->isOrderable($product))
            ->take(5)
            ->each(fn (Product $product): ProductPrice => ProductPrice::query()->create([
                'product_id' => $product->id,
                'price_tier_id' => $tier->id,
                'price' => $product->retail_price->withDiscount(Percent::ofBasisPoints(1500))->roundUpToRubles(),
            ]));
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function seedOrders(Collection $products, User $wholesaler, GenerateOrderNumber $generateOrderNumber): void
    {
        $orderable = $products->filter(fn (Product $product): bool => $this->isOrderable($product))->values();
        $manager = User::query()->where('email', 'manager@horeca.test')->firstOrFail();
        $tierDiscount = $wholesaler->company->priceTier->discount_percent;

        $specs = [
            [9, OrderStatus::Completed, false],
            [7, OrderStatus::Invoiced, true],
            [4, OrderStatus::Confirmed, false],
            [2, OrderStatus::Processing, true],
            [0, OrderStatus::New, false],
        ];

        foreach ($specs as $position => [$daysAgo, $status, $isWholesale]) {
            $createdAt = CarbonImmutable::now()->subDays($daysAgo)->setTime(9 + $position, 15);
            $lines = collect($this->faker->randomElements($orderable->all(), $this->faker->numberBetween(1, 3)))
                ->map(fn (Product $product): array => [
                    'product' => $product,
                    'qty' => $this->faker->numberBetween(1, 3),
                    'price' => $isWholesale
                        ? $product->retail_price->withDiscount($tierDiscount)->roundUpToRubles()
                        : $product->retail_price,
                ]);

            $subtotal = $lines->reduce(fn (Money $sum, array $line): Money => $sum->add($line['product']->retail_price->multiply($line['qty'])), Money::zero());
            $total = $lines->reduce(fn (Money $sum, array $line): Money => $sum->add($line['price']->multiply($line['qty'])), Money::zero());

            $order = new Order([
                'number' => $generateOrderNumber->handle($createdAt),
                'idempotency_key' => (string) Str::uuid(),
                'user_id' => $isWholesale ? $wholesaler->id : null,
                'company_id' => $isWholesale ? $wholesaler->company_id : null,
                'type' => $isWholesale ? OrderType::Wholesale : OrderType::Retail,
                'customer_name' => $isWholesale ? $wholesaler->name : $this->faker->name(),
                'phone' => $isWholesale ? $wholesaler->company->phone : '+7978'.$this->faker->numerify('#######'),
                'email' => $isWholesale ? $wholesaler->email : null,
                'is_legal_entity' => $isWholesale,
                'inn' => $isWholesale ? $wholesaler->company->inn : null,
                'company_name' => $isWholesale ? $wholesaler->company->legal_name : null,
                'delivery_method' => $isWholesale ? DeliveryMethod::TransportCompany : DeliveryMethod::Pickup,
                'delivery_city' => $isWholesale ? 'Симферополь' : null,
                'tk_name' => $isWholesale ? 'СДЭК' : null,
                'payment_method' => $isWholesale ? PaymentMethod::Invoice : PaymentMethod::Cash,
                'subtotal' => $subtotal,
                'discount' => $subtotal->subtract($total),
                'total' => $total,
            ]);
            $order->forceFill([
                'status' => $status,
                'manager_id' => $status === OrderStatus::New ? null : $manager->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            foreach ($lines as $line) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'sku' => $line['product']->sku,
                    'supplier_code' => $line['product']->supplier_code,
                    'name' => $line['product']->name,
                    'unit' => $line['product']->unit,
                    'availability' => $line['product']->availability,
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                    'sum' => $line['price']->multiply($line['qty']),
                ]);
            }

            $this->seedStatusHistory($order, $status, $manager, $createdAt);
        }
    }

    private function seedStatusHistory(Order $order, OrderStatus $finalStatus, User $manager, CarbonImmutable $createdAt): void
    {
        $path = [OrderStatus::New, OrderStatus::Processing, OrderStatus::Confirmed, OrderStatus::Invoiced, OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Completed];
        $previous = null;

        foreach ($path as $step => $status) {
            $log = new OrderStatusLog([
                'order_id' => $order->id,
                'from_status' => $previous,
                'to_status' => $status,
                'user_id' => $previous === null ? null : $manager->id,
            ]);
            $log->forceFill(['created_at' => $createdAt->addHours($step)])->save();

            if ($status === $finalStatus) {
                return;
            }

            $previous = $status;
        }
    }

    private function isOrderable(Product $product): bool
    {
        return ! $product->isPriceOnRequest() && $product->availability !== Availability::Discontinued;
    }
}
