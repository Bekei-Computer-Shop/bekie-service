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
     * Seed a realistic storefront catalog for a computer shop with products
     * matching the storefront categories used by the admin and customer apps.
     */
    public function run(): void
    {
        $categories = [];
        foreach ([
            'Laptops',
            'Desktops',
            'Monitors',
            'Keyboards',
            'Mice',
            'Storage',
            'Networking',
            'Audio',
            'Accessories',
            'Gaming',
        ] as $index => $name) {
            $categories[$name] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => 'Computer shop essentials for everyday work and play.',
                    'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=900&q=80',
                    'icon' => 'desktop',
                    'is_active' => true,
                    'is_featured' => $index < 3,
                    'sort_order' => $index + 1,
                ]
            );
        }

        $brands = [];
        foreach ([
            'Dell', 'Lenovo', 'ASUS', 'Acer', 'HP', 'MSI', 'LG', 'Logitech', 'Razer',
            'Samsung', 'Western Digital', 'TP-Link', 'Sony', 'JBL', 'Anker', 'NZXT',
        ] as $name) {
            $brands[$name] = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }

        $products = [
            ['Dell XPS 13 Plus Laptop', 'DELL-XPS13-PLUS', 'Laptops', 'Dell', 1399.00, 12, 3, 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=80'],
            ['Lenovo Legion 5 Pro', 'LEN-LEGION5-PRO', 'Laptops', 'Lenovo', 1599.00, 8, 2, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=80'],
            ['ASUS ProArt PX13', 'ASUS-PROART-PX13', 'Laptops', 'ASUS', 1749.00, 6, 2, 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=900&q=80'],
            ['Acer Predator Helios 16', 'ACER-PREDATOR-HELIOS16', 'Gaming', 'Acer', 1999.00, 5, 2, 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=900&q=80'],
            ['HP Pavilion Gaming Desktop', 'HP-PAVILION-GAMING', 'Desktops', 'HP', 1499.00, 10, 3, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?auto=format&fit=crop&w=900&q=80'],
            ['ASUS ROG G22CH Desktop', 'ASUS-ROG-G22CH', 'Desktops', 'ASUS', 2199.00, 4, 2, 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=900&q=80'],
            ['Dell UltraSharp U2723QE', 'DELL-U2723QE', 'Monitors', 'Dell', 579.99, 15, 4, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=900&q=80'],
            ['LG UltraGear 27GN950', 'LG-ULTRAGEAR-27GN950', 'Monitors', 'LG', 899.99, 7, 2, 'https://images.unsplash.com/photo-1550009158-9ebf69173e03?auto=format&fit=crop&w=900&q=80'],
            ['ASUS Mechanical Keyboard TUF K1', 'ASUS-TUF-K1', 'Keyboards', 'ASUS', 119.99, 18, 4, 'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?auto=format&fit=crop&w=900&q=80'],
            ['Logitech G915 TKL', 'LOGI-G915-TKL', 'Keyboards', 'Logitech', 189.99, 12, 3, 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=900&q=80'],
            ['Logitech MX Master 3S', 'LOGI-MX-MASTER-3S', 'Mice', 'Logitech', 99.99, 24, 5, 'https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=900&q=80'],
            ['Razer DeathAdder V3', 'RAZER-DEATHADDER-V3', 'Mice', 'Razer', 79.99, 20, 5, 'https://images.unsplash.com/photo-1589254065909-b7086229d08c?auto=format&fit=crop&w=900&q=80'],
            ['Samsung 990 Pro 2TB NVMe SSD', 'SAMSUNG-990PRO-2TB', 'Storage', 'Samsung', 169.99, 30, 6, 'https://images.unsplash.com/photo-1583394838336-acd977736f90?auto=format&fit=crop&w=900&q=80'],
            ['WD Black SN850X 2TB', 'WD-BLACK-SN850X-2TB', 'Storage', 'Western Digital', 179.99, 22, 5, 'https://images.unsplash.com/photo-1555618568-8016a3d4c07c?auto=format&fit=crop&w=900&q=80'],
            ['TP-Link Deco XE75 Mesh WiFi', 'TPLINK-DECO-XE75', 'Networking', 'TP-Link', 249.99, 16, 4, 'https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&w=900&q=80'],
            ['ASUS RT-AX86U Pro Router', 'ASUS-RT-AX86U-PRO', 'Networking', 'ASUS', 299.99, 9, 2, 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?auto=format&fit=crop&w=900&q=80'],
            ['Sony WH-1000XM5 Headphones', 'SONY-WH1000XM5', 'Audio', 'Sony', 349.99, 11, 3, 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=900&q=80'],
            ['JBL Quantum 910 Wireless', 'JBL-QUANTUM-910', 'Audio', 'JBL', 289.99, 14, 3, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=80'],
            ['Anker 4-Port USB-C Hub', 'ANKER-USB-C-HUB', 'Accessories', 'Anker', 49.99, 26, 5, 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80'],
            ['NZXT H510 Flow Case', 'NZXT-H510-FLOW', 'Gaming', 'NZXT', 129.99, 13, 3, 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?auto=format&fit=crop&w=900&q=80'],
        ];

        foreach ($products as $index => [$name, $sku, $category, $brand, $price, $stock, $minAlert, $thumbnail]) {
            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categories[$category]->id,
                    'brand_id' => $brands[$brand]->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'short_description' => $name.' — a reliable everyday computer shop bestseller.',
                    'description' => 'Premium '.$brand.' '.$name.' built for speed, comfort, and long-term performance.',
                    'price' => $price,
                    'cost_price' => round($price * 0.72, 2),
                    'stock_quantity' => $stock,
                    'min_stock_alert' => $minAlert,
                    'track_inventory' => true,
                    'in_stock' => $stock > 0,
                    'thumbnail' => $thumbnail,
                    'is_active' => true,
                    'is_featured' => $index < 5,
                    'sort_order' => $index,
                ]
            );
        }

        $variationMap = [
            'SAMSUNG-990PRO-2TB' => [
                ['1TB', 'SAMSUNG-990PRO-1TB', 109.99, 18, ['capacity' => '1TB', 'interface' => 'PCIe 4.0']],
                ['2TB', 'SAMSUNG-990PRO-2TB-STD', 169.99, 30, ['capacity' => '2TB', 'interface' => 'PCIe 4.0']],
            ],
            'LOGI-G915-TKL' => [
                ['Black', 'LOGI-G915-TKL-BLK', 189.99, 12, ['color' => 'Black', 'layout' => 'TKL']],
                ['White', 'LOGI-G915-TKL-WHT', 199.99, 7, ['color' => 'White', 'layout' => 'TKL']],
            ],
            'DELL-U2723QE' => [
                ['27-inch 4K', 'DELL-U2723QE-4K', 579.99, 15, ['size' => '27-inch', 'resolution' => '4K']],
                ['27-inch QHD', 'DELL-U2723QE-QHD', 549.99, 8, ['size' => '27-inch', 'resolution' => 'QHD']],
            ],
        ];

        foreach ($variationMap as $productSku => $variants) {
            $product = Product::where('sku', $productSku)->firstOrFail();

            foreach ($variants as $position => [$name, $sku, $price, $stock, $attributes]) {
                ProductVariant::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $product->id,
                        'name' => $name,
                        'slug' => Str::slug($product->name.' '.$name),
                        'price' => $price,
                        'cost_price' => round($price * 0.72, 2),
                        'stock_quantity' => $stock,
                        'min_stock_alert' => 5,
                        'track_inventory' => true,
                        'in_stock' => $stock > 0,
                        'attributes' => $attributes,
                        'is_default' => $position === 0,
                        'is_active' => true,
                        'sort_order' => $position,
                    ]
                );
            }
        }
    }
}
