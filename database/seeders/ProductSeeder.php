<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Seed a small, realistic PC-parts catalog (8 products) covering the
     * in-stock / low-stock / out-of-stock states the admin list renders.
     * Idempotent: re-running updates existing rows keyed by SKU.
     */
    public function run(): void
    {
        $categories = [];
        foreach (['Graphics Cards', 'Processors', 'Motherboards', 'Memory', 'Storage', 'Power Supply'] as $name) {
            $categories[$name] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }

        $brands = [];
        foreach (['NVIDIA', 'Intel', 'ASUS', 'Corsair', 'Samsung'] as $name) {
            $brands[$name] = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }

        $products = [
            ['NVIDIA GeForce RTX 4090 Founders Edition', 'NV-RTX4090-FE', 'Graphics Cards', 'NVIDIA', 1599.00, 8, 5],
            ['NVIDIA RTX 4070 Founders Edition',         'NV-4070-FE',    'Graphics Cards', 'NVIDIA', 599.00,  22, 5],
            ['Intel Core i9-14900K Processor',           'INT-I9-14900K', 'Processors',     'Intel',  589.00,  15, 5],
            ['Intel Core i7-13700K',                     'INT-13700K',    'Processors',     'Intel',  399.00,  4,  5],
            ['ASUS ROG Maximus Z790 Dark Hero',          'AS-MAX-Z790',   'Motherboards',   'ASUS',   699.00,  3,  5],
            ['Corsair Dominator Platinum 64GB DDR5',     'COR-DP-64G5',   'Memory',         'Corsair', 299.00, 0,  5],
            ['Samsung 980 Pro 2TB NVMe SSD',             'SAM-980P-2TB',  'Storage',        'Samsung', 179.99, 42, 5],
            ['Corsair RM1000x 1000W 80+ Gold PSU',       'COR-RM1000X',   'Power Supply',   'Corsair', 199.99, 12, 5],
        ];

        foreach ($products as $index => [$name, $sku, $category, $brand, $price, $stock, $minAlert]) {
            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categories[$category]->id,
                    'brand_id' => $brands[$brand]->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'short_description' => $name.' — high-performance PC component.',
                    'description' => 'Genuine '.$brand.' '.$name.'. Backed by full manufacturer warranty.',
                    'price' => $price,
                    'cost_price' => round($price * 0.75, 2),
                    'stock_quantity' => $stock,
                    'min_stock_alert' => $minAlert,
                    'track_inventory' => true,
                    'in_stock' => $stock > 0,
                    'is_active' => true,
                    'is_featured' => $index < 2,
                    'sort_order' => $index,
                ]
            );
        }
    }
}
