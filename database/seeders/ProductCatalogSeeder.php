<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The full storefront catalog: 30 products, all genuinely computer-shop
 * merchandise (laptops, PC components, storage, peripherals, networking,
 * audio, printers and computer accessories — no phones, wearables, cameras,
 * smart home, fashion or appliances). Every product populates every
 * catalog-facing column on `products` (pricing, inventory, physical
 * dimensions, SEO, warehouse location, ...), ships a thumbnail plus a
 * two-image gallery, and carries at least three fully specified variants.
 *
 * Each product's `category` is the exact name of a leaf category already
 * seeded by CategorySeeder (a top-level department or one of its children),
 * so both seeders share one taxonomy instead of maintaining parallel trees.
 *
 * Upserts by SKU (products) and by SKU (variants), so it is safe to run
 * repeatedly — including on every deploy. PreProductionSeeder calls this
 * seeder for the Render/Cloud Run pipelines instead of keeping its own
 * inline product list.
 *
 * Usage: php artisan db:seed --class=ProductCatalogSeeder
 */
class ProductCatalogSeeder extends Seeder
{
    /**
     * 30 products. Every entry carries pricing, physical dimensions and at
     * least 3 variants; variant `attributes` hold the axis values the admin
     * variant editor reads back (color / storage / size / ...). `category`
     * must match the name of a category CategorySeeder already created.
     */
    private const PRODUCTS = [
        [
            'name' => 'ASUS ROG Zephyrus G16 Gaming Laptop',
            'sku' => 'AS-ZEPH-G16',
            'category' => 'Laptops',
            'brand' => 'ASUS',
            'price' => 2199.00,
            'sale_price' => null,
            'short_description' => 'Slim 16-inch OLED gaming laptop with Intel Core Ultra 9 and RTX 4080.',
            'description' => 'The ROG Zephyrus G16 pairs a 240Hz OLED display with an Intel Core Ultra 9 and NVIDIA RTX graphics in a 1.5kg CNC-milled aluminium chassis. Backed by the full ASUS manufacturer warranty.',
            'is_featured' => true,
            'weight' => 1.85, 'length' => 35.5, 'width' => 24.6, 'height' => 1.5,
            'variants' => [
                ['label' => '16GB / 1TB / Eclipse Gray', 'suffix' => 'EG-16-1T', 'price' => 2199.00, 'sale_price' => null, 'stock' => 12, 'attributes' => ['ram' => '16GB', 'storage' => '1TB', 'color' => 'Eclipse Gray']],
                ['label' => '32GB / 1TB / Eclipse Gray', 'suffix' => 'EG-32-1T', 'price' => 2499.00, 'sale_price' => null, 'stock' => 8, 'attributes' => ['ram' => '32GB', 'storage' => '1TB', 'color' => 'Eclipse Gray']],
                ['label' => '32GB / 2TB / Platinum White', 'suffix' => 'PW-32-2T', 'price' => 2699.00, 'sale_price' => null, 'stock' => 5, 'attributes' => ['ram' => '32GB', 'storage' => '2TB', 'color' => 'Platinum White']],
            ],
        ],
        [
            'name' => 'Samsung 990 PRO NVMe SSD',
            'sku' => 'SAM-990PRO',
            'category' => 'Internal SSDs',
            'brand' => 'Samsung',
            'price' => 169.99,
            'sale_price' => null,
            'short_description' => 'PCIe 4.0 M.2 drive reaching 7,450 MB/s sequential reads.',
            'description' => 'Samsung 990 PRO with the in-house Pascal controller and V-NAND. Nickel-coated controller and heat-spreader label for sustained thermal control in laptops and consoles.',
            'is_featured' => true,
            'weight' => 0.01, 'length' => 8.0, 'width' => 2.2, 'height' => 0.23,
            'variants' => [
                ['label' => '1TB', 'suffix' => '1TB', 'price' => 99.99, 'sale_price' => null, 'stock' => 48, 'attributes' => ['capacity' => '1TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '2TB', 'suffix' => '2TB', 'price' => 169.99, 'sale_price' => 149.99, 'stock' => 31, 'attributes' => ['capacity' => '2TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '4TB', 'suffix' => '4TB', 'price' => 329.99, 'sale_price' => null, 'stock' => 12, 'attributes' => ['capacity' => '4TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
            ],
        ],
        [
            'name' => 'Kingston FURY Beast DDR5 Memory Kit',
            'sku' => 'KIN-FURY-DDR5',
            'category' => 'Memory (RAM)',
            'brand' => 'Kingston',
            'price' => 129.99,
            'sale_price' => null,
            'short_description' => 'Plug-and-play DDR5 kits with Intel XMP 3.0 and AMD EXPO profiles.',
            'description' => 'Kingston FURY Beast DDR5 ships with on-die ECC and a low-profile heat spreader that clears oversized CPU coolers. Automatic overclocking to the rated speed on XMP or EXPO boards.',
            'is_featured' => false,
            'weight' => 0.06, 'length' => 13.3, 'width' => 3.4, 'height' => 0.7,
            'variants' => [
                ['label' => '16GB (2x8GB) 5600MHz', 'suffix' => '16G-5600', 'price' => 74.99, 'sale_price' => null, 'stock' => 40, 'attributes' => ['capacity' => '16GB', 'speed' => '5600MHz', 'kit' => '2x8GB']],
                ['label' => '32GB (2x16GB) 6000MHz', 'suffix' => '32G-6000', 'price' => 129.99, 'sale_price' => null, 'stock' => 26, 'attributes' => ['capacity' => '32GB', 'speed' => '6000MHz', 'kit' => '2x16GB']],
                ['label' => '64GB (2x32GB) 6000MHz', 'suffix' => '64G-6000', 'price' => 239.99, 'sale_price' => null, 'stock' => 8, 'attributes' => ['capacity' => '64GB', 'speed' => '6000MHz', 'kit' => '2x32GB']],
            ],
        ],
        [
            'name' => 'Logitech MX Master 3S Wireless Mouse',
            'sku' => 'LOG-MXM3S',
            'category' => 'Mice',
            'brand' => 'Logitech',
            'price' => 99.99,
            'sale_price' => null,
            'short_description' => '8K DPI sensor with near-silent clicks and MagSpeed scrolling.',
            'description' => 'The MX Master 3S tracks on glass, scrolls 1,000 lines a second with the MagSpeed wheel, and pairs to three machines over Bluetooth or the Logi Bolt receiver.',
            'is_featured' => false,
            'weight' => 0.14, 'length' => 12.4, 'width' => 8.4, 'height' => 5.1,
            'variants' => [
                ['label' => 'Graphite', 'suffix' => 'GRAPHITE', 'price' => 99.99, 'sale_price' => null, 'stock' => 34, 'attributes' => ['color' => 'Graphite', 'connectivity' => 'Bluetooth + Logi Bolt']],
                ['label' => 'Pale Grey', 'suffix' => 'PALEGREY', 'price' => 99.99, 'sale_price' => null, 'stock' => 21, 'attributes' => ['color' => 'Pale Grey', 'connectivity' => 'Bluetooth + Logi Bolt']],
                ['label' => 'Black', 'suffix' => 'BLACK', 'price' => 99.99, 'sale_price' => 84.99, 'stock' => 19, 'attributes' => ['color' => 'Black', 'connectivity' => 'Bluetooth + Logi Bolt']],
            ],
        ],
        [
            'name' => 'Razer BlackWidow V4 Pro Mechanical Keyboard',
            'sku' => 'RAZ-BWV4PRO',
            'category' => 'Keyboards',
            'brand' => 'Razer',
            'price' => 229.99,
            'sale_price' => null,
            'short_description' => 'Full-size mechanical board with command dial and Chroma underglow.',
            'description' => 'BlackWidow V4 Pro adds a rotary command dial, eight macro keys and a magnetic plush wrist rest, on doubleshot ABS keycaps with sound-dampening foam.',
            'is_featured' => false,
            'weight' => 1.42, 'length' => 46.2, 'width' => 15.5, 'height' => 4.3,
            'variants' => [
                ['label' => 'Green Clicky Switch', 'suffix' => 'GREEN', 'price' => 229.99, 'sale_price' => null, 'stock' => 14, 'attributes' => ['switch' => 'Green Clicky', 'layout' => 'Full size', 'backlight' => 'Chroma RGB']],
                ['label' => 'Yellow Linear Switch', 'suffix' => 'YELLOW', 'price' => 229.99, 'sale_price' => null, 'stock' => 9, 'attributes' => ['switch' => 'Yellow Linear', 'layout' => 'Full size', 'backlight' => 'Chroma RGB']],
                ['label' => 'Orange Tactile Switch', 'suffix' => 'ORANGE', 'price' => 239.99, 'sale_price' => null, 'stock' => 11, 'attributes' => ['switch' => 'Orange Tactile', 'layout' => 'Full size', 'backlight' => 'Chroma RGB']],
            ],
        ],
        [
            'name' => 'Dell UltraSharp 4K USB-C Hub Monitor',
            'sku' => 'DEL-ULTRA-4K',
            'category' => 'Monitors',
            'brand' => 'Dell',
            'price' => 579.99,
            'sale_price' => null,
            'short_description' => 'IPS Black 4K panel with 90W USB-C power delivery and RJ45.',
            'description' => 'A colour-calibrated IPS Black panel with a 2000:1 contrast ratio, single-cable USB-C docking at 90W, and a built-in Gigabit ethernet passthrough.',
            'is_featured' => false,
            'weight' => 6.9, 'length' => 61.1, 'width' => 20.1, 'height' => 45.7,
            'variants' => [
                ['label' => '27-inch 4K', 'suffix' => '27-4K', 'price' => 579.99, 'sale_price' => null, 'stock' => 11, 'attributes' => ['size' => '27-inch', 'resolution' => '3840x2160', 'panel' => 'IPS Black', 'refresh_rate' => '60Hz']],
                ['label' => '32-inch 4K', 'suffix' => '32-4K', 'price' => 899.99, 'sale_price' => null, 'stock' => 5, 'attributes' => ['size' => '32-inch', 'resolution' => '3840x2160', 'panel' => 'IPS Black', 'refresh_rate' => '60Hz']],
                ['label' => '34-inch Curved 4K', 'suffix' => '34-4K', 'price' => 999.99, 'sale_price' => null, 'stock' => 4, 'attributes' => ['size' => '34-inch', 'resolution' => '3440x1440', 'panel' => 'IPS Black', 'refresh_rate' => '60Hz']],
            ],
        ],
        [
            'name' => 'ASUS TUF Gaming GeForce RTX 4070 SUPER',
            'sku' => 'AS-TUF-4070S',
            'category' => 'Graphics Cards',
            'brand' => 'ASUS',
            'price' => 649.99,
            'sale_price' => null,
            'short_description' => 'Triple-fan RTX 4070 SUPER with a military-grade TUF build.',
            'description' => 'Axial-tech fans on dual ball bearings, a reinforced metal frame and Auto-Extreme manufacturing. Dual BIOS lets you switch between performance and quiet profiles.',
            'is_featured' => true,
            'weight' => 1.34, 'length' => 30.1, 'width' => 13.7, 'height' => 5.2,
            'variants' => [
                ['label' => '12GB OC Edition', 'suffix' => '12G-OC', 'price' => 679.99, 'sale_price' => null, 'stock' => 7, 'attributes' => ['memory' => '12GB GDDR6X', 'edition' => 'OC', 'length' => '301mm']],
                ['label' => '12GB Standard Edition', 'suffix' => '12G-STD', 'price' => 649.99, 'sale_price' => null, 'stock' => 6, 'attributes' => ['memory' => '12GB GDDR6X', 'edition' => 'Standard', 'length' => '301mm']],
                ['label' => '16GB OC Edition', 'suffix' => '16G-OC', 'price' => 799.99, 'sale_price' => null, 'stock' => 3, 'attributes' => ['memory' => '16GB GDDR6X', 'edition' => 'OC', 'length' => '318mm']],
            ],
        ],
        [
            'name' => 'Corsair RM Series 80+ Gold Power Supply',
            'sku' => 'COR-RM-GOLD',
            'category' => 'Power Supplies',
            'brand' => 'Corsair',
            'price' => 149.99,
            'sale_price' => null,
            'short_description' => 'Fully modular ATX supply with a zero-RPM fan mode.',
            'description' => 'Japanese 105C capacitors, 80 PLUS Gold efficiency and a magnetic levitation fan that stays off under light load. Ten-year warranty.',
            'is_featured' => false,
            'weight' => 2.1, 'length' => 16.0, 'width' => 15.0, 'height' => 8.6,
            'variants' => [
                ['label' => '750W', 'suffix' => '750W', 'price' => 129.99, 'sale_price' => null, 'stock' => 22, 'attributes' => ['wattage' => '750W', 'efficiency' => '80+ Gold', 'modular' => 'Fully modular']],
                ['label' => '850W', 'suffix' => '850W', 'price' => 149.99, 'sale_price' => null, 'stock' => 18, 'attributes' => ['wattage' => '850W', 'efficiency' => '80+ Gold', 'modular' => 'Fully modular']],
                ['label' => '1000W', 'suffix' => '1000W', 'price' => 199.99, 'sale_price' => null, 'stock' => 9, 'attributes' => ['wattage' => '1000W', 'efficiency' => '80+ Gold', 'modular' => 'Fully modular']],
            ],
        ],
        [
            'name' => 'Seagate IronWolf NAS Hard Drive',
            'sku' => 'SEA-IRONWOLF',
            'category' => 'Hard Drives',
            'brand' => 'Seagate',
            'price' => 199.99,
            'sale_price' => null,
            'short_description' => 'CMR NAS drive rated for 24x7 multi-bay operation.',
            'description' => 'IronWolf drives are tuned for always-on NAS enclosures with rotational vibration sensors, AgileArray firmware and a 180TB/year workload rating.',
            'is_featured' => false,
            'weight' => 0.69, 'length' => 14.7, 'width' => 10.2, 'height' => 2.6,
            'variants' => [
                ['label' => '4TB', 'suffix' => '4TB', 'price' => 109.99, 'sale_price' => null, 'stock' => 25, 'attributes' => ['capacity' => '4TB', 'rpm' => '5900', 'interface' => 'SATA 6Gb/s']],
                ['label' => '8TB', 'suffix' => '8TB', 'price' => 199.99, 'sale_price' => null, 'stock' => 14, 'attributes' => ['capacity' => '8TB', 'rpm' => '7200', 'interface' => 'SATA 6Gb/s']],
                ['label' => '12TB', 'suffix' => '12TB', 'price' => 279.99, 'sale_price' => null, 'stock' => 6, 'attributes' => ['capacity' => '12TB', 'rpm' => '7200', 'interface' => 'SATA 6Gb/s']],
            ],
        ],
        [
            'name' => 'ASUS RT-AX86U Pro WiFi 6 Router',
            'sku' => 'AS-RTAX86U-PRO',
            'category' => 'Networking',
            'brand' => 'ASUS',
            'price' => 249.99,
            'sale_price' => null,
            'short_description' => 'AX5700 dual-band router with a 2.5G WAN/LAN port.',
            'description' => 'Mobile Game Mode, adaptive QoS and lifetime AiProtection Pro. Pairs with any AiMesh node to cover a larger floor plan.',
            'is_featured' => false,
            'weight' => 0.68, 'length' => 25.0, 'width' => 17.2, 'height' => 4.2,
            'variants' => [
                ['label' => 'Single unit', 'suffix' => 'SINGLE', 'price' => 249.99, 'sale_price' => null, 'stock' => 16, 'attributes' => ['pack' => '1 unit', 'wifi' => 'WiFi 6 AX5700', 'ports' => '2.5G WAN/LAN']],
                ['label' => '2-pack AiMesh kit', 'suffix' => '2PACK', 'price' => 459.99, 'sale_price' => null, 'stock' => 6, 'attributes' => ['pack' => '2 units', 'wifi' => 'WiFi 6 AX5700', 'ports' => '2.5G WAN/LAN']],
                ['label' => '3-pack AiMesh kit', 'suffix' => '3PACK', 'price' => 649.99, 'sale_price' => 599.99, 'stock' => 3, 'attributes' => ['pack' => '3 units', 'wifi' => 'WiFi 6 AX5700', 'ports' => '2.5G WAN/LAN']],
            ],
        ],
        [
            'name' => 'Intel Core i9-14900K Processor',
            'sku' => 'INT-I9-14900K',
            'category' => 'Processors',
            'brand' => 'Intel',
            'price' => 589.00,
            'sale_price' => null,
            'short_description' => '24-core LGA1700 flagship boosting to 6.0GHz.',
            'description' => 'Eight performance cores and sixteen efficient cores with Intel Thermal Velocity Boost. Unlocked for overclocking on Z790 boards.',
            'is_featured' => false,
            'weight' => 0.09, 'length' => 5.0, 'width' => 5.0, 'height' => 0.5,
            'variants' => [
                ['label' => 'Retail boxed', 'suffix' => 'BOX', 'price' => 589.00, 'sale_price' => null, 'stock' => 12, 'attributes' => ['packaging' => 'Retail box', 'cores' => '24', 'socket' => 'LGA1700']],
                ['label' => 'OEM tray', 'suffix' => 'TRAY', 'price' => 559.00, 'sale_price' => null, 'stock' => 9, 'attributes' => ['packaging' => 'OEM tray', 'cores' => '24', 'socket' => 'LGA1700']],
                ['label' => 'Retail boxed + cooler bundle', 'suffix' => 'BUNDLE', 'price' => 649.00, 'sale_price' => null, 'stock' => 4, 'attributes' => ['packaging' => 'Retail box', 'cores' => '24', 'socket' => 'LGA1700', 'bundle' => 'AIO cooler']],
            ],
        ],
        [
            'name' => 'NVIDIA GeForce RTX 4090 Founders Edition',
            'sku' => 'NV-RTX4090-FE',
            'category' => 'Graphics Cards',
            'brand' => 'NVIDIA',
            'price' => 1599.00,
            'sale_price' => null,
            'short_description' => '24GB flagship card with the reference vapour-chamber cooler.',
            'description' => 'The Founders Edition RTX 4090 in NVIDIA reference trim: 16,384 CUDA cores, 24GB of GDDR6X and a triple-slot vapour chamber.',
            'is_featured' => true,
            'weight' => 2.19, 'length' => 30.4, 'width' => 13.7, 'height' => 6.1,
            'variants' => [
                ['label' => '24GB Founders Edition', 'suffix' => '24G', 'price' => 1599.00, 'sale_price' => null, 'stock' => 4, 'attributes' => ['memory' => '24GB GDDR6X', 'edition' => 'Founders', 'length' => '304mm']],
                ['label' => '24GB + 1000W PSU bundle', 'suffix' => 'BUNDLE', 'price' => 1749.00, 'sale_price' => null, 'stock' => 3, 'attributes' => ['memory' => '24GB GDDR6X', 'edition' => 'Founders', 'bundle' => '1000W PSU']],
                ['label' => '24GB Liquid Cooled Edition', 'suffix' => 'LC', 'price' => 1899.00, 'sale_price' => null, 'stock' => 2, 'attributes' => ['memory' => '24GB GDDR6X', 'edition' => 'Liquid Cooled']],
            ],
        ],
        [
            'name' => 'Corsair Dominator Platinum RGB DDR5',
            'sku' => 'COR-DOM-DDR5',
            'category' => 'Memory (RAM)',
            'brand' => 'Corsair',
            'price' => 299.00,
            'sale_price' => null,
            'short_description' => 'Hand-screened DDR5 with twelve addressable RGB LEDs per stick.',
            'description' => 'Dominator Platinum RGB uses a patented DHX cooling design and hand-sorted ICs for headroom well past the rated XMP profile.',
            'is_featured' => false,
            'weight' => 0.07, 'length' => 13.4, 'width' => 3.9, 'height' => 0.8,
            'variants' => [
                ['label' => '32GB (2x16GB) 6400MHz', 'suffix' => '32G-6400', 'price' => 299.00, 'sale_price' => null, 'stock' => 13, 'attributes' => ['capacity' => '32GB', 'speed' => '6400MHz', 'kit' => '2x16GB']],
                ['label' => '64GB (2x32GB) 6000MHz', 'suffix' => '64G-6000', 'price' => 469.00, 'sale_price' => null, 'stock' => 7, 'attributes' => ['capacity' => '64GB', 'speed' => '6000MHz', 'kit' => '2x32GB']],
                ['label' => '96GB (2x48GB) 6000MHz', 'suffix' => '96G-6000', 'price' => 629.00, 'sale_price' => null, 'stock' => 3, 'attributes' => ['capacity' => '96GB', 'speed' => '6000MHz', 'kit' => '2x48GB']],
            ],
        ],
        [
            'name' => 'ASUS ROG Maximus Z790 Dark Hero',
            'sku' => 'AS-MAX-Z790',
            'category' => 'Motherboards',
            'brand' => 'ASUS',
            'price' => 699.00,
            'sale_price' => null,
            'short_description' => 'Flagship Z790 ATX board with 24+1 power stages.',
            'description' => 'Teamed 110A power stages, a 10Gb ethernet port and passive VRM cooling for a fanless flagship build.',
            'is_featured' => false,
            'weight' => 1.35, 'length' => 30.5, 'width' => 24.4, 'height' => 3.2,
            'variants' => [
                ['label' => 'Wi-Fi 7 Edition', 'suffix' => 'WIFI7', 'price' => 699.00, 'sale_price' => null, 'stock' => 5, 'attributes' => ['chipset' => 'Z790', 'socket' => 'LGA1700', 'form_factor' => 'ATX', 'wifi' => 'Wi-Fi 7']],
                ['label' => 'Standard Edition', 'suffix' => 'STD', 'price' => 649.00, 'sale_price' => null, 'stock' => 6, 'attributes' => ['chipset' => 'Z790', 'socket' => 'LGA1700', 'form_factor' => 'ATX', 'wifi' => 'None']],
                ['label' => 'EVA Edition', 'suffix' => 'EVA', 'price' => 749.00, 'sale_price' => null, 'stock' => 2, 'attributes' => ['chipset' => 'Z790', 'socket' => 'LGA1700', 'form_factor' => 'ATX', 'wifi' => 'Wi-Fi 7', 'theme' => 'EVA collab']],
            ],
        ],
        [
            'name' => 'AMD Ryzen 9 7950X3D Processor',
            'sku' => 'AMD-R9-7950X3D',
            'category' => 'Processors',
            'brand' => 'AMD',
            'price' => 699.00,
            'sale_price' => null,
            'short_description' => '16-core AM5 chip with 128MB of 3D V-Cache.',
            'description' => 'Second-generation 3D V-Cache stacked over one CCD, giving the gaming uplift of the X3D line without losing multi-threaded throughput.',
            'is_featured' => false,
            'weight' => 0.09, 'length' => 5.0, 'width' => 5.0, 'height' => 0.5,
            'variants' => [
                ['label' => 'Retail boxed', 'suffix' => 'BOX', 'price' => 699.00, 'sale_price' => null, 'stock' => 8, 'attributes' => ['packaging' => 'Retail box', 'cores' => '16', 'socket' => 'AM5']],
                ['label' => 'OEM tray', 'suffix' => 'TRAY', 'price' => 669.00, 'sale_price' => null, 'stock' => 5, 'attributes' => ['packaging' => 'OEM tray', 'cores' => '16', 'socket' => 'AM5']],
                ['label' => 'Retail boxed + cooler bundle', 'suffix' => 'BUNDLE', 'price' => 759.00, 'sale_price' => null, 'stock' => 3, 'attributes' => ['packaging' => 'Retail box', 'cores' => '16', 'socket' => 'AM5', 'bundle' => 'AIO cooler']],
            ],
        ],
        [
            'name' => 'Samsung Odyssey OLED G9 Gaming Monitor',
            'sku' => 'SAM-ODY-G9',
            'category' => 'Monitors',
            'brand' => 'Samsung',
            'price' => 1299.00,
            'sale_price' => null,
            'short_description' => 'Ultrawide QD-OLED panel with a 0.03ms response time.',
            'description' => 'Quantum Dot OLED with a 1800R curve, DisplayPort 2.1 and a built-in smart hub that runs without a PC attached.',
            'is_featured' => false,
            'weight' => 11.9, 'length' => 114.8, 'width' => 23.1, 'height' => 38.1,
            'variants' => [
                ['label' => '49-inch DQHD 240Hz', 'suffix' => '49-DQHD', 'price' => 1299.00, 'sale_price' => null, 'stock' => 5, 'attributes' => ['size' => '49-inch', 'resolution' => '5120x1440', 'refresh_rate' => '240Hz', 'panel' => 'QD-OLED']],
                ['label' => '34-inch UWQHD 175Hz', 'suffix' => '34-UWQHD', 'price' => 899.00, 'sale_price' => null, 'stock' => 6, 'attributes' => ['size' => '34-inch', 'resolution' => '3440x1440', 'refresh_rate' => '175Hz', 'panel' => 'QD-OLED']],
                ['label' => '27-inch QHD 240Hz', 'suffix' => '27-QHD', 'price' => 649.00, 'sale_price' => 579.00, 'stock' => 9, 'attributes' => ['size' => '27-inch', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel' => 'QD-OLED']],
            ],
        ],
        [
            'name' => 'WD Black SN850X NVMe SSD',
            'sku' => 'WD-SN850X',
            'category' => 'Internal SSDs',
            'brand' => 'Western Digital',
            'price' => 179.99,
            'sale_price' => null,
            'short_description' => 'Gen4 gaming drive with a predictive Game Mode 2.0 cache.',
            'description' => 'The SN850X pairs WD in-house controller silicon with 112-layer TLC for 7,300 MB/s reads and low random-read latency.',
            'is_featured' => false,
            'weight' => 0.01, 'length' => 8.0, 'width' => 2.2, 'height' => 0.23,
            'variants' => [
                ['label' => '1TB', 'suffix' => '1TB', 'price' => 109.99, 'sale_price' => null, 'stock' => 27, 'attributes' => ['capacity' => '1TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '2TB', 'suffix' => '2TB', 'price' => 179.99, 'sale_price' => null, 'stock' => 19, 'attributes' => ['capacity' => '2TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '4TB', 'suffix' => '4TB', 'price' => 349.99, 'sale_price' => null, 'stock' => 7, 'attributes' => ['capacity' => '4TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
            ],
        ],
        [
            'name' => 'NZXT Kraken Elite 360 AIO Cooler',
            'sku' => 'NZXT-KRAKEN-360',
            'category' => 'CPU Coolers',
            'brand' => 'NZXT',
            'price' => 279.99,
            'sale_price' => null,
            'short_description' => '360mm liquid cooler with a 2.36" LCD pump head.',
            'description' => 'A wide-format LCD on the pump head, three F120 RGB Core fans and a seventh-generation Asetek pump. Mounts on LGA1700 and AM5 out of the box.',
            'is_featured' => false,
            'weight' => 1.28, 'length' => 39.4, 'width' => 12.0, 'height' => 5.4,
            'variants' => [
                ['label' => '360mm Black', 'suffix' => '360-BLK', 'price' => 279.99, 'sale_price' => null, 'stock' => 10, 'attributes' => ['radiator' => '360mm', 'color' => 'Black', 'display' => '2.36in LCD']],
                ['label' => '360mm White', 'suffix' => '360-WHT', 'price' => 289.99, 'sale_price' => null, 'stock' => 6, 'attributes' => ['radiator' => '360mm', 'color' => 'White', 'display' => '2.36in LCD']],
                ['label' => '280mm Black', 'suffix' => '280-BLK', 'price' => 239.99, 'sale_price' => null, 'stock' => 8, 'attributes' => ['radiator' => '280mm', 'color' => 'Black', 'display' => '2.36in LCD']],
            ],
        ],
        [
            'name' => 'Lian Li O11 Dynamic EVO PC Case',
            'sku' => 'LIAN-O11-EVO',
            'category' => 'PC Cases',
            'brand' => 'Lian Li',
            'price' => 169.99,
            'sale_price' => null,
            'short_description' => 'Dual-chamber showcase case with a reversible layout.',
            'description' => 'The O11 Dynamic EVO can be rebuilt as a normal or inverted layout, takes three 360mm radiators, and hides the PSU and cabling in a second chamber.',
            'is_featured' => false,
            'weight' => 9.6, 'length' => 46.5, 'width' => 28.5, 'height' => 45.7,
            'variants' => [
                ['label' => 'Mid Tower Black', 'suffix' => 'MID-BLK', 'price' => 169.99, 'sale_price' => null, 'stock' => 11, 'attributes' => ['form_factor' => 'Mid Tower', 'color' => 'Black', 'side_panel' => 'Tempered glass']],
                ['label' => 'Mid Tower White', 'suffix' => 'MID-WHT', 'price' => 179.99, 'sale_price' => null, 'stock' => 9, 'attributes' => ['form_factor' => 'Mid Tower', 'color' => 'White', 'side_panel' => 'Tempered glass']],
                ['label' => 'Mid Tower Silver', 'suffix' => 'MID-SLV', 'price' => 179.99, 'sale_price' => null, 'stock' => 4, 'attributes' => ['form_factor' => 'Mid Tower', 'color' => 'Silver', 'side_panel' => 'Tempered glass']],
            ],
        ],
        [
            'name' => 'Elgato Stream Deck MK.2',
            'sku' => 'ELG-SD-MK2',
            'category' => 'Accessories',
            'brand' => 'Elgato',
            'price' => 149.99,
            'sale_price' => null,
            'short_description' => '15 LCD keys for one-touch scene and macro control.',
            'description' => 'Fifteen customisable LCD keys with a detachable USB-C cable and interchangeable faceplates. Integrates with OBS, Streamlabs and Teams.',
            'is_featured' => false,
            'weight' => 0.32, 'length' => 11.9, 'width' => 8.3, 'height' => 2.5,
            'variants' => [
                ['label' => '15 Key Black', 'suffix' => '15-BLK', 'price' => 149.99, 'sale_price' => null, 'stock' => 15, 'attributes' => ['keys' => '15', 'color' => 'Black', 'connection' => 'USB-C']],
                ['label' => '15 Key White', 'suffix' => '15-WHT', 'price' => 149.99, 'sale_price' => null, 'stock' => 10, 'attributes' => ['keys' => '15', 'color' => 'White', 'connection' => 'USB-C']],
                ['label' => '6 Key Mini Black', 'suffix' => '6-BLK', 'price' => 99.99, 'sale_price' => 84.99, 'stock' => 13, 'attributes' => ['keys' => '6', 'color' => 'Black', 'connection' => 'USB-C']],
            ],
        ],
        [
            'name' => 'Sony WH-1000XM5 Headphones',
            'sku' => 'SNY-WH1000XM5',
            'category' => 'Headphones & Headsets',
            'brand' => 'Sony',
            'price' => 399.99,
            'sale_price' => null,
            'short_description' => 'Flagship noise-canceling headphones with 30-hour battery life.',
            'description' => 'Eight microphones and two processors drive best-in-class active noise cancellation, with multipoint Bluetooth and Sony\'s DSEE Extreme upscaling.',
            'is_featured' => true,
            'weight' => 0.25, 'length' => 20.5, 'width' => 18.2, 'height' => 8.2,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 399.99, 'sale_price' => null, 'stock' => 24, 'attributes' => ['color' => 'Black']],
                ['label' => 'Silver', 'suffix' => 'SLV', 'price' => 399.99, 'sale_price' => null, 'stock' => 17, 'attributes' => ['color' => 'Silver']],
                ['label' => 'Midnight Blue', 'suffix' => 'BLU', 'price' => 419.99, 'sale_price' => 379.99, 'stock' => 9, 'attributes' => ['color' => 'Midnight Blue']],
            ],
        ],
        [
            'name' => 'Bose QuietComfort Ultra Headphones',
            'sku' => 'BOS-QCULTRA',
            'category' => 'Headphones & Headsets',
            'brand' => 'Bose',
            'price' => 429.00,
            'sale_price' => null,
            'short_description' => 'Immersive Audio headphones with CustomTune noise cancellation.',
            'description' => 'Bose\'s Immersive Audio mode adds a spatial, head-tracked soundstage on top of the brand\'s best active noise cancellation to date.',
            'is_featured' => false,
            'weight' => 0.253, 'length' => 19.8, 'width' => 17.5, 'height' => 7.9,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 429.00, 'sale_price' => null, 'stock' => 15, 'attributes' => ['color' => 'Black']],
                ['label' => 'White Smoke', 'suffix' => 'WHT', 'price' => 429.00, 'sale_price' => null, 'stock' => 11, 'attributes' => ['color' => 'White Smoke']],
                ['label' => 'Sandstone', 'suffix' => 'SND', 'price' => 429.00, 'sale_price' => 389.00, 'stock' => 6, 'attributes' => ['color' => 'Sandstone']],
            ],
        ],
        [
            'name' => 'Apple AirPods Pro 2',
            'sku' => 'APL-APP2',
            'category' => 'Headphones & Headsets',
            'brand' => 'Apple',
            'price' => 249.00,
            'sale_price' => null,
            'short_description' => 'USB-C AirPods Pro with Adaptive Audio and Personalized Spatial Audio.',
            'description' => 'The H2 chip drives Adaptive Audio that blends transparency and noise cancellation in real time, now charging over a USB-C case.',
            'is_featured' => false,
            'weight' => 0.0505, 'length' => 6.09, 'width' => 4.52, 'height' => 2.17,
            'variants' => [
                ['label' => 'USB-C MagSafe Case', 'suffix' => 'USBC', 'price' => 249.00, 'sale_price' => null, 'stock' => 30, 'attributes' => ['charging_case' => 'USB-C MagSafe']],
                ['label' => 'USB-C MagSafe Case + Engraving', 'suffix' => 'USBC-ENG', 'price' => 259.00, 'sale_price' => null, 'stock' => 14, 'attributes' => ['charging_case' => 'USB-C MagSafe', 'engraving' => 'Included']],
                ['label' => 'Lightning MagSafe Case', 'suffix' => 'LTG', 'price' => 229.00, 'sale_price' => 199.00, 'stock' => 8, 'attributes' => ['charging_case' => 'Lightning MagSafe']],
            ],
        ],
        [
            'name' => 'JBL Charge 5 Portable Speaker',
            'sku' => 'JBL-CHARGE5',
            'category' => 'Speakers',
            'brand' => 'JBL',
            'price' => 179.95,
            'sale_price' => null,
            'short_description' => 'IP67 rugged Bluetooth speaker that doubles as a power bank.',
            'description' => 'A 20-hour battery, an IP67 rating against dust and water, and a built-in USB power bank for topping up a phone on the go.',
            'is_featured' => false,
            'weight' => 0.96, 'length' => 22.2, 'width' => 9.6, 'height' => 9.35,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 179.95, 'sale_price' => null, 'stock' => 20, 'attributes' => ['color' => 'Black']],
                ['label' => 'Blue', 'suffix' => 'BLU', 'price' => 179.95, 'sale_price' => null, 'stock' => 14, 'attributes' => ['color' => 'Blue']],
                ['label' => 'Camo', 'suffix' => 'CAMO', 'price' => 189.95, 'sale_price' => 159.95, 'stock' => 7, 'attributes' => ['color' => 'Camo']],
            ],
        ],
        [
            'name' => 'Sonos Era 300 Smart Speaker',
            'sku' => 'SON-ERA300',
            'category' => 'Speakers',
            'brand' => 'Sonos',
            'price' => 449.00,
            'sale_price' => null,
            'short_description' => 'Six-driver spatial speaker with Dolby Atmos support.',
            'description' => 'Six Class-D amps fire audio upward and sideways for a genuinely spatial soundstage, with Dolby Atmos, AirPlay 2 and Trueplay tuning.',
            'is_featured' => false,
            'weight' => 4.47, 'length' => 26.0, 'width' => 18.5, 'height' => 18.0,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 449.00, 'sale_price' => null, 'stock' => 10, 'attributes' => ['color' => 'Black']],
                ['label' => 'White', 'suffix' => 'WHT', 'price' => 449.00, 'sale_price' => null, 'stock' => 8, 'attributes' => ['color' => 'White']],
                ['label' => 'White Stereo Pair', 'suffix' => 'WHT-PAIR', 'price' => 898.00, 'sale_price' => 849.00, 'stock' => 3, 'attributes' => ['color' => 'White', 'pack' => 'Stereo Pair']],
            ],
        ],
        [
            'name' => 'Logitech Brio 4K Webcam',
            'sku' => 'LOG-BRIO4K',
            'category' => 'Webcams',
            'brand' => 'Logitech',
            'price' => 199.99,
            'sale_price' => null,
            'short_description' => '4K HDR webcam with Windows Hello facial recognition.',
            'description' => 'A 4K sensor with RightLight 3 HDR, a 5x digital zoom, and infrared sensors for Windows Hello sign-in.',
            'is_featured' => false,
            'weight' => 0.062, 'length' => 10.2, 'width' => 2.7, 'height' => 2.8,
            'variants' => [
                ['label' => 'Standard', 'suffix' => 'STD', 'price' => 199.99, 'sale_price' => null, 'stock' => 22, 'attributes' => ['resolution' => '4K', 'mount' => 'Clip mount']],
                ['label' => 'With Tripod', 'suffix' => 'TRIPOD', 'price' => 229.99, 'sale_price' => null, 'stock' => 9, 'attributes' => ['resolution' => '4K', 'mount' => 'Tripod included']],
                ['label' => 'With Privacy Shutter', 'suffix' => 'SHUTTER', 'price' => 219.99, 'sale_price' => 199.99, 'stock' => 11, 'attributes' => ['resolution' => '4K', 'mount' => 'Clip mount', 'privacy' => 'Shutter included']],
            ],
        ],
        [
            'name' => 'HP OfficeJet Pro 9015e Printer',
            'sku' => 'HP-OJPRO9015E',
            'category' => 'Printers & Scanners',
            'brand' => 'HP',
            'price' => 229.99,
            'sale_price' => null,
            'short_description' => 'All-in-one inkjet printer with automatic duplex and 6 months of Instant Ink.',
            'description' => 'Print, scan, copy and fax over a 35-page automatic document feeder, with a 250-sheet paper tray and automatic two-sided printing.',
            'is_featured' => false,
            'weight' => 8.24, 'length' => 45.4, 'width' => 38.0, 'height' => 20.6,
            'variants' => [
                ['label' => 'Standard', 'suffix' => 'STD', 'price' => 229.99, 'sale_price' => null, 'stock' => 13, 'attributes' => ['connectivity' => 'WiFi + USB', 'duplex' => 'Automatic']],
                ['label' => 'With Extra Ink Bundle', 'suffix' => 'INK-BUNDLE', 'price' => 279.99, 'sale_price' => 249.99, 'stock' => 6, 'attributes' => ['connectivity' => 'WiFi + USB', 'duplex' => 'Automatic', 'bundle' => 'Extra ink cartridges']],
                ['label' => 'With Extended Warranty', 'suffix' => 'WARRANTY', 'price' => 259.99, 'sale_price' => null, 'stock' => 4, 'attributes' => ['connectivity' => 'WiFi + USB', 'duplex' => 'Automatic', 'warranty' => '3-year extended']],
            ],
        ],
        [
            'name' => 'Anker 737 Power Bank',
            'sku' => 'ANK-737PWR',
            'category' => 'Power Banks & Chargers',
            'brand' => 'Anker',
            'price' => 149.99,
            'sale_price' => null,
            'short_description' => '24,000mAh power bank with a 140W USB-C output.',
            'description' => 'A digital display shows real-time wattage, and the 140W USB-C port can fast-charge a laptop as easily as a phone.',
            'is_featured' => false,
            'weight' => 0.52, 'length' => 15.6, 'width' => 7.0, 'height' => 3.4,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 149.99, 'sale_price' => null, 'stock' => 26, 'attributes' => ['capacity' => '24000mAh', 'color' => 'Black']],
                ['label' => 'White', 'suffix' => 'WHT', 'price' => 149.99, 'sale_price' => null, 'stock' => 15, 'attributes' => ['capacity' => '24000mAh', 'color' => 'White']],
                ['label' => 'Black + Car Charger Bundle', 'suffix' => 'BLK-CAR', 'price' => 179.99, 'sale_price' => 159.99, 'stock' => 5, 'attributes' => ['capacity' => '24000mAh', 'color' => 'Black', 'bundle' => 'Car charger']],
            ],
        ],
        [
            'name' => 'Anker PowerExpand 8-in-1 USB-C Hub',
            'sku' => 'ANK-PWREXP8',
            'category' => 'Cables & Adapters',
            'brand' => 'Anker',
            'price' => 69.99,
            'sale_price' => null,
            'short_description' => '8-in-1 dock with 4K HDMI, Gigabit ethernet and 100W passthrough.',
            'description' => 'Two USB-A, one USB-C data port, a 4K/60Hz HDMI output, Gigabit ethernet, an SD/microSD reader and 100W USB-C power delivery passthrough.',
            'is_featured' => false,
            'weight' => 0.145, 'length' => 12.4, 'width' => 4.1, 'height' => 1.5,
            'variants' => [
                ['label' => 'Space Gray', 'suffix' => 'GRY', 'price' => 69.99, 'sale_price' => null, 'stock' => 30, 'attributes' => ['color' => 'Space Gray', 'ports' => '8-in-1']],
                ['label' => 'Silver', 'suffix' => 'SLV', 'price' => 69.99, 'sale_price' => null, 'stock' => 19, 'attributes' => ['color' => 'Silver', 'ports' => '8-in-1']],
                ['label' => 'Space Gray + Cable Bundle', 'suffix' => 'GRY-CABLE', 'price' => 84.99, 'sale_price' => 74.99, 'stock' => 8, 'attributes' => ['color' => 'Space Gray', 'ports' => '8-in-1', 'bundle' => 'USB-C cable']],
            ],
        ],
        [
            'name' => 'SanDisk Extreme Portable SSD',
            'sku' => 'SAN-EXTPORT',
            'category' => 'External Storage',
            'brand' => 'SanDisk',
            'price' => 129.99,
            'sale_price' => null,
            'short_description' => 'IP65-rated portable SSD reaching 1050 MB/s over USB-C.',
            'description' => 'A rubberised, drop-resistant shell rated IP65 against dust and water, with 256-bit AES hardware encryption support.',
            'is_featured' => false,
            'weight' => 0.058, 'length' => 9.9, 'width' => 5.3, 'height' => 1.0,
            'variants' => [
                ['label' => '1TB', 'suffix' => '1TB', 'price' => 129.99, 'sale_price' => null, 'stock' => 24, 'attributes' => ['capacity' => '1TB']],
                ['label' => '2TB', 'suffix' => '2TB', 'price' => 199.99, 'sale_price' => null, 'stock' => 16, 'attributes' => ['capacity' => '2TB']],
                ['label' => '4TB', 'suffix' => '4TB', 'price' => 359.99, 'sale_price' => 329.99, 'stock' => 6, 'attributes' => ['capacity' => '4TB']],
            ],
        ],
    ];

    private int $barcodeSequence = 2000000000000;

    public function run(): void
    {
        $categories = $this->categories();
        $brands = $this->brands();

        foreach (self::PRODUCTS as $index => $definition) {
            $product = $this->seedProduct($definition, $categories, $brands, $index);
            $this->seedImages($product, $definition);
            $this->seedVariants($product, $definition);
        }

        $variantCount = array_sum(array_map(fn (array $d) => count($d['variants']), self::PRODUCTS));
        $this->command?->info(sprintf(
            'Seeded %d products with %d variants and %d images.',
            count(self::PRODUCTS),
            $variantCount,
            count(self::PRODUCTS) * 3
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Taxonomy
    |--------------------------------------------------------------------------
    */

    /**
     * Resolves each product's `category` name against the taxonomy
     * CategorySeeder already created, rather than maintaining a second,
     * parallel category tree. CategorySeeder is idempotent (firstOrCreate on
     * slug), so calling it here keeps this seeder runnable standalone.
     *
     * @return array<string, Category>
     */
    private function categories(): array
    {
        $this->call(CategorySeeder::class);

        $categories = [];
        foreach (array_unique(array_column(self::PRODUCTS, 'category')) as $name) {
            $categories[$name] = Category::where('slug', Str::slug($name))->firstOrFail();
        }

        return $categories;
    }

    /** @return array<string, Brand> */
    private function brands(): array
    {
        $brands = [];
        foreach (array_unique(array_column(self::PRODUCTS, 'brand')) as $name) {
            $brands[$name] = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }

        return $brands;
    }

    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Brand>  $brands
     */
    private function seedProduct(array $definition, array $categories, array $brands, int $index): Product
    {
        $stock = array_sum(array_column($definition['variants'], 'stock'));

        return Product::updateOrCreate(
            ['sku' => $definition['sku']],
            [
                'category_id' => $categories[$definition['category']]->id,
                'brand_id' => $brands[$definition['brand']]->id,
                'name' => $definition['name'],
                'slug' => Str::slug($definition['name']),
                'barcode' => $this->nextBarcode(),
                'short_description' => $definition['short_description'],
                'description' => $definition['description'],
                'price' => $definition['price'],
                'sale_price' => $definition['sale_price'],
                'cost_price' => round($definition['price'] * 0.7, 2),
                'stock_quantity' => $stock,
                'reserved_stock' => 0,
                'damaged_stock' => 0,
                'incoming_stock' => 0,
                'min_stock_alert' => 5,
                'max_stock_level' => max(200, $stock * 3),
                'reorder_point' => 10,
                'track_inventory' => true,
                'in_stock' => $stock > 0,
                'weight' => $definition['weight'],
                'length' => $definition['length'],
                'width' => $definition['width'],
                'height' => $definition['height'],
                'thumbnail' => $this->imageUrl($definition['sku']),
                'meta_title' => $definition['name'],
                'meta_description' => $definition['short_description'],
                'is_active' => true,
                'is_featured' => $definition['is_featured'],
                'is_digital' => false,
                'views_count' => 0,
                'sales_count' => 0,
                'sort_order' => $index,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    /**
     * One real Unsplash photo id per SKU, hand-picked and verified (fetched
     * and visually checked) to depict that kind of product, instead of a
     * random unrelated picsum.photos placeholder. Same format CategorySeeder
     * uses: the id is whatever follows "photo-" in an
     * images.unsplash.com/photo-<id> URL.
     */
    private const PRODUCT_IMAGES = [
        'AS-ZEPH-G16' => '1771014817844-327a14245bd1',
        'SAM-990PRO' => '1669480380758-4b163a33f6f9',
        'KIN-FURY-DDR5' => '1672923491001-3e58a608e418',
        'LOG-MXM3S' => '1527864550417-7fd91fc51a46',
        'RAZ-BWV4PRO' => '1756694938594-e760b4bd3bfb',
        'DEL-ULTRA-4K' => '1628358011846-80e98db9e925',
        'AS-TUF-4070S' => '1591488320449-011701bb6704',
        'COR-RM-GOLD' => '1716062890647-60feae0609d0',
        'SEA-IRONWOLF' => '1593448848024-77a27f0690b1',
        'AS-RTAX86U-PRO' => '1745847768408-b7b83796cae6',
        'INT-I9-14900K' => '1686195165991-74af7c2918d5',
        'NV-RTX4090-FE' => '1752179634046-5159d1b13f6f',
        'COR-DOM-DDR5' => '1541029071515-84cc54f84dc5',
        'AS-MAX-Z790' => '1518770660439-4636190af475',
        'AMD-R9-7950X3D' => '1591799264318-7e6ef8ddb7ea',
        'SAM-ODY-G9' => '1762096070873-63e82ab143ca',
        'WD-SN850X' => '1604590003050-14c5b8521015',
        'NZXT-KRAKEN-360' => '1765824142326-8e9046b1f935',
        'LIAN-O11-EVO' => '1587202372775-e229f172b9d7',
        'ELG-SD-MK2' => '1642784353476-d226d8d617b3',
        'SNY-WH1000XM5' => '1505740420928-5e560c06d30e',
        'BOS-QCULTRA' => '1559743345-24713f59f5e3',
        'APL-APP2' => '1624258919367-5dc28f5dc293',
        'JBL-CHARGE5' => '1589273705736-1bd0a0bcf116',
        'SON-ERA300' => '1549199224-29cf2f784cd0',
        'LOG-BRIO4K' => '1750975314977-374f2290db53',
        'HP-OJPRO9015E' => '1612815154858-60aa4c59eaa6',
        'ANK-737PWR' => '1564286027179-588f75ac4ef2',
        'ANK-PWREXP8' => '1616578273518-450dd375759b',
        'SAN-EXTPORT' => '1756142752564-586c77618757',
    ];

    /**
     * A stable, absolute image URL for a product's curated Unsplash photo.
     *
     * The admin list only renders absolute URLs (product thumbnails are
     * normally Cloudinary secure_url values), so seed data has to be absolute
     * too or it falls back to the initials tile.
     */
    private function imageUrl(string $sku, int $size = 800): string
    {
        $photoId = self::PRODUCT_IMAGES[$sku];

        return "https://images.unsplash.com/photo-{$photoId}?auto=format&fit=crop&w={$size}&h={$size}&q=80";
    }

    /**
     * One primary thumbnail row plus two gallery rows per product. All three
     * point at the same curated photo (there's only one real photo per SKU,
     * not multiple angles of the literal product) at slightly different crop
     * sizes.
     */
    private function seedImages(Product $product, array $definition): void
    {
        $images = [
            ['size' => 800, 'type' => 'thumbnail', 'primary' => true],
            ['size' => 1000, 'type' => 'gallery', 'primary' => false],
            ['size' => 1200, 'type' => 'gallery', 'primary' => false],
        ];

        foreach ($images as $sortOrder => $image) {
            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'type' => $image['type'], 'sort_order' => $sortOrder],
                [
                    'image' => $this->imageUrl($definition['sku'], $image['size']),
                    'disk' => 'public',
                    'mime_type' => 'image/jpeg',
                    'file_size' => 245_760,
                    'alt_text' => $definition['name'],
                    'title' => $definition['name'],
                    'is_primary' => $image['primary'],
                    'is_active' => true,
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Variants
    |--------------------------------------------------------------------------
    */

    /**
     * Variant slug and sku are seeded with the parent SKU on purpose: both
     * columns carry GLOBAL unique indexes, so two products that each have a
     * variant called e.g. "1TB" would otherwise collide. Variants share the
     * parent product's curated photo — they differ by color/capacity/etc,
     * not by a distinct real-world product photo.
     */
    private function seedVariants(Product $product, array $definition): void
    {
        foreach ($definition['variants'] as $sortOrder => $variant) {
            $sku = $definition['sku'].'-'.$variant['suffix'];

            ProductVariant::updateOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $product->id,
                    'name' => $variant['label'],
                    'slug' => Str::slug($sku.' '.$variant['label']),
                    'barcode' => $this->nextBarcode(),
                    'price' => $variant['price'],
                    'sale_price' => $variant['sale_price'],
                    'cost_price' => round($variant['price'] * 0.7, 2),
                    'stock_quantity' => $variant['stock'],
                    'reserved_stock' => 0,
                    'damaged_stock' => 0,
                    'incoming_stock' => 0,
                    'min_stock_alert' => 5,
                    'max_stock_level' => max(50, $variant['stock'] * 4),
                    'track_inventory' => true,
                    'in_stock' => $variant['stock'] > 0,
                    'weight' => $definition['weight'],
                    'length' => $definition['length'],
                    'width' => $definition['width'],
                    'height' => $definition['height'],
                    'image' => $this->imageUrl($definition['sku']),
                    'attributes' => $variant['attributes'],
                    'is_default' => $sortOrder === 0,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }

    private function nextBarcode(): string
    {
        return (string) $this->barcodeSequence++;
    }
}
