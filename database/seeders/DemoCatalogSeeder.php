<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSerial;
use App\Models\ProductSerialHistory;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\Support\DemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo-only catalog on top of ProductCatalogSeeder: about 60 more products so
 * listings run to several pages and every product promotion has 10-20 real
 * products to target.
 *
 * Kept apart from ProductCatalogSeeder on purpose: that one also runs on every
 * production deploy (through PreProductionSeeder), this one only in local and
 * testing.
 *
 * Variants are the real configurations a product is sold in, built from the
 * `axes` below (RAM x storage for laptops, capacity x speed for memory, ...),
 * never more than ten and often just one. Each axis value carries the price
 * step it adds. Variant `attributes` hold the axis values, the same shape the
 * admin variant editor reads back.
 *
 * Inventory is kept consistent the way checkout expects it:
 *  - product stock is the sum of its variants' stock;
 *  - a serialized variant has exactly one `available` serial per unit on hand
 *    (plus `damaged` ones counted in `damaged_stock`);
 *  - each variant's opening stock is a `stock_in` movement.
 *
 * `state` covers the inventory and visibility cases the storefront and the
 * admin need to show: pending (nothing on hand, stock incoming), soldout,
 * overstock, inactive and deleted.
 *
 * Images reuse the category's own photo (CategorySeeder), so no new image URL
 * is invented here.
 *
 * Upserts by SKU across trashed rows, like ProductCatalogSeeder, so re-running
 * updates rather than duplicates.
 *
 * Usage: php artisan db:seed --class=DemoCatalogSeeder
 */
class DemoCatalogSeeder extends Seeder
{
    /** weight kg, length, width, height cm — per category. */
    private const DIMENSIONS = [
        'laptops' => [2.0, 36.0, 25.0, 2.2],
        'desktop-computers' => [9.5, 45.0, 21.0, 43.0],
        'monitors' => [6.5, 62.0, 22.0, 45.0],
        'graphics-cards' => [1.2, 30.0, 13.0, 5.5],
        'processors' => [0.1, 4.5, 4.5, 0.6],
        'motherboards' => [1.3, 30.5, 24.4, 6.0],
        'memory-ram' => [0.1, 13.5, 4.0, 0.8],
        'power-supplies' => [1.8, 16.0, 15.0, 8.6],
        'pc-cases' => [8.5, 45.0, 23.0, 46.0],
        'cpu-coolers' => [1.3, 39.0, 12.0, 5.2],
        'internal-ssds' => [0.01, 8.0, 2.2, 0.24],
        'hard-drives' => [0.6, 14.7, 10.2, 2.6],
        'external-storage' => [0.1, 8.8, 5.9, 1.3],
        'keyboards' => [1.1, 36.0, 15.0, 4.0],
        'mice' => [0.1, 13.0, 7.0, 4.2],
        'webcams' => [0.2, 10.0, 4.0, 5.0],
        'networking' => [0.6, 12.0, 12.0, 11.0],
        'headphones-headsets' => [0.3, 20.0, 18.0, 9.0],
        'speakers' => [3.5, 23.0, 15.0, 20.0],
        'printers-scanners' => [7.0, 36.0, 36.0, 18.0],
        'cables-adapters' => [0.1, 15.0, 10.0, 2.0],
        'power-banks-chargers' => [0.25, 7.0, 4.0, 3.5],
    ];

    /**
     * name, sku, category slug, brand, base price, short description,
     * variant axes, and optional: serialized, featured, sale (% off every
     * other variant), state.
     */
    private const PRODUCTS = [
        // Laptops
        ['name' => 'Lenovo Legion Pro 5i Gen 9', 'sku' => 'LEN-LEGPRO5I', 'category' => 'laptops', 'brand' => 'Lenovo', 'price' => 1499.00,
            'short' => '16-inch 240Hz gaming laptop with Core i7-14650HX and RTX 4060.',
            'axes' => ['ram' => ['16GB' => 0, '32GB' => 160], 'storage' => ['512GB' => 0, '1TB' => 90, '2TB' => 240]],
            'serialized' => true, 'featured' => true],
        ['name' => 'Dell XPS 14', 'sku' => 'DEL-XPS14', 'category' => 'laptops', 'brand' => 'Dell', 'price' => 1699.00,
            'short' => '14.5-inch creator laptop with Core Ultra 7 and an OLED option.',
            'axes' => ['ram' => ['16GB' => 0, '32GB' => 200], 'storage' => ['512GB' => 0, '1TB' => 150], 'color' => ['Platinum' => 0, 'Graphite' => 0]],
            'serialized' => true],
        ['name' => 'Apple MacBook Air 13 (M3)', 'sku' => 'APL-MBA13-M3', 'category' => 'laptops', 'brand' => 'Apple', 'price' => 1099.00,
            'short' => 'Fanless 13.6-inch laptop with the Apple M3 chip and 18-hour battery.',
            'axes' => ['ram' => ['8GB' => 0, '16GB' => 200, '24GB' => 400], 'storage' => ['256GB' => 0, '512GB' => 200]],
            'serialized' => true, 'featured' => true],
        ['name' => 'HP Victus 15 Gaming Laptop', 'sku' => 'HP-VICTUS15', 'category' => 'laptops', 'brand' => 'HP', 'price' => 849.00,
            'short' => 'Entry gaming laptop with Ryzen 5 and RTX 4050 at a student-friendly price.',
            'axes' => ['ram' => ['8GB' => 0, '16GB' => 60], 'storage' => ['512GB' => 0, '1TB' => 70], 'color' => ['Mica Silver' => 0, 'Performance Blue' => 0]],
            'serialized' => true, 'sale' => 10],
        ['name' => 'Acer Swift Go 14', 'sku' => 'ACR-SWIFTGO14', 'category' => 'laptops', 'brand' => 'Acer', 'price' => 799.00,
            'short' => 'Thin 14-inch OLED ultrabook for study and travel.',
            'axes' => ['cpu' => ['Core Ultra 5' => 0, 'Core Ultra 7' => 150], 'ram' => ['16GB' => 0, '32GB' => 150], 'storage' => ['512GB' => 0, '1TB' => 100]],
            'serialized' => true],
        ['name' => 'ASUS Vivobook 15', 'sku' => 'AS-VIVO15', 'category' => 'laptops', 'brand' => 'ASUS', 'price' => 549.00,
            'short' => 'Everyday 15.6-inch laptop for school and office work.',
            'axes' => ['ram' => ['8GB' => 0, '16GB' => 50], 'storage' => ['512GB' => 0, '1TB' => 60], 'color' => ['Quiet Blue' => 0, 'Cool Silver' => 0]],
            'serialized' => true],
        ['name' => 'MSI Katana 15 Gaming Laptop', 'sku' => 'MSI-KATANA15', 'category' => 'laptops', 'brand' => 'MSI', 'price' => 1099.00,
            'short' => '15.6-inch 144Hz gaming laptop with a choice of RTX graphics.',
            'axes' => ['gpu' => ['RTX 4050' => 0, 'RTX 4060' => 150, 'RTX 4070' => 350], 'ram' => ['16GB' => 0, '32GB' => 120]],
            'serialized' => true],
        ['name' => 'Lenovo ThinkPad E14 Gen 6', 'sku' => 'LEN-TPE14G6', 'category' => 'laptops', 'brand' => 'Lenovo', 'price' => 899.00,
            'short' => 'Business laptop with a spill-resistant keyboard and MIL-STD durability.',
            'axes' => ['cpu' => ['Core Ultra 5' => 0, 'Core Ultra 7' => 180], 'ram' => ['16GB' => 0, '32GB' => 140], 'storage' => ['512GB' => 0, '1TB' => 90]],
            'serialized' => true],

        // Desktop computers
        ['name' => 'HP OMEN 25L Gaming Desktop', 'sku' => 'HP-OMEN25L', 'category' => 'desktop-computers', 'brand' => 'HP', 'price' => 1299.00,
            'short' => 'Tool-less gaming tower with Core i7 and RTX graphics.',
            'axes' => ['gpu' => ['RTX 4060' => 0, 'RTX 4060 Ti' => 120, 'RTX 4070' => 300], 'ram' => ['16GB' => 0, '32GB' => 110]],
            'serialized' => true, 'featured' => true],
        ['name' => 'Dell XPS Desktop 8960', 'sku' => 'DEL-XPS8960', 'category' => 'desktop-computers', 'brand' => 'Dell', 'price' => 1399.00,
            'short' => 'Quiet creator desktop with 14th-gen Intel and room to upgrade.',
            'axes' => ['cpu' => ['Core i7-14700' => 0, 'Core i9-14900' => 250], 'ram' => ['16GB' => 0, '32GB' => 120], 'storage' => ['1TB' => 0, '2TB' => 150]],
            'serialized' => true],
        ['name' => 'Lenovo Legion Tower 5i Gen 8', 'sku' => 'LEN-LEGT5I', 'category' => 'desktop-computers', 'brand' => 'Lenovo', 'price' => 1499.00,
            'short' => 'Mid-tower gaming PC with Legion Coldfront cooling.',
            'axes' => ['gpu' => ['RTX 4060 Ti' => 0, 'RTX 4070' => 200, 'RTX 4070 SUPER' => 300], 'ram' => ['16GB' => 0, '32GB' => 110]],
            'serialized' => true],
        ['name' => 'Apple Mac mini (M4)', 'sku' => 'APL-MACMINI-M4', 'category' => 'desktop-computers', 'brand' => 'Apple', 'price' => 599.00,
            'short' => 'Five-inch-square desktop with the Apple M4 chip and Thunderbolt 4.',
            'axes' => ['ram' => ['16GB' => 0, '24GB' => 200, '32GB' => 400], 'storage' => ['256GB' => 0, '512GB' => 200, '1TB' => 400]],
            'serialized' => true, 'featured' => true],
        ['name' => 'ASUS ROG Strix G16CH Desktop', 'sku' => 'AS-STRIXG16CH', 'category' => 'desktop-computers', 'brand' => 'ASUS', 'price' => 1599.00,
            'short' => 'High-airflow gaming desktop built around the RTX 4070 family.',
            'axes' => ['gpu' => ['RTX 4070' => 0, 'RTX 4070 Ti SUPER' => 350], 'ram' => ['32GB' => 0, '64GB' => 180], 'storage' => ['1TB' => 0, '2TB' => 140]],
            'serialized' => true],
        ['name' => 'HP Pro Tower 290 G9', 'sku' => 'HP-PRO290G9', 'category' => 'desktop-computers', 'brand' => 'HP', 'price' => 649.00,
            'short' => 'Reliable office tower for front desks and back offices.',
            'axes' => ['cpu' => ['Core i3-13100' => 0, 'Core i5-13500' => 120, 'Core i7-13700' => 260], 'ram' => ['8GB' => 0, '16GB' => 50]],
            'serialized' => true, 'sale' => 8],
        ['name' => 'Dell OptiPlex 7020 Micro', 'sku' => 'DEL-OPTI7020M', 'category' => 'desktop-computers', 'brand' => 'Dell', 'price' => 799.00,
            'short' => 'One-litre business PC that mounts behind a monitor.',
            'axes' => ['cpu' => ['Core i5-14500T' => 0, 'Core i7-14700T' => 200], 'ram' => ['16GB' => 0, '32GB' => 100], 'storage' => ['256GB' => 0, '512GB' => 60]],
            'serialized' => true, 'state' => 'pending'],

        // Monitors
        ['name' => 'LG UltraGear 27GR95QE OLED Monitor', 'sku' => 'LG-27GR95QE', 'category' => 'monitors', 'brand' => 'LG', 'price' => 899.00,
            'short' => '27-inch 1440p 240Hz OLED gaming monitor with 0.03ms response.',
            'axes' => [], 'serialized' => true, 'featured' => true],
        ['name' => 'ASUS TUF Gaming VG27AQ1A Monitor', 'sku' => 'AS-VG27AQ1A', 'category' => 'monitors', 'brand' => 'ASUS', 'price' => 249.00,
            'short' => '27-inch 1440p 170Hz IPS monitor for competitive gaming.',
            'axes' => [], 'serialized' => true],
        ['name' => 'Dell P-Series Office Monitor', 'sku' => 'DEL-PSERIES', 'category' => 'monitors', 'brand' => 'Dell', 'price' => 199.00,
            'short' => 'Height-adjustable 100Hz IPS office monitor with USB hub.',
            'axes' => ['size' => ['24-inch (P2425H)' => 0, '27-inch (P2725H)' => 60]], 'serialized' => true],
        ['name' => 'Samsung ViewFinity S8 4K Monitor', 'sku' => 'SAM-VIEWS8', 'category' => 'monitors', 'brand' => 'Samsung', 'price' => 399.00,
            'short' => '4K UHD monitor with HDR10 and USB-C for creative work.',
            'axes' => ['size' => ['27-inch' => 0, '32-inch' => 150]], 'serialized' => true],
        ['name' => 'BenQ PD2706U Designer Monitor', 'sku' => 'BNQ-PD2706U', 'category' => 'monitors', 'brand' => 'BenQ', 'price' => 549.00,
            'short' => '27-inch 4K monitor calibrated for sRGB and Display P3.',
            'axes' => [], 'serialized' => true, 'state' => 'overstock'],
        ['name' => 'AOC 24G2SP Gaming Monitor', 'sku' => 'AOC-24G2SP', 'category' => 'monitors', 'brand' => 'AOC', 'price' => 159.00,
            'short' => '24-inch 165Hz IPS monitor — the budget esports pick.',
            'axes' => [], 'serialized' => true, 'sale' => 12],
        ['name' => 'MSI MAG 274UPF 4K Monitor', 'sku' => 'MSI-274UPF', 'category' => 'monitors', 'brand' => 'MSI', 'price' => 399.00,
            'short' => '27-inch 4K 144Hz Rapid IPS monitor with HDMI 2.1.',
            'axes' => [], 'serialized' => true],
        ['name' => 'Gigabyte M27Q Monitor', 'sku' => 'GIG-M27Q', 'category' => 'monitors', 'brand' => 'Gigabyte', 'price' => 279.00,
            'short' => '27-inch 1440p 170Hz KVM monitor. Hidden from the storefront.',
            'axes' => [], 'serialized' => true, 'state' => 'inactive'],

        // Graphics cards
        ['name' => 'MSI GeForce RTX 4060 Ti Ventus 2X', 'sku' => 'MSI-4060TI-V2X', 'category' => 'graphics-cards', 'brand' => 'MSI', 'price' => 399.00,
            'short' => 'Compact dual-fan RTX 4060 Ti for 1080p and 1440p gaming.',
            'axes' => ['memory' => ['8GB' => 0, '16GB' => 70]], 'serialized' => true, 'damaged' => true],
        ['name' => 'Gigabyte RTX 4070 Ti SUPER Gaming OC', 'sku' => 'GIG-4070TIS-GOC', 'category' => 'graphics-cards', 'brand' => 'Gigabyte', 'price' => 849.00,
            'short' => 'Triple-fan factory-overclocked RTX 4070 Ti SUPER with 16GB.',
            'axes' => [], 'serialized' => true],
        ['name' => 'Sapphire Radeon RX 7800 XT', 'sku' => 'SAP-RX7800XT', 'category' => 'graphics-cards', 'brand' => 'Sapphire', 'price' => 499.00,
            'short' => '16GB RDNA 3 card for high-refresh 1440p.',
            'axes' => ['edition' => ['Pulse' => 0, 'Nitro+' => 60]], 'serialized' => true],
        ['name' => 'ASUS Dual GeForce RTX 4060', 'sku' => 'AS-DUAL-4060', 'category' => 'graphics-cards', 'brand' => 'ASUS', 'price' => 299.00,
            'short' => 'Two-slot RTX 4060 that fits small-form-factor builds.',
            'axes' => ['edition' => ['Standard' => 0, 'OC' => 20, 'White OC' => 35]], 'serialized' => true],
        ['name' => 'XFX Speedster Radeon RX 7600', 'sku' => 'XFX-RX7600', 'category' => 'graphics-cards', 'brand' => 'XFX', 'price' => 259.00,
            'short' => '8GB entry card for smooth 1080p gaming.',
            'axes' => [], 'serialized' => true, 'sale' => 10],

        // Processors
        ['name' => 'AMD Ryzen 5 7600X Processor', 'sku' => 'AMD-R5-7600X', 'category' => 'processors', 'brand' => 'AMD', 'price' => 229.00,
            'short' => '6-core AM5 processor up to 5.3GHz.', 'axes' => [], 'serialized' => true],
        ['name' => 'Intel Core i5-14600K Processor', 'sku' => 'INT-I5-14600K', 'category' => 'processors', 'brand' => 'Intel', 'price' => 319.00,
            'short' => '14-core unlocked LGA1700 processor.', 'axes' => [], 'serialized' => true],
        ['name' => 'AMD Ryzen 7 9700X Processor', 'sku' => 'AMD-R7-9700X', 'category' => 'processors', 'brand' => 'AMD', 'price' => 359.00,
            'short' => '8-core Zen 5 processor at 65W.', 'axes' => [], 'serialized' => true, 'state' => 'soldout'],
        ['name' => 'Intel Core Ultra 7 265K Processor', 'sku' => 'INT-U7-265K', 'category' => 'processors', 'brand' => 'Intel', 'price' => 399.00,
            'short' => '20-core LGA1851 desktop processor with an NPU.', 'axes' => [], 'serialized' => true],

        // Motherboards
        ['name' => 'MSI MAG B650 Tomahawk WiFi', 'sku' => 'MSI-B650-TOMA', 'category' => 'motherboards', 'brand' => 'MSI', 'price' => 219.00,
            'short' => 'ATX AM5 board with 2.5G LAN and Wi-Fi 6E.', 'axes' => []],
        ['name' => 'Gigabyte B760 AORUS Elite AX', 'sku' => 'GIG-B760-ELITE', 'category' => 'motherboards', 'brand' => 'Gigabyte', 'price' => 189.00,
            'short' => 'ATX LGA1700 board with Wi-Fi 6E.', 'axes' => ['memory' => ['DDR5' => 0, 'DDR4' => -20]]],
        ['name' => 'ASUS TUF Gaming X670E-Plus WiFi', 'sku' => 'AS-X670E-TUF', 'category' => 'motherboards', 'brand' => 'ASUS', 'price' => 289.00,
            'short' => 'Durable ATX X670E board with PCIe 5.0.', 'axes' => []],

        // Memory
        ['name' => 'Corsair Vengeance DDR5 Memory Kit', 'sku' => 'COR-VENG-DDR5', 'category' => 'memory-ram', 'brand' => 'Corsair', 'price' => 89.00,
            'short' => 'Low-profile DDR5 desktop memory with Intel XMP 3.0.',
            'axes' => ['capacity' => ['16GB (2x8GB)' => -35, '32GB (2x16GB)' => 0, '64GB (2x32GB)' => 110], 'speed' => ['5600MT/s' => 0, '6000MT/s' => 10, '6400MT/s' => 25]],
            'featured' => true],
        ['name' => 'G.Skill Ripjaws S5 DDR5 Memory Kit', 'sku' => 'GSK-RIPS5', 'category' => 'memory-ram', 'brand' => 'G.Skill', 'price' => 84.00,
            'short' => 'Low-profile DDR5-6000 kit for compact air coolers.',
            'axes' => ['capacity' => ['32GB (2x16GB)' => 0, '48GB (2x24GB)' => 50, '64GB (2x32GB)' => 100], 'color' => ['Matte Black' => 0, 'Matte White' => 4]]],
        ['name' => 'Crucial DDR5 Laptop Memory (SODIMM)', 'sku' => 'CRU-SODIMM-DDR5', 'category' => 'memory-ram', 'brand' => 'Crucial', 'price' => 34.00,
            'short' => 'Single-module DDR5 SODIMM laptop upgrade.',
            'axes' => ['capacity' => ['8GB' => 0, '16GB' => 20, '32GB' => 55, '48GB' => 95], 'speed' => ['4800MT/s' => 0, '5600MT/s' => 6]]],
        ['name' => 'TeamGroup T-Force Delta RGB DDR5', 'sku' => 'TEAM-DELTA-DDR5', 'category' => 'memory-ram', 'brand' => 'TeamGroup', 'price' => 99.00,
            'short' => 'RGB DDR5 kit with a 120-degree light bar.',
            'axes' => ['capacity' => ['32GB (2x16GB)' => 0, '64GB (2x32GB)' => 105], 'color' => ['Black' => 0, 'White' => 6], 'speed' => ['6000MT/s' => 0, '6400MT/s' => 15]]],

        // Power, cases, cooling
        ['name' => 'be quiet! Pure Power 12 M Power Supply', 'sku' => 'BQ-PP12M', 'category' => 'power-supplies', 'brand' => 'be quiet!', 'price' => 99.00,
            'short' => '80 PLUS Gold modular ATX 3.0 power supply.',
            'axes' => ['wattage' => ['650W' => 0, '750W' => 15, '850W' => 35, '1000W' => 60]]],
        ['name' => 'NZXT H6 Flow PC Case', 'sku' => 'NZXT-H6FLOW', 'category' => 'pc-cases', 'brand' => 'NZXT', 'price' => 99.00,
            'short' => 'Dual-chamber mid-tower with panoramic glass.',
            'axes' => ['color' => ['Black' => 0, 'White' => 0]]],
        ['name' => 'Noctua NH-D15 G2 CPU Cooler', 'sku' => 'NOC-NHD15G2', 'category' => 'cpu-coolers', 'brand' => 'Noctua', 'price' => 149.00,
            'short' => 'Dual-tower air cooler with two 140mm fans.', 'axes' => []],
        ['name' => 'Arctic Liquid Freezer III AIO Cooler', 'sku' => 'ARC-LF3', 'category' => 'cpu-coolers', 'brand' => 'Arctic', 'price' => 99.00,
            'short' => 'All-in-one liquid cooler with an offset contact frame.',
            'axes' => ['size' => ['280mm' => -15, '360mm' => 0], 'color' => ['Black' => 0, 'White' => 5]]],

        // Storage
        ['name' => 'Crucial T500 NVMe SSD', 'sku' => 'CRU-T500', 'category' => 'internal-ssds', 'brand' => 'Crucial', 'price' => 64.00,
            'short' => 'PCIe 4.0 M.2 SSD up to 7,400 MB/s.',
            'axes' => ['capacity' => ['500GB' => 0, '1TB' => 30, '2TB' => 95, '4TB' => 230], 'heatsink' => ['No heatsink' => 0, 'Heatsink' => 15]]],
        ['name' => 'Kingston NV3 NVMe SSD', 'sku' => 'KIN-NV3', 'category' => 'internal-ssds', 'brand' => 'Kingston', 'price' => 39.00,
            'short' => 'Value PCIe 4.0 M.2 SSD for everyday upgrades.',
            'axes' => ['capacity' => ['500GB' => 0, '1TB' => 21, '2TB' => 76, '4TB' => 200]], 'sale' => 15],
        ['name' => 'Samsung 870 EVO SATA SSD', 'sku' => 'SAM-870EVO', 'category' => 'internal-ssds', 'brand' => 'Samsung', 'price' => 49.00,
            'short' => '2.5-inch SATA SSD to revive older laptops and desktops.',
            'axes' => ['capacity' => ['250GB' => 0, '500GB' => 20, '1TB' => 50, '2TB' => 120, '4TB' => 260]]],
        ['name' => 'Seagate BarraCuda Hard Drive', 'sku' => 'SEA-BARRA', 'category' => 'hard-drives', 'brand' => 'Seagate', 'price' => 49.00,
            'short' => '3.5-inch 7200RPM desktop hard drive.',
            'axes' => ['capacity' => ['1TB' => 0, '2TB' => 15, '4TB' => 45, '8TB' => 110]]],
        ['name' => 'Samsung T7 Shield Portable SSD', 'sku' => 'SAM-T7SHIELD', 'category' => 'external-storage', 'brand' => 'Samsung', 'price' => 89.00,
            'short' => 'Rugged IP65 USB 3.2 portable SSD.',
            'axes' => ['capacity' => ['1TB' => 0, '2TB' => 70, '4TB' => 200], 'color' => ['Black' => 0, 'Beige' => 0, 'Blue' => 0]]],

        // Peripherals
        ['name' => 'Keychron K8 Pro Mechanical Keyboard', 'sku' => 'KEY-K8PRO', 'category' => 'keyboards', 'brand' => 'Keychron', 'price' => 99.00,
            'short' => 'Hot-swappable wireless TKL keyboard with QMK/VIA.',
            'axes' => ['switch' => ['Gateron Red' => 0, 'Gateron Brown' => 0, 'Gateron Blue' => 0], 'layout' => ['US ANSI' => 0, 'UK ISO' => 5]]],
        ['name' => 'Logitech G Pro X TKL Keyboard', 'sku' => 'LOG-GPROXTKL', 'category' => 'keyboards', 'brand' => 'Logitech', 'price' => 199.00,
            'short' => 'Tournament wireless TKL with LIGHTSPEED.',
            'axes' => ['switch' => ['GX Red Linear' => 0, 'GX Brown Tactile' => 0, 'GX Blue Clicky' => 0], 'color' => ['Black' => 0, 'White' => 0, 'Magenta' => 0]]],
        ['name' => 'Corsair K70 RGB PRO Keyboard', 'sku' => 'COR-K70PRO', 'category' => 'keyboards', 'brand' => 'Corsair', 'price' => 159.00,
            'short' => 'Full-size aluminium keyboard with 8,000Hz polling.',
            'axes' => ['switch' => ['Cherry MX Red' => 0, 'Cherry MX Speed' => 10, 'Cherry MX Brown' => 0], 'layout' => ['US ANSI' => 0, 'UK ISO' => 5]]],
        ['name' => 'Logitech G502 X Plus Gaming Mouse', 'sku' => 'LOG-G502XP', 'category' => 'mice', 'brand' => 'Logitech', 'price' => 159.00,
            'short' => 'Wireless RGB gaming mouse with hybrid optical switches.',
            'axes' => ['color' => ['Black' => 0, 'White' => 0]]],
        ['name' => 'Razer Basilisk V3 Pro Mouse', 'sku' => 'RAZ-BASV3PRO', 'category' => 'mice', 'brand' => 'Razer', 'price' => 149.00,
            'short' => 'Customizable wireless mouse with a tilt scroll wheel.',
            'axes' => ['color' => ['Black' => 0, 'White' => 0]]],
        ['name' => 'Logitech MX Anywhere 3S Mouse', 'sku' => 'LOG-MXA3S', 'category' => 'mice', 'brand' => 'Logitech', 'price' => 79.00,
            'short' => 'Compact travel mouse that tracks on glass.',
            'axes' => ['color' => ['Graphite' => 0, 'Pale Grey' => 0, 'Rose' => 0]]],
        ['name' => 'Razer Kiyo Pro Webcam', 'sku' => 'RAZ-KIYOPRO', 'category' => 'webcams', 'brand' => 'Razer', 'price' => 129.00,
            'short' => '1080p 60fps webcam with an adaptive light sensor.', 'axes' => []],

        // Audio
        ['name' => 'HyperX Cloud III Wireless Headset', 'sku' => 'HYX-CLOUD3W', 'category' => 'headphones-headsets', 'brand' => 'HyperX', 'price' => 149.00,
            'short' => 'Wireless gaming headset with 120-hour battery.',
            'axes' => ['color' => ['Black' => 0, 'Black/Red' => 0]]],
        ['name' => 'SteelSeries Arctis Nova 7 Headset', 'sku' => 'SS-NOVA7', 'category' => 'headphones-headsets', 'brand' => 'SteelSeries', 'price' => 179.00,
            'short' => 'Simultaneous 2.4GHz and Bluetooth gaming headset.',
            'axes' => ['platform' => ['PC / PlayStation' => 0, 'Xbox' => 10], 'color' => ['Black' => 0, 'White' => 0]]],
        ['name' => 'Logitech Z407 Bluetooth Speakers', 'sku' => 'LOG-Z407', 'category' => 'speakers', 'brand' => 'Logitech', 'price' => 79.00,
            'short' => '2.1 desk speakers with a wireless control dial.', 'axes' => []],

        // Networking and accessories
        ['name' => 'TP-Link Deco X55 Mesh WiFi 6', 'sku' => 'TPL-DECOX55', 'category' => 'networking', 'brand' => 'TP-Link', 'price' => 129.00,
            'short' => 'AX3000 whole-home mesh Wi-Fi system.',
            'axes' => ['pack' => ['1-pack' => 0, '2-pack' => 70, '3-pack' => 130]]],
        ['name' => 'Ubiquiti UniFi U6+ Access Point', 'sku' => 'UBI-U6PLUS', 'category' => 'networking', 'brand' => 'Ubiquiti', 'price' => 129.00,
            'short' => 'Ceiling-mount Wi-Fi 6 access point powered over Ethernet.', 'axes' => []],
        ['name' => 'Anker USB-C to USB-C Cable (240W)', 'sku' => 'ANK-USBC240', 'category' => 'cables-adapters', 'brand' => 'Anker', 'price' => 15.00,
            'short' => 'Braided 240W charging cable for laptops and phones.',
            'axes' => ['length' => ['3ft' => 0, '6ft' => 3, '10ft' => 6], 'color' => ['Black' => 0, 'White' => 0]]],
        ['name' => 'UGREEN 8K HDMI 2.1 Cable', 'sku' => 'UGR-HDMI21', 'category' => 'cables-adapters', 'brand' => 'UGREEN', 'price' => 12.00,
            'short' => '48Gbps HDMI 2.1 cable for 4K 120Hz and 8K.',
            'axes' => ['length' => ['1m' => 0, '2m' => 3, '3m' => 5, '5m' => 10]]],
        ['name' => 'Anker Prime 100W GaN Charger', 'sku' => 'ANK-PRIME100W', 'category' => 'power-banks-chargers', 'brand' => 'Anker', 'price' => 79.00,
            'short' => 'Three-port 100W GaN charger for a laptop and two phones.',
            'axes' => ['color' => ['Black' => 0, 'White' => 0]]],
        ['name' => 'Brother HL-L2460DW Laser Printer', 'sku' => 'BRO-HLL2460DW', 'category' => 'printers-scanners', 'brand' => 'Brother', 'price' => 129.00,
            'short' => 'Wireless duplex mono laser printer for home offices.', 'axes' => [], 'serialized' => true],

        // Discontinued, soft-deleted: hidden everywhere but still restorable.
        ['name' => 'Logitech G915 TKL Keyboard (2021)', 'sku' => 'LOG-G915TKL', 'category' => 'keyboards', 'brand' => 'Logitech', 'price' => 229.00,
            'short' => 'Discontinued low-profile wireless keyboard.',
            'axes' => ['switch' => ['GL Linear' => 0, 'GL Tactile' => 0]], 'state' => 'deleted'],
    ];

    private ?int $actorId = null;

    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        $this->call(CategorySeeder::class);
        $this->actorId = User::query()->where('email', 'admin@example.com')->value('id');

        $categories = Category::query()->whereIn('slug', array_unique(array_column(self::PRODUCTS, 'category')))->get()->keyBy('slug');
        $variantCount = 0;

        foreach (self::PRODUCTS as $index => $definition) {
            $category = $categories[$definition['category']] ?? null;
            if ($category === null) {
                throw new \RuntimeException("Category {$definition['category']} is missing — run CategorySeeder first.");
            }

            $variants = $this->variantDefinitions($definition);
            $product = $this->seedProduct($definition, $variants, $category, 1000 + $index);
            $this->seedImages($product, $definition, $category);

            foreach ($variants as $sortOrder => $variant) {
                $model = $this->seedVariant($product, $definition, $variant, $sortOrder, $category);
                $this->seedOpeningStock($model, $variant);
                if ($definition['serialized'] ?? false) {
                    $this->seedSerials($product, $model, $variant);
                }
                $variantCount++;
            }

            if (($definition['state'] ?? null) === 'deleted' && ! $product->trashed()) {
                $product->delete();
            }
        }

        $this->command?->info(sprintf('Seeded %d demo products with %d variants.', count(self::PRODUCTS), $variantCount));
    }

    /**
     * Every combination of the product's axes, each with its price and stock.
     *
     * @return list<array{label: string, suffix: string, attributes: array<string, string>, price: float, sale_price: ?float, stock: int, incoming: int, reserved: int, damaged: int, max: int}>
     */
    private function variantDefinitions(array $definition): array
    {
        $combinations = [[]];
        foreach ($definition['axes'] as $axis => $values) {
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($values as $value => $delta) {
                    $next[] = $combination + [$axis => [$value, $delta]];
                }
            }
            $combinations = $next;
        }

        $state = $definition['state'] ?? null;
        $variants = [];

        foreach (array_slice($combinations, 0, 10) as $index => $combination) {
            $attributes = array_map(fn (array $pair): string => (string) $pair[0], $combination);
            $price = round($definition['price'] + array_sum(array_column($combination, 1)), 2);
            $onSale = isset($definition['sale']) && $index % 2 === 0;
            $max = 60;

            $stock = match (true) {
                in_array($state, ['pending', 'soldout'], true) => 0,
                $state === 'overstock' => $max + 15,
                $index % 7 === 3 => 0,
                $index % 7 === 5 => 2,
                default => 4 + (crc32($definition['sku'].'#'.$index) % 26),
            };

            $variants[] = [
                'label' => $attributes === [] ? 'Standard' : implode(' / ', $attributes),
                'suffix' => $attributes === [] ? 'STD' : strtoupper(Str::slug(implode(' ', $attributes))),
                'attributes' => $attributes,
                'price' => $price,
                'sale_price' => $onSale ? round($price * (100 - $definition['sale']) / 100, 0) - 0.01 : null,
                'stock' => $stock,
                // Only the pending product has stock on the way; every other
                // empty variant is plainly out of stock.
                'incoming' => $state === 'pending' ? 15 : 0,
                'reserved' => $index === 1 && $stock > 4 ? 2 : 0,
                'damaged' => ($definition['damaged'] ?? false) && $index === 0 ? 1 : 0,
                'max' => $max,
            ];
        }

        return $variants;
    }

    /** @param list<array<string, mixed>> $variants */
    private function seedProduct(array $definition, array $variants, Category $category, int $sortOrder): Product
    {
        $slug = Str::slug($definition['name']);
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($definition['brand'])],
            ['name' => $definition['brand'], 'is_active' => true],
        );

        $product = Product::withTrashed()->where('sku', $definition['sku'])->first()
            ?? Product::withTrashed()->where('slug', $slug)->first()
            ?? new Product;

        if ($product->trashed() && ($definition['state'] ?? null) !== 'deleted') {
            $product->restore();
        }

        $stock = array_sum(array_column($variants, 'stock'));
        $default = $variants[0];
        [$weight, $length, $width, $height] = self::DIMENSIONS[$definition['category']];
        $state = $definition['state'] ?? null;

        $product->fill([
            'sku' => $definition['sku'],
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $definition['name'],
            'slug' => $slug,
            'barcode' => $this->barcode($definition['sku']),
            'short_description' => $definition['short'],
            'description' => $definition['short'].' Sold with the full '.$definition['brand'].' manufacturer warranty and set up in-store by Bekie technicians on request.',
            'price' => min(array_column($variants, 'price')),
            // A product-level sale price would apply to every variant without
            // one (see ProductPromotionService::currentUnitPrice), so it is only
            // set when the product has a single variant.
            'sale_price' => count($variants) === 1 ? $default['sale_price'] : null,
            'cost_price' => round(min(array_column($variants, 'price')) * 0.72, 2),
            'stock_quantity' => $stock,
            'reserved_stock' => array_sum(array_column($variants, 'reserved')),
            'damaged_stock' => array_sum(array_column($variants, 'damaged')),
            'incoming_stock' => array_sum(array_column($variants, 'incoming')),
            'min_stock_alert' => 5,
            'max_stock_level' => $state === 'overstock' ? 60 : max(200, $stock * 3),
            'reorder_point' => 10,
            'track_inventory' => true,
            'is_serialized' => (bool) ($definition['serialized'] ?? false),
            'in_stock' => $stock > 0,
            'weight' => $weight,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'thumbnail' => $this->imageUrl($category, 800),
            'meta_title' => $definition['name'],
            'meta_description' => $definition['short'],
            'is_active' => $state !== 'inactive',
            'is_featured' => (bool) ($definition['featured'] ?? false),
            'is_digital' => false,
            'views_count' => crc32($definition['sku']) % 900,
            'sales_count' => 0,
            'sort_order' => $sortOrder,
        ])->save();

        return $product;
    }

    /** One to three images; one product per category gets only its thumbnail. */
    private function seedImages(Product $product, array $definition, Category $category): void
    {
        $count = 1 + (crc32($definition['sku']) % 3);
        $sizes = [[800, 'thumbnail', true], [1000, 'gallery', false], [1200, 'gallery', false]];

        foreach (array_slice($sizes, 0, $count) as $sortOrder => [$size, $type, $primary]) {
            ProductImage::query()->updateOrCreate(
                ['product_id' => $product->id, 'type' => $type, 'sort_order' => $sortOrder],
                [
                    'image' => $this->imageUrl($category, $size),
                    'disk' => 'public',
                    'mime_type' => 'image/jpeg',
                    'alt_text' => $definition['name'],
                    'title' => $definition['name'],
                    'is_primary' => $primary,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedVariant(Product $product, array $definition, array $variant, int $sortOrder, Category $category): ProductVariant
    {
        // Prefixed with the product SKU: variant sku and slug are unique
        // across all products, not per product.
        $sku = $definition['sku'].'-'.$variant['suffix'];
        [$weight, $length, $width, $height] = self::DIMENSIONS[$definition['category']];

        $model = ProductVariant::withTrashed()->firstOrNew(['sku' => $sku]);
        if ($model->trashed()) {
            $model->restore();
        }

        $model->fill([
            'product_id' => $product->id,
            'name' => $variant['label'],
            'slug' => Str::slug($sku.' '.$variant['label']),
            'barcode' => $this->barcode($sku),
            'price' => $variant['price'],
            'sale_price' => $variant['sale_price'],
            'cost_price' => round($variant['price'] * 0.72, 2),
            'stock_quantity' => $variant['stock'],
            'reserved_stock' => $variant['reserved'],
            'damaged_stock' => $variant['damaged'],
            'incoming_stock' => $variant['incoming'],
            'min_stock_alert' => 5,
            'max_stock_level' => $variant['max'],
            'track_inventory' => true,
            'is_serialized' => (bool) ($definition['serialized'] ?? false),
            'in_stock' => $variant['stock'] > 0,
            'weight' => $weight,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'image' => $this->imageUrl($category, 800),
            'attributes' => $variant['attributes'],
            'is_default' => $sortOrder === 0,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ])->save();

        return $model;
    }

    /**
     * The opening receipt behind the variant's stock, written once. A damaged
     * unit is received with the rest and then written off by an adjustment.
     */
    private function seedOpeningStock(ProductVariant $variant, array $definition): void
    {
        $received = $definition['stock'] + $definition['damaged'];
        if ($received === 0 || $this->hasMovement($variant, 'stock_in')) {
            return;
        }

        $this->movement($variant, 'stock_in', $received, 0, $received, 'Opening stock received');

        if ($definition['damaged'] > 0) {
            $this->movement($variant, 'adjust', -$definition['damaged'], $received, $definition['stock'], 'Damaged in transit');
        }
    }

    private function hasMovement(ProductVariant $variant, string $type): bool
    {
        return StockMovement::query()
            ->where('stockable_type', $variant->getMorphClass())
            ->where('stockable_id', (string) $variant->id)
            ->where('movement_type', $type)
            ->where('reference', DemoData::STOCK_MOVEMENT_REFERENCE)
            ->exists();
    }

    private function movement(ProductVariant $variant, string $type, int $quantity, int $previous, int $new, string $reason): void
    {
        StockMovement::query()->create([
            'stockable_type' => $variant->getMorphClass(),
            'stockable_id' => (string) $variant->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'previous_quantity' => $previous,
            'new_quantity' => $new,
            'reason' => $reason,
            'destination_location' => 'Phnom Penh Main',
            'reference' => DemoData::STOCK_MOVEMENT_REFERENCE,
            'metadata' => ['seeded_by' => self::class],
            'created_by_id' => $this->actorId,
        ]);
    }

    /**
     * One `available` serial per unit on hand and one `damaged` serial per
     * written-off unit. Only missing serials are added, so re-runs keep the
     * pool at the variant's stock.
     */
    private function seedSerials(Product $product, ProductVariant $variant, array $definition): void
    {
        $wanted = [ProductSerial::AVAILABLE => $definition['stock'], ProductSerial::DAMAGED => $definition['damaged']];

        foreach ($wanted as $status => $count) {
            $prefix = $status === ProductSerial::AVAILABLE ? 'A' : 'D';

            for ($unit = 1; $unit <= $count; $unit++) {
                $number = sprintf('%s-%s%04d', $variant->sku, $prefix, $unit);
                if (ProductSerial::query()->where('serial_number', $number)->exists()) {
                    continue;
                }

                $serial = ProductSerial::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'serial_number' => $number,
                    'status' => $status,
                    'warehouse' => 'Phnom Penh Main',
                    'receiving_reference' => DemoData::STOCK_SERIAL_REFERENCE,
                ]);

                $this->serialHistory($serial, 'received', null, ProductSerial::AVAILABLE);
                if ($status === ProductSerial::DAMAGED) {
                    $this->serialHistory($serial, 'damaged', ProductSerial::AVAILABLE, ProductSerial::DAMAGED, 'Damaged in transit');
                }
            }
        }
    }

    private function serialHistory(ProductSerial $serial, string $event, ?string $previous, string $next, ?string $note = null): void
    {
        ProductSerialHistory::query()->create([
            'product_serial_id' => $serial->id,
            'event' => $event,
            'previous_status' => $previous,
            'new_status' => $next,
            'actor_id' => $this->actorId,
            'reference' => DemoData::STOCK_SERIAL_REFERENCE,
            'note' => $note,
        ]);
    }

    /**
     * The category's own photo (set by CategorySeeder) at the requested size.
     */
    private function imageUrl(Category $category, int $size): ?string
    {
        if (! $category->image || ! preg_match('/photo-([0-9a-f-]+)/', $category->image, $match)) {
            return null;
        }

        return "https://images.unsplash.com/photo-{$match[1]}?auto=format&fit=crop&w={$size}&h={$size}&q=80";
    }

    /**
     * A stable 13-digit barcode derived from the SKU, so it never changes
     * between runs. The 31 prefix keeps it clear of the 20/29 ranges the other
     * catalog seeders count up from.
     */
    private function barcode(string $sku): string
    {
        return sprintf('31%011u', crc32($sku));
    }
}
