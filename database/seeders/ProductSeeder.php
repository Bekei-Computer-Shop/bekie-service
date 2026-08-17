<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
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
        $roots = [];
        foreach (['Computer Components', 'Storage', 'Laptop', 'Monitor', 'Accessories', 'Networking'] as $name) {
            $roots[$name] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }

        $categories = [];
        foreach ([
            'Graphics Cards' => 'Computer Components', 'Processors' => 'Computer Components', 'Motherboards' => 'Computer Components',
            'Memory' => 'Computer Components', 'Power Supply' => 'Computer Components', 'CPU Cooler' => 'Computer Components',
            'NVMe SSD' => 'Storage', 'Hard Drives' => 'Storage', 'Gaming Laptop' => 'Laptop', 'Business Laptop' => 'Laptop',
            'Gaming Monitor' => 'Monitor', 'Office Monitor' => 'Monitor', 'Keyboard' => 'Accessories', 'Mouse' => 'Accessories',
            'Router' => 'Networking',
        ] as $name => $parent) {
            $categories[$name] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'parent_id' => $roots[$parent]->id, 'is_active' => true]
            );
        }

        $brands = [];
        foreach (['ASUS', 'MSI', 'Gigabyte', 'ASRock', 'Acer', 'Lenovo', 'HP', 'Dell', 'Intel', 'AMD', 'NVIDIA', 'Corsair', 'Kingston', 'Samsung', 'Western Digital', 'Seagate', 'Logitech', 'Razer', 'Cooler Master', 'NZXT'] as $name) {
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
            ['Samsung 980 Pro 2TB NVMe SSD',             'SAM-980P-2TB',  'NVMe SSD',       'Samsung', 179.99, 42, 5],
            ['Corsair RM1000x 1000W 80+ Gold PSU',       'COR-RM1000X',   'Power Supply',   'Corsair', 199.99, 12, 5],
            ['AMD Ryzen 7 7800X3D Processor',            'AMD-R7-7800X3D', 'Processors',     'AMD',    449.00, 18, 5],
            ['MSI MAG B650 Tomahawk WiFi',               'MSI-B650-TOMA', 'Motherboards',   'MSI',    219.99, 14, 4],
            ['ASUS TUF Gaming GeForce RTX 4070',         'AS-RTX4070-TUF', 'Graphics Cards', 'ASUS',   649.99, 9,  4],
            ['Kingston Fury Beast 32GB DDR5-6000',       'KIN-FURY-32G',  'Memory',         'Kingston', 129.99, 28, 5],
            ['Samsung 990 Pro 2TB NVMe SSD',             'SAM-990P-2TB',  'NVMe SSD',       'Samsung', 169.99, 31, 5],
            ['Lenovo Legion 5 16-inch Gaming Laptop',    'LEN-LEGION5-16', 'Gaming Laptop',  'Lenovo', 1299.00, 7,  3],
            ['Dell UltraSharp U2723QE 4K Monitor',       'DEL-U2723QE',   'Office Monitor', 'Dell',   579.99, 11, 4],
            ['Logitech G Pro X Superlight 2',            'LOG-GPXSL2',    'Mouse',          'Logitech', 159.99, 25, 5],
            ['Razer BlackWidow V4 Keyboard',             'RAZ-BWV4',      'Keyboard',       'Razer', 169.99, 16, 5],
            ['ASUS RT-AX86U Pro Router',                 'AS-RTAX86UPRO', 'Router',         'ASUS',  249.99, 10, 4],
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

        foreach ([
            ['SAM-990P-2TB', '1TB', 'SAM-990P-1TB', 99.99, 38, ['capacity' => '1TB', 'interface' => 'PCIe 4.0']],
            ['SAM-990P-2TB', '2TB', 'SAM-990P-2TB-VAR', 169.99, 31, ['capacity' => '2TB', 'interface' => 'PCIe 4.0']],
            ['KIN-FURY-32G', '16GB DDR5-6000', 'KIN-FURY-16G', 74.99, 40, ['capacity' => '16GB', 'speed' => '6000MHz']],
            ['KIN-FURY-32G', '32GB DDR5-6000', 'KIN-FURY-32G-VAR', 129.99, 28, ['capacity' => '32GB', 'speed' => '6000MHz']],
            ['LEN-LEGION5-16', '16GB / 512GB / Black', 'LEN-LEG5-16-512', 1299.00, 7, ['ram' => '16GB', 'storage' => '512GB', 'color' => 'Black']],
            ['LEN-LEGION5-16', '32GB / 1TB / Storm Grey', 'LEN-LEG5-32-1T', 1499.00, 5, ['ram' => '32GB', 'storage' => '1TB', 'color' => 'Storm Grey']],
            ['DEL-U2723QE', '27-inch 4K', 'DEL-U2723QE-27', 579.99, 11, ['size' => '27-inch', 'resolution' => '4K', 'refresh_rate' => '60Hz']],
        ] as $index => [$productSku, $name, $sku, $price, $stock, $attributes]) {
            $product = Product::where('sku', $productSku)->firstOrFail();

            ProductVariant::updateOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $product->id,
                    'name' => $name,
                    'slug' => Str::slug($product->name.' '.$name),
                    'price' => $price,
                    'cost_price' => round($price * 0.75, 2),
                    'stock_quantity' => $stock,
                    'min_stock_alert' => 5,
                    'track_inventory' => true,
                    'in_stock' => $stock > 0,
                    'attributes' => $attributes,
                    'is_default' => $index === 0,
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
