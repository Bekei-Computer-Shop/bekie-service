<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 10 low-stock products and 10 out-of-stock products, purely to exercise the
 * admin Stock Management screens (StockController::alerts/index/summary):
 * the "low" filter needs `0 < stock_quantity <= min_stock_alert` rows and the
 * "out" filter needs `stock_quantity <= 0` rows, and ProductCatalogSeeder's
 * 30 products don't reliably produce either.
 *
 * Each product also gets one matching variant: ThisWeekOrderSeeder builds its
 * order lines from `ProductVariant::where('product_id', ...)->get()` and
 * indexes into that collection unconditionally, so any active product with
 * zero variants makes it divide by zero.
 *
 * Upserts by SKU, so it is safe to run repeatedly. Reuses the taxonomy from
 * CategorySeeder rather than inventing a parallel one.
 *
 * Usage: php artisan db:seed --class=StockAlertSeeder
 */
class StockAlertSeeder extends Seeder
{
    /** stock_quantity is > 0 and <= min_stock_alert on every row here. */
    private const LOW_STOCK_PRODUCTS = [
        ['name' => 'Corsair Vengeance RGB Pro DDR4 16GB', 'sku' => 'LOW-CORS-VENG16', 'category' => 'Memory (RAM)', 'brand' => 'Corsair', 'price' => 64.99, 'stock' => 3, 'min_stock_alert' => 10],
        ['name' => 'Logitech G502 HERO Gaming Mouse', 'sku' => 'LOW-LOG-G502', 'category' => 'Mice', 'brand' => 'Logitech', 'price' => 49.99, 'stock' => 4, 'min_stock_alert' => 10],
        ['name' => 'TP-Link Archer AX55 WiFi 6 Router', 'sku' => 'LOW-TPL-AX55', 'category' => 'Networking', 'brand' => 'TP-Link', 'price' => 89.99, 'stock' => 2, 'min_stock_alert' => 10],
        ['name' => 'Crucial MX500 1TB SATA SSD', 'sku' => 'LOW-CRU-MX500', 'category' => 'Internal SSDs', 'brand' => 'Crucial', 'price' => 74.99, 'stock' => 5, 'min_stock_alert' => 10],
        ['name' => 'HyperX Cloud II Gaming Headset', 'sku' => 'LOW-HYX-CLOUD2', 'category' => 'Headphones & Headsets', 'brand' => 'HyperX', 'price' => 99.99, 'stock' => 6, 'min_stock_alert' => 10],
        ['name' => 'Cooler Master Hyper 212 CPU Cooler', 'sku' => 'LOW-CM-HYPER212', 'category' => 'CPU Coolers', 'brand' => 'Cooler Master', 'price' => 44.99, 'stock' => 3, 'min_stock_alert' => 8],
        ['name' => 'EVGA SuperNOVA 650 GT Power Supply', 'sku' => 'LOW-EVGA-650GT', 'category' => 'Power Supplies', 'brand' => 'EVGA', 'price' => 89.99, 'stock' => 4, 'min_stock_alert' => 10],
        ['name' => 'Fractal Design Meshify C PC Case', 'sku' => 'LOW-FD-MESHC', 'category' => 'PC Cases', 'brand' => 'Fractal Design', 'price' => 99.99, 'stock' => 2, 'min_stock_alert' => 8],
        ['name' => 'Logitech C920 HD Pro Webcam', 'sku' => 'LOW-LOG-C920', 'category' => 'Webcams', 'brand' => 'Logitech', 'price' => 69.99, 'stock' => 5, 'min_stock_alert' => 10],
        ['name' => 'Canon PIXMA TS6420a All-in-One Printer', 'sku' => 'LOW-CAN-TS6420A', 'category' => 'Printers & Scanners', 'brand' => 'Canon', 'price' => 99.99, 'stock' => 3, 'min_stock_alert' => 8],
    ];

    /** stock_quantity is 0 on every row here (in_stock is derived false). */
    private const OUT_OF_STOCK_PRODUCTS = [
        ['name' => 'Gigabyte AORUS RTX 4080 Master', 'sku' => 'OUT-GIGA-4080M', 'category' => 'Graphics Cards', 'brand' => 'Gigabyte', 'price' => 1199.99, 'min_stock_alert' => 5],
        ['name' => 'AMD Ryzen 7 7800X3D Processor', 'sku' => 'OUT-AMD-7800X3D', 'category' => 'Processors', 'brand' => 'AMD', 'price' => 449.00, 'min_stock_alert' => 5],
        ['name' => 'ASRock B650 Steel Legend Motherboard', 'sku' => 'OUT-ASR-B650SL', 'category' => 'Motherboards', 'brand' => 'ASRock', 'price' => 219.99, 'min_stock_alert' => 5],
        ['name' => 'G.Skill Trident Z5 DDR5 32GB', 'sku' => 'OUT-GSK-TZ5-32', 'category' => 'Memory (RAM)', 'brand' => 'G.Skill', 'price' => 149.99, 'min_stock_alert' => 5],
        ['name' => 'Seasonic Focus GX-850 Power Supply', 'sku' => 'OUT-SEA-GX850', 'category' => 'Power Supplies', 'brand' => 'Seasonic', 'price' => 139.99, 'min_stock_alert' => 5],
        ['name' => 'NZXT H510 Flow PC Case', 'sku' => 'OUT-NZXT-H510F', 'category' => 'PC Cases', 'brand' => 'NZXT', 'price' => 84.99, 'min_stock_alert' => 5],
        ['name' => 'Western Digital Blue 2TB Hard Drive', 'sku' => 'OUT-WD-BLUE2TB', 'category' => 'Hard Drives', 'brand' => 'Western Digital', 'price' => 54.99, 'min_stock_alert' => 5],
        ['name' => 'Razer DeathAdder V3 Gaming Mouse', 'sku' => 'OUT-RAZ-DAV3', 'category' => 'Mice', 'brand' => 'Razer', 'price' => 69.99, 'min_stock_alert' => 5],
        ['name' => 'SteelSeries Apex Pro Mechanical Keyboard', 'sku' => 'OUT-SS-APEXPRO', 'category' => 'Keyboards', 'brand' => 'SteelSeries', 'price' => 219.99, 'min_stock_alert' => 5],
        ['name' => 'Epson EcoTank ET-4850 Printer', 'sku' => 'OUT-EPS-ET4850', 'category' => 'Printers & Scanners', 'brand' => 'Epson', 'price' => 379.99, 'min_stock_alert' => 5],
    ];

    private int $barcodeSequence = 2900000000000;

    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $index = 0;
        foreach (self::LOW_STOCK_PRODUCTS as $definition) {
            $product = $this->seedProduct($definition, $definition['stock'], $index++);
            $this->seedVariant($product, $definition, $definition['stock']);
        }

        foreach (self::OUT_OF_STOCK_PRODUCTS as $definition) {
            $product = $this->seedProduct($definition, 0, $index++);
            $this->seedVariant($product, $definition, 0);
        }

        $this->command?->info(sprintf(
            'Seeded %d low-stock and %d out-of-stock products.',
            count(self::LOW_STOCK_PRODUCTS),
            count(self::OUT_OF_STOCK_PRODUCTS)
        ));
    }

    private function seedProduct(array $definition, int $stock, int $index): Product
    {
        $category = Category::where('slug', Str::slug($definition['category']))->firstOrFail();
        $brand = Brand::firstOrCreate(
            ['slug' => Str::slug($definition['brand'])],
            ['name' => $definition['brand'], 'is_active' => true]
        );

        return Product::updateOrCreate(
            ['sku' => $definition['sku']],
            [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => $definition['name'],
                'slug' => Str::slug($definition['name']).'-'.Str::lower($definition['sku']),
                'barcode' => (string) $this->barcodeSequence++,
                'short_description' => $definition['name'],
                'description' => $definition['name'],
                'price' => $definition['price'],
                'sale_price' => null,
                'cost_price' => round($definition['price'] * 0.7, 2),
                'stock_quantity' => $stock,
                'reserved_stock' => 0,
                'damaged_stock' => 0,
                'incoming_stock' => 0,
                'min_stock_alert' => $definition['min_stock_alert'],
                'max_stock_level' => max(50, $definition['min_stock_alert'] * 10),
                'reorder_point' => $definition['min_stock_alert'],
                'track_inventory' => true,
                'in_stock' => $stock > 0,
                'meta_title' => $definition['name'],
                'meta_description' => $definition['name'],
                'is_active' => true,
                'is_featured' => false,
                'is_digital' => false,
                'views_count' => 0,
                'sales_count' => 0,
                'sort_order' => 1000 + $index,
            ]
        );
    }

    /**
     * Single default variant per product, stock-mirrored with the parent so
     * order-seeding code that reads a product's variants stays consistent
     * with the product-level low/out-of-stock figures above.
     */
    private function seedVariant(Product $product, array $definition, int $stock): void
    {
        $sku = $definition['sku'].'-DEFAULT';

        ProductVariant::updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'name' => 'Standard',
                'slug' => Str::slug($sku),
                'barcode' => (string) $this->barcodeSequence++,
                'price' => $definition['price'],
                'sale_price' => null,
                'cost_price' => round($definition['price'] * 0.7, 2),
                'stock_quantity' => $stock,
                'reserved_stock' => 0,
                'damaged_stock' => 0,
                'incoming_stock' => 0,
                'min_stock_alert' => $definition['min_stock_alert'],
                'max_stock_level' => max(50, $definition['min_stock_alert'] * 10),
                'track_inventory' => true,
                'in_stock' => $stock > 0,
                'attributes' => [],
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );
    }
}
