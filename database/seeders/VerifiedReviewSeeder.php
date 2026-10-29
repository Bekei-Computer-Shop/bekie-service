<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OrderItem;
use App\Models\Review;
use Database\Seeders\Support\DemoData;
use Illuminate\Database\Seeder;

/**
 * Verified-purchase reviews written by demo shoppers about products they
 * received in a completed demo order — the counterpart to ReviewSeeder's
 * unverified moderation-queue reviews.
 *
 * One review per shopper and product, linked to the order it came from, with
 * a skewed rating mix (mostly 4-5 stars, a few low ones) and a mostly
 * approved status so storefront averages and the rating filter have data.
 * Products nobody bought keep zero reviews, for the empty state.
 *
 * Reviews hang off their demo order, so `demo:reset` removes them with it.
 * Re-running is safe: rows are matched on shopper, product and order.
 *
 * Usage: php artisan db:seed --class=VerifiedReviewSeeder
 */
class VerifiedReviewSeeder extends Seeder
{
    private const LIMIT = 150;

    private const RATINGS = [5, 4, 5, 5, 3, 4, 5, 2, 4, 5, 1, 4];

    private const TITLES = [
        5 => 'Exactly what I needed',
        4 => 'Solid purchase, small nitpicks',
        3 => 'Does the job',
        2 => 'Not quite what I expected',
        1 => 'Had problems with mine',
    ];

    private const COMMENTS = [
        5 => 'Arrived well packed and worked first time. Setup took a few minutes and performance has been excellent.',
        4 => 'Good value for the money. Runs well day to day; the included software could be better.',
        3 => 'Works as described, though nothing stands out. Delivery was on time.',
        2 => 'It works, but it runs louder and warmer than I hoped for the price.',
        1 => 'Stopped working properly after a week. The shop is handling the warranty claim.',
    ];

    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        $items = OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($query) => $query
                ->where('status', 'completed')
                ->whereIn('metadata->seeded_by', DemoData::ORDER_TAGS))
            ->with('order:id,user_id,delivered_at,created_at')
            ->orderBy('order_id')
            ->orderBy('id')
            ->get()
            ->unique(fn (OrderItem $item): string => $item->order->user_id.'|'.$item->product_id)
            ->take(self::LIMIT)
            ->values();

        foreach ($items as $number => $item) {
            $rating = self::RATINGS[$number % count(self::RATINGS)];
            $reviewedAt = ($item->order->delivered_at ?? $item->order->created_at)->copy()->addDays(2 + $number % 9);

            $review = Review::withTrashed()->updateOrCreate(
                ['user_id' => $item->order->user_id, 'product_id' => $item->product_id, 'order_id' => $item->order_id],
                [
                    'rating' => $rating,
                    'title' => self::TITLES[$rating],
                    'comment' => self::COMMENTS[$rating],
                    'status' => match (true) {
                        $number % 11 === 10 => 'rejected',
                        $number % 7 === 6 => 'pending',
                        default => 'approved',
                    },
                    'is_verified_purchase' => true,
                    'helpful_count' => ($number * 5) % 31,
                    'not_helpful_count' => $number % 4,
                    'images' => null,
                    'is_featured' => $rating === 5 && $number % 9 === 0,
                ],
            );
            $review->forceFill(['created_at' => $reviewedAt, 'updated_at' => $reviewedAt])->saveQuietly();
        }

        $this->command?->info(sprintf('Seeded %d verified-purchase reviews.', $items->count()));
    }
}
