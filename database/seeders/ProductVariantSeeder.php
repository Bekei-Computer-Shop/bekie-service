<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::query()->with('variants')->get()->each(function (Product $product): void {
            $variants = $product->variants;

            if ($variants->count() >= 2) {
                if (! $variants->contains('is_default', true)) {
                    $variants->first()->update(['is_default' => true]);
                }

                return;
            }

            if ($variants->count() === 1 && ! $variants->first()->is_default) {
                $variants->first()->update(['is_default' => true]);
            }

            $startingPosition = $variants->count() + 1;
            $labels = ['Standard', 'Premium'];

            foreach (array_slice($labels, $startingPosition - 1) as $offset => $label) {
                $position = $startingPosition + $offset;
                $price = round((float) ($product->price ?? 0) * ($position === 1 ? 1 : 1.1), 2);
                $stock = max(0, (int) ($product->stock_quantity ?? 0));
                $suffix = substr(sha1($product->getKey().'-'.$position), 0, 12);

                $product->variants()->create([
                    'name' => $product->name.' - '.$label,
                    'slug' => Str::slug($product->name.' '.$label).'-'.$suffix,
                    'sku' => substr((string) $product->sku, 0, 47).'-'.$suffix,
                    'price' => $price,
                    'cost_price' => round($price * 0.72, 2),
                    'stock_quantity' => $position === 1 ? $stock : intdiv($stock, 2),
                    'min_stock_alert' => 5,
                    'track_inventory' => true,
                    'in_stock' => $position === 1 ? $stock > 0 : intdiv($stock, 2) > 0,
                    'attributes' => ['tier' => strtolower($label)],
                    'is_default' => $position === 1,
                    'is_active' => true,
                    'sort_order' => $position - 1,
                ]);
            }
        });
    }
}
