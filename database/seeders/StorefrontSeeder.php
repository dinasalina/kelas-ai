<?php

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorefrontSeeder extends Seeder
{
    /**
     * Categories with their products: name, description, price, emoji, cost price.
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: float, 3: string, 4: float}>>
     */
    protected array $catalog = [
        'Makanan' => [
            ['Nasi Lemak Ayam Goreng', 'Nasi lemak berlaukkan ayam goreng rangup, sambal, telur dan kacang.', 9.50, '🍛', 5.50],
            ['Mee Goreng Mamak', 'Mee kuning digoreng bersama sayur, telur dan udang.', 7.00, '🍜', 3.80],
            ['Roti Canai Kosong', 'Roti canai rangup disajikan dengan kuah dhal dan sambal.', 2.20, '🫓', 0.90],
            ['Ayam Percik', 'Ayam panggang bersama kuah percik pedas manis khas pantai timur.', 12.00, '🍗', 7.20],
        ],
        'Minuman' => [
            ['Teh Tarik', 'Teh tarik panas berbuih, dibancuh secara tradisional.', 2.80, '🍵', 1.00],
            ['Air Bandung', 'Minuman sejuk sirap bandung bersama susu.', 3.00, '🥤', 1.10],
            ['Kopi O Ais', 'Kopi hitam ais tanpa susu, manis sederhana.', 2.50, '🧋', 0.80],
            ['Sirap Limau', 'Minuman sejuk sirap dan limau nipis segar.', 3.20, '🍹', 1.20],
        ],
        'Snek & Kuih' => [
            ['Kuih Lapis', 'Kuih tradisional berlapis warna-warni, lembut dan manis.', 1.50, '🍰', 0.70],
            ['Karipap Pusing', 'Pastri diisi kentang dan daging berperisa kari.', 1.20, '🥟', 0.50],
            ['Keropok Lekor', 'Keropok ikan goreng khas Terengganu, disajikan dengan cili.', 5.00, '🍤', 2.80],
            ['Cucur Udang', 'Cucur rangup berisi udang, disajikan dengan sos kacang.', 4.50, '🍢', 2.30],
        ],
    ];

    /**
     * Gradient colour pairs used for the generated product images, keyed by category.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected array $gradients = [
        'Makanan' => ['#fb923c', '#fde68a'],
        'Minuman' => ['#38bdf8', '#a5f3fc'],
        'Snek & Kuih' => ['#f472b6', '#fbcfe8'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $staff = User::where('role', 'staff')->first();

        $zones = collect([
            ['name' => 'Dalam Bandar', 'fee' => 3.00],
            ['name' => 'Pinggir Bandar', 'fee' => 5.00],
            ['name' => 'Luar Kawasan', 'fee' => 8.00],
        ])->map(fn (array $zone) => DeliveryZone::create([...$zone, 'is_active' => true]));

        foreach ($this->catalog as $categoryName => $products) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'description' => "Produk dalam kategori {$categoryName}.",
            ]);

            $createdProducts = collect($products)->map(function (array $product) use ($category, $categoryName) {
                $slug = Str::slug($product[0]).'-'.Str::random(4);

                return Product::create([
                    'category_id' => $category->id,
                    'name' => $product[0],
                    'slug' => $slug,
                    'description' => $product[1],
                    'price' => $product[2],
                    'cost_price' => $product[4],
                    'image_path' => $this->createProductImage($slug, $product[3], $this->gradients[$categoryName]),
                    'stock' => fake()->numberBetween(5, 50),
                    'is_active' => true,
                ]);
            });

            collect([
                Order::factory()->for($createdProducts->random())->create(),
                Order::factory()->for($createdProducts->random())->placedByStaff($staff)->confirmed()->create(),
                Order::factory()->for($createdProducts->random())->preparing()->create(),
                Order::factory()->for($createdProducts->random())->delivering()->create(),
                Order::factory()->for($createdProducts->random())->completed()->create(),
            ])->each(function (Order $order) use ($staff, $zones) {
                if (fake()->boolean(70)) {
                    $zone = $zones->random();
                    $order->forceFill([
                        'delivery_zone_id' => $zone->id,
                        'delivery_fee' => $zone->fee,
                        'total_price' => $order->total_price + $zone->fee,
                    ])->save();
                }

                $this->seedStatusHistory($order, $staff);
            });

            $cancelled = Order::factory()->for($createdProducts->random())->cancelled()->create();
            $this->seedStatusHistory($cancelled, $staff);
        }

        Coupon::create([
            'code' => 'DISKAUN10',
            'type' => CouponType::Percentage,
            'value' => 10,
            'min_order_amount' => null,
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'JIMAT5',
            'type' => CouponType::Fixed,
            'value' => 5,
            'min_order_amount' => 20,
            'expires_at' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Seed a realistic status-change history for a demo order, with staggered timestamps.
     */
    protected function seedStatusHistory(Order $order, ?User $staff): void
    {
        $timestamp = now()->subMinutes(fake()->numberBetween(180, 41760));

        $order->forceFill(['created_at' => $timestamp])->save();

        $order->statusHistories()->forceCreate([
            'from_status' => null,
            'to_status' => OrderStatus::Pending,
            'changed_by_staff_id' => $order->placed_by_staff_id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        if ($order->status === OrderStatus::Cancelled) {
            $timestamp = $timestamp->addMinutes(fake()->numberBetween(10, 60));

            $order->statusHistories()->forceCreate([
                'from_status' => OrderStatus::Pending,
                'to_status' => OrderStatus::Cancelled,
                'changed_by_staff_id' => $staff?->id,
                'note' => 'Pelanggan membatalkan tempahan.',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $order->forceFill(['processed_by_staff_id' => $staff?->id])->save();

            return;
        }

        $flow = OrderStatus::flow();

        for ($i = 1; $i <= $order->status->flowIndex(); $i++) {
            $timestamp = $timestamp->addMinutes(fake()->numberBetween(10, 45));

            $order->statusHistories()->forceCreate([
                'from_status' => $flow[$i - 1],
                'to_status' => $flow[$i],
                'changed_by_staff_id' => $staff?->id,
                'note' => $flow[$i] === OrderStatus::Delivering ? 'Rider dalam perjalanan.' : null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        if ($order->status !== OrderStatus::Pending) {
            $order->forceFill(['processed_by_staff_id' => $staff?->id])->save();
        }
    }

    /**
     * Generate a simple gradient SVG placeholder image for a product and return its storage path.
     *
     * @param  array{0: string, 1: string}  $colors
     */
    protected function createProductImage(string $slug, string $emoji, array $colors): string
    {
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
            <defs>
                <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="{$colors[0]}"/>
                    <stop offset="100%" stop-color="{$colors[1]}"/>
                </linearGradient>
            </defs>
            <rect width="800" height="600" fill="url(#bg)"/>
            <circle cx="400" cy="300" r="180" fill="#ffffff" fill-opacity="0.3"/>
            <text x="400" y="310" font-size="200" text-anchor="middle" dominant-baseline="central">{$emoji}</text>
        </svg>
        SVG;

        $path = "products/{$slug}.svg";

        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
