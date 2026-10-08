<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class ReviewSeeder extends Seeder
{
    private const TOTAL_REVIEWS = 100;

    private const STATUSES = ['pending', 'approved', 'rejected', 'hidden'];

    private const COMMENTS = [
        'The product matched the description and works as expected.',
        'Good quality for the price. Delivery was on time.',
        'Setup was simple and the product feels well made.',
        'The item arrived safely and performs reliably.',
        'A useful product with a few small areas to improve.',
        'The packaging was neat and everything was included.',
        'Performance has been consistent during daily use.',
        'The product is convenient and easy to use.',
        'Quality is acceptable, though delivery took longer than expected.',
        'I am satisfied with the purchase and overall value.',
    ];

    public function run(): void
    {
        $customers = User::query()
            ->whereIn('email', CustomerSeeder::shopperEmails())
            ->orderBy('email')
            ->get();
        $products = Product::query()
            ->orderBy('name')
            ->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            throw new RuntimeException(
                'ReviewSeeder requires the demo customers and products. Run ProductCatalogSeeder and CustomerSeeder first.'
            );
        }

        $reviewsPerStatus = intdiv(self::TOTAL_REVIEWS, count(self::STATUSES));

        for ($number = 1; $number <= self::TOTAL_REVIEWS; $number++) {
            $status = self::STATUSES[intdiv($number - 1, $reviewsPerStatus)];
            $customer = $customers[($number - 1) % $customers->count()];
            $product = $products[($number - 1) % $products->count()];
            $title = sprintf('Demo customer review %03d', $number);

            Review::query()->updateOrCreate(
                ['title' => $title],
                [
                    'user_id' => $customer->id,
                    'product_id' => $product->id,
                    'order_id' => null,
                    'rating' => (($number * 7) % 5) + 1,
                    'comment' => self::COMMENTS[($number - 1) % count(self::COMMENTS)],
                    'status' => $status,
                    'is_verified_purchase' => false,
                    'helpful_count' => ($number * 3) % 24,
                    'not_helpful_count' => $number % 5,
                    'images' => null,
                    'is_featured' => $status === 'approved' && $number % 10 === 0,
                ],
            );
        }

        $this->command?->info('Seeded 100 customer reviews across pending, approved, rejected, and hidden statuses.');
    }
}
