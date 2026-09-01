<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A 20-product demo catalog with a complete media + variant set, for developing
 * and demoing the admin Products screen.
 *
 * The stock spread is deliberate and asserted at the end of run(): 8 in stock,
 * 5 low stock, 7 out of stock. A product's stock is the sum of its variants, so
 * "out of stock" means every variant sits at 0 — the list's status filters have
 * a meaningful population in each bucket.
 *
 * Unlike ProductSeeder — which is additive, keyed by SKU and safe to re-run
 * from DatabaseSeeder — this seeder RESETS the catalog first: every product,
 * variant and product_image row is hard-deleted before the 10 demo products are
 * written. That is why it is deliberately NOT wired into DatabaseSeeder.
 *
 * Deleting products does not delete orders. order_items.product_id is
 * ON DELETE SET NULL and every line item keeps its own product_name /
 * product_sku snapshot, so the Orders screen still renders — the rows just lose
 * their link back to a catalog row.
 *
 * Usage: php artisan db:seed --class=DemoProductSeeder
 */
class DemoProductSeeder extends Seeder
{
    /*
    |--------------------------------------------------------------------------
    | Catalog definition
    |--------------------------------------------------------------------------
    |
    | Twenty products, each with a thumbnail, a small image gallery and two or
    | three variants. Variant `attributes` carry the axis VALUES the admin
    | variant editor reads back (color / capacity / switch / ...).
    |
    | Stock covers every state the list renders. min_stock_alert is 5 across the
    | board, so a product totalling 1-5 is low stock and 0 is out of stock;
    | AS-TUF-4070S sits exactly on 5 to pin the `<=` boundary.
    */
    private const PRODUCTS = [
        [
            'name' => 'ASUS ROG Zephyrus G16 Gaming Laptop',
            'sku' => 'AS-ZEPH-G16',
            'category' => 'Gaming Laptop',
            'brand' => 'ASUS',
            'price' => 2199.00,
            'short_description' => 'Slim 16-inch OLED gaming laptop with Intel Core Ultra 9 and RTX 4080.',
            'description' => 'The ROG Zephyrus G16 pairs a 240Hz OLED display with an Intel Core Ultra 9 and NVIDIA RTX graphics in a 1.5kg CNC-milled aluminium chassis. Backed by the full ASUS manufacturer warranty.',
            'is_featured' => true,
            'variants' => [
                ['label' => '16GB / 1TB / Eclipse Gray', 'suffix' => 'EG-16-1T', 'price' => 2199.00, 'stock' => 2, 'attributes' => ['ram' => '16GB', 'storage' => '1TB', 'color' => 'Eclipse Gray']],
                ['label' => '32GB / 1TB / Eclipse Gray', 'suffix' => 'EG-32-1T', 'price' => 2499.00, 'stock' => 1, 'attributes' => ['ram' => '32GB', 'storage' => '1TB', 'color' => 'Eclipse Gray']],
                ['label' => '32GB / 2TB / Platinum White', 'suffix' => 'PW-32-2T', 'price' => 2699.00, 'stock' => 1, 'attributes' => ['ram' => '32GB', 'storage' => '2TB', 'color' => 'Platinum White']],
            ],
        ],
        [
            'name' => 'Samsung 990 PRO NVMe SSD',
            'sku' => 'SAM-990PRO',
            'category' => 'NVMe SSD',
            'brand' => 'Samsung',
            'price' => 169.99,
            'short_description' => 'PCIe 4.0 M.2 drive reaching 7,450 MB/s sequential reads.',
            'description' => 'Samsung 990 PRO with the in-house Pascal controller and V-NAND. Nickel-coated controller and heat-spreader label for sustained thermal control in laptops and consoles.',
            'is_featured' => true,
            'variants' => [
                ['label' => '1TB', 'suffix' => '1TB', 'price' => 99.99, 'stock' => 48, 'attributes' => ['capacity' => '1TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '2TB', 'suffix' => '2TB', 'price' => 169.99, 'stock' => 31, 'attributes' => ['capacity' => '2TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '4TB', 'suffix' => '4TB', 'price' => 329.99, 'stock' => 12, 'attributes' => ['capacity' => '4TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
            ],
        ],
        [
            'name' => 'Kingston FURY Beast DDR5 Memory Kit',
            'sku' => 'KIN-FURY-DDR5',
            'category' => 'Memory',
            'brand' => 'Kingston',
            'price' => 129.99,
            'short_description' => 'Plug-and-play DDR5 kits with Intel XMP 3.0 and AMD EXPO profiles.',
            'description' => 'Kingston FURY Beast DDR5 ships with on-die ECC and a low-profile heat spreader that clears oversized CPU coolers. Automatic overclocking to the rated speed on XMP or EXPO boards.',
            'is_featured' => false,
            'variants' => [
                ['label' => '16GB (2x8GB) 5600MHz', 'suffix' => '16G-5600', 'price' => 74.99, 'stock' => 40, 'attributes' => ['capacity' => '16GB', 'speed' => '5600MHz', 'kit' => '2x8GB']],
                ['label' => '32GB (2x16GB) 6000MHz', 'suffix' => '32G-6000', 'price' => 129.99, 'stock' => 26, 'attributes' => ['capacity' => '32GB', 'speed' => '6000MHz', 'kit' => '2x16GB']],
                ['label' => '64GB (2x32GB) 6000MHz', 'suffix' => '64G-6000', 'price' => 239.99, 'stock' => 8, 'attributes' => ['capacity' => '64GB', 'speed' => '6000MHz', 'kit' => '2x32GB']],
            ],
        ],
        [
            'name' => 'Logitech MX Master 3S Wireless Mouse',
            'sku' => 'LOG-MXM3S',
            'category' => 'Mouse',
            'brand' => 'Logitech',
            'price' => 99.99,
            'short_description' => '8K DPI sensor with near-silent clicks and MagSpeed scrolling.',
            'description' => 'The MX Master 3S tracks on glass, scrolls 1,000 lines a second with the MagSpeed wheel, and pairs to three machines over Bluetooth or the Logi Bolt receiver.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Graphite', 'suffix' => 'GRAPHITE', 'price' => 99.99, 'stock' => 34, 'attributes' => ['color' => 'Graphite', 'connectivity' => 'Bluetooth + Logi Bolt']],
                ['label' => 'Pale Grey', 'suffix' => 'PALEGREY', 'price' => 99.99, 'stock' => 21, 'attributes' => ['color' => 'Pale Grey', 'connectivity' => 'Bluetooth + Logi Bolt']],
                ['label' => 'Black', 'suffix' => 'BLACK', 'price' => 99.99, 'stock' => 0, 'attributes' => ['color' => 'Black', 'connectivity' => 'Bluetooth + Logi Bolt']],
            ],
        ],
        [
            'name' => 'Razer BlackWidow V4 Pro Mechanical Keyboard',
            'sku' => 'RAZ-BWV4PRO',
            'category' => 'Keyboard',
            'brand' => 'Razer',
            'price' => 229.99,
            'short_description' => 'Full-size mechanical board with command dial and Chroma underglow.',
            'description' => 'BlackWidow V4 Pro adds a rotary command dial, eight macro keys and a magnetic plush wrist rest, on doubleshot ABS keycaps with sound-dampening foam.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Green Clicky Switch', 'suffix' => 'GREEN', 'price' => 229.99, 'stock' => 14, 'attributes' => ['switch' => 'Green Clicky', 'layout' => 'Full size', 'backlight' => 'Chroma RGB']],
                ['label' => 'Yellow Linear Switch', 'suffix' => 'YELLOW', 'price' => 229.99, 'stock' => 9, 'attributes' => ['switch' => 'Yellow Linear', 'layout' => 'Full size', 'backlight' => 'Chroma RGB']],
                ['label' => 'Orange Tactile Switch', 'suffix' => 'ORANGE', 'price' => 239.99, 'stock' => 3, 'attributes' => ['switch' => 'Orange Tactile', 'layout' => 'Full size', 'backlight' => 'Chroma RGB']],
            ],
        ],
        [
            'name' => 'Dell UltraSharp 4K USB-C Hub Monitor',
            'sku' => 'DEL-ULTRA-4K',
            'category' => 'Office Monitor',
            'brand' => 'Dell',
            'price' => 579.99,
            'short_description' => 'IPS Black 4K panel with 90W USB-C power delivery and RJ45.',
            'description' => 'A colour-calibrated IPS Black panel with a 2000:1 contrast ratio, single-cable USB-C docking at 90W, and a built-in Gigabit ethernet passthrough.',
            'is_featured' => false,
            'variants' => [
                ['label' => '27-inch 4K', 'suffix' => '27-4K', 'price' => 579.99, 'stock' => 11, 'attributes' => ['size' => '27-inch', 'resolution' => '3840x2160', 'panel' => 'IPS Black', 'refresh_rate' => '60Hz']],
                ['label' => '32-inch 4K', 'suffix' => '32-4K', 'price' => 899.99, 'stock' => 5, 'attributes' => ['size' => '32-inch', 'resolution' => '3840x2160', 'panel' => 'IPS Black', 'refresh_rate' => '60Hz']],
            ],
        ],
        [
            'name' => 'ASUS TUF Gaming GeForce RTX 4070 SUPER',
            'sku' => 'AS-TUF-4070S',
            'category' => 'Graphics Cards',
            'brand' => 'ASUS',
            'price' => 649.99,
            'short_description' => 'Triple-fan RTX 4070 SUPER with a military-grade TUF build.',
            'description' => 'Axial-tech fans on dual ball bearings, a reinforced metal frame and Auto-Extreme manufacturing. Dual BIOS lets you switch between performance and quiet profiles.',
            'is_featured' => true,
            'variants' => [
                ['label' => '12GB OC Edition', 'suffix' => '12G-OC', 'price' => 679.99, 'stock' => 3, 'attributes' => ['memory' => '12GB GDDR6X', 'edition' => 'OC', 'length' => '301mm']],
                ['label' => '12GB Standard Edition', 'suffix' => '12G-STD', 'price' => 649.99, 'stock' => 2, 'attributes' => ['memory' => '12GB GDDR6X', 'edition' => 'Standard', 'length' => '301mm']],
            ],
        ],
        [
            'name' => 'Corsair RM Series 80+ Gold Power Supply',
            'sku' => 'COR-RM-GOLD',
            'category' => 'Power Supply',
            'brand' => 'Corsair',
            'price' => 149.99,
            'short_description' => 'Fully modular ATX supply with a zero-RPM fan mode.',
            'description' => 'Japanese 105C capacitors, 80 PLUS Gold efficiency and a magnetic levitation fan that stays off under light load. Ten-year warranty.',
            'is_featured' => false,
            'variants' => [
                ['label' => '750W', 'suffix' => '750W', 'price' => 129.99, 'stock' => 22, 'attributes' => ['wattage' => '750W', 'efficiency' => '80+ Gold', 'modular' => 'Fully modular']],
                ['label' => '850W', 'suffix' => '850W', 'price' => 149.99, 'stock' => 18, 'attributes' => ['wattage' => '850W', 'efficiency' => '80+ Gold', 'modular' => 'Fully modular']],
                ['label' => '1000W', 'suffix' => '1000W', 'price' => 199.99, 'stock' => 4, 'attributes' => ['wattage' => '1000W', 'efficiency' => '80+ Gold', 'modular' => 'Fully modular']],
            ],
        ],
        [
            'name' => 'Seagate IronWolf NAS Hard Drive',
            'sku' => 'SEA-IRONWOLF',
            'category' => 'Hard Drives',
            'brand' => 'Seagate',
            'price' => 199.99,
            'short_description' => 'CMR NAS drive rated for 24x7 multi-bay operation.',
            'description' => 'IronWolf drives are tuned for always-on NAS enclosures with rotational vibration sensors, AgileArray firmware and a 180TB/year workload rating.',
            'is_featured' => false,
            'variants' => [
                ['label' => '4TB', 'suffix' => '4TB', 'price' => 109.99, 'stock' => 25, 'attributes' => ['capacity' => '4TB', 'rpm' => '5900', 'interface' => 'SATA 6Gb/s']],
                ['label' => '8TB', 'suffix' => '8TB', 'price' => 199.99, 'stock' => 14, 'attributes' => ['capacity' => '8TB', 'rpm' => '7200', 'interface' => 'SATA 6Gb/s']],
                ['label' => '12TB', 'suffix' => '12TB', 'price' => 279.99, 'stock' => 0, 'attributes' => ['capacity' => '12TB', 'rpm' => '7200', 'interface' => 'SATA 6Gb/s']],
            ],
        ],
        [
            'name' => 'ASUS RT-AX86U Pro WiFi 6 Router',
            'sku' => 'AS-RTAX86U-PRO',
            'category' => 'Router',
            'brand' => 'ASUS',
            'price' => 249.99,
            'short_description' => 'AX5700 dual-band router with a 2.5G WAN/LAN port.',
            'description' => 'Mobile Game Mode, adaptive QoS and lifetime AiProtection Pro. Pairs with any AiMesh node to cover a larger floor plan.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Single unit', 'suffix' => 'SINGLE', 'price' => 249.99, 'stock' => 16, 'attributes' => ['pack' => '1 unit', 'wifi' => 'WiFi 6 AX5700', 'ports' => '2.5G WAN/LAN']],
                ['label' => '2-pack AiMesh kit', 'suffix' => '2PACK', 'price' => 459.99, 'stock' => 6, 'attributes' => ['pack' => '2 units', 'wifi' => 'WiFi 6 AX5700', 'ports' => '2.5G WAN/LAN']],
            ],
        ],

        /*
        | ---- Low stock: totals land on 1-5, at or under min_stock_alert ----
        */
        [
            'name' => 'Intel Core i9-14900K Processor',
            'sku' => 'INT-I9-14900K',
            'category' => 'Processors',
            'brand' => 'Intel',
            'price' => 589.00,
            'short_description' => '24-core LGA1700 flagship boosting to 6.0GHz.',
            'description' => 'Eight performance cores and sixteen efficient cores with Intel Thermal Velocity Boost. Unlocked for overclocking on Z790 boards.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Retail boxed', 'suffix' => 'BOX', 'price' => 589.00, 'stock' => 2, 'attributes' => ['packaging' => 'Retail box', 'cores' => '24', 'socket' => 'LGA1700']],
                ['label' => 'OEM tray', 'suffix' => 'TRAY', 'price' => 559.00, 'stock' => 1, 'attributes' => ['packaging' => 'OEM tray', 'cores' => '24', 'socket' => 'LGA1700']],
            ],
        ],
        [
            'name' => 'NVIDIA GeForce RTX 4090 Founders Edition',
            'sku' => 'NV-RTX4090-FE',
            'category' => 'Graphics Cards',
            'brand' => 'NVIDIA',
            'price' => 1599.00,
            'short_description' => '24GB flagship card with the reference vapour-chamber cooler.',
            'description' => 'The Founders Edition RTX 4090 in NVIDIA reference trim: 16,384 CUDA cores, 24GB of GDDR6X and a triple-slot vapour chamber.',
            'is_featured' => true,
            'variants' => [
                ['label' => '24GB Founders Edition', 'suffix' => '24G', 'price' => 1599.00, 'stock' => 2, 'attributes' => ['memory' => '24GB GDDR6X', 'edition' => 'Founders', 'length' => '304mm']],
                ['label' => '24GB + 1000W PSU bundle', 'suffix' => 'BUNDLE', 'price' => 1749.00, 'stock' => 1, 'attributes' => ['memory' => '24GB GDDR6X', 'edition' => 'Founders', 'bundle' => '1000W PSU']],
            ],
        ],
        [
            'name' => 'Corsair Dominator Platinum RGB DDR5',
            'sku' => 'COR-DOM-DDR5',
            'category' => 'Memory',
            'brand' => 'Corsair',
            'price' => 299.00,
            'short_description' => 'Hand-screened DDR5 with twelve addressable RGB LEDs per stick.',
            'description' => 'Dominator Platinum RGB uses a patented DHX cooling design and hand-sorted ICs for headroom well past the rated XMP profile.',
            'is_featured' => false,
            'variants' => [
                ['label' => '32GB (2x16GB) 6400MHz', 'suffix' => '32G-6400', 'price' => 299.00, 'stock' => 3, 'attributes' => ['capacity' => '32GB', 'speed' => '6400MHz', 'kit' => '2x16GB']],
                ['label' => '64GB (2x32GB) 6000MHz', 'suffix' => '64G-6000', 'price' => 469.00, 'stock' => 1, 'attributes' => ['capacity' => '64GB', 'speed' => '6000MHz', 'kit' => '2x32GB']],
            ],
        ],

        /*
        | ---- Out of stock: every variant sits at 0 ----
        */
        [
            'name' => 'ASUS ROG Maximus Z790 Dark Hero',
            'sku' => 'AS-MAX-Z790',
            'category' => 'Motherboards',
            'brand' => 'ASUS',
            'price' => 699.00,
            'short_description' => 'Flagship Z790 ATX board with 24+1 power stages.',
            'description' => 'Teamed 110A power stages, a 10Gb ethernet port and passive VRM cooling for a fanless flagship build.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Wi-Fi 7 Edition', 'suffix' => 'WIFI7', 'price' => 699.00, 'stock' => 0, 'attributes' => ['chipset' => 'Z790', 'socket' => 'LGA1700', 'form_factor' => 'ATX', 'wifi' => 'Wi-Fi 7']],
                ['label' => 'Standard Edition', 'suffix' => 'STD', 'price' => 649.00, 'stock' => 0, 'attributes' => ['chipset' => 'Z790', 'socket' => 'LGA1700', 'form_factor' => 'ATX', 'wifi' => 'None']],
            ],
        ],
        [
            'name' => 'AMD Ryzen 9 7950X3D Processor',
            'sku' => 'AMD-R9-7950X3D',
            'category' => 'Processors',
            'brand' => 'AMD',
            'price' => 699.00,
            'short_description' => '16-core AM5 chip with 128MB of 3D V-Cache.',
            'description' => 'Second-generation 3D V-Cache stacked over one CCD, giving the gaming uplift of the X3D line without losing multi-threaded throughput.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Retail boxed', 'suffix' => 'BOX', 'price' => 699.00, 'stock' => 0, 'attributes' => ['packaging' => 'Retail box', 'cores' => '16', 'socket' => 'AM5']],
                ['label' => 'OEM tray', 'suffix' => 'TRAY', 'price' => 669.00, 'stock' => 0, 'attributes' => ['packaging' => 'OEM tray', 'cores' => '16', 'socket' => 'AM5']],
            ],
        ],
        [
            'name' => 'Samsung Odyssey OLED G9 Gaming Monitor',
            'sku' => 'SAM-ODY-G9',
            'category' => 'Gaming Monitor',
            'brand' => 'Samsung',
            'price' => 1299.00,
            'short_description' => 'Ultrawide QD-OLED panel with a 0.03ms response time.',
            'description' => 'Quantum Dot OLED with a 1800R curve, DisplayPort 2.1 and a built-in smart hub that runs without a PC attached.',
            'is_featured' => false,
            'variants' => [
                ['label' => '49-inch DQHD 240Hz', 'suffix' => '49-DQHD', 'price' => 1299.00, 'stock' => 0, 'attributes' => ['size' => '49-inch', 'resolution' => '5120x1440', 'refresh_rate' => '240Hz', 'panel' => 'QD-OLED']],
                ['label' => '34-inch UWQHD 175Hz', 'suffix' => '34-UWQHD', 'price' => 899.00, 'stock' => 0, 'attributes' => ['size' => '34-inch', 'resolution' => '3440x1440', 'refresh_rate' => '175Hz', 'panel' => 'QD-OLED']],
            ],
        ],
        [
            'name' => 'WD Black SN850X NVMe SSD',
            'sku' => 'WD-SN850X',
            'category' => 'NVMe SSD',
            'brand' => 'Western Digital',
            'price' => 179.99,
            'short_description' => 'Gen4 gaming drive with a predictive Game Mode 2.0 cache.',
            'description' => 'The SN850X pairs WD in-house controller silicon with 112-layer TLC for 7,300 MB/s reads and low random-read latency.',
            'is_featured' => false,
            'variants' => [
                ['label' => '1TB', 'suffix' => '1TB', 'price' => 109.99, 'stock' => 0, 'attributes' => ['capacity' => '1TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '2TB', 'suffix' => '2TB', 'price' => 179.99, 'stock' => 0, 'attributes' => ['capacity' => '2TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '4TB', 'suffix' => '4TB', 'price' => 349.99, 'stock' => 0, 'attributes' => ['capacity' => '4TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
            ],
        ],
        [
            'name' => 'NZXT Kraken Elite 360 AIO Cooler',
            'sku' => 'NZXT-KRAKEN-360',
            'category' => 'CPU Cooler',
            'brand' => 'NZXT',
            'price' => 279.99,
            'short_description' => '360mm liquid cooler with a 2.36" LCD pump head.',
            'description' => 'A wide-format LCD on the pump head, three F120 RGB Core fans and a seventh-generation Asetek pump. Mounts on LGA1700 and AM5 out of the box.',
            'is_featured' => false,
            'variants' => [
                ['label' => '360mm Black', 'suffix' => '360-BLK', 'price' => 279.99, 'stock' => 0, 'attributes' => ['radiator' => '360mm', 'color' => 'Black', 'display' => '2.36in LCD']],
                ['label' => '360mm White', 'suffix' => '360-WHT', 'price' => 289.99, 'stock' => 0, 'attributes' => ['radiator' => '360mm', 'color' => 'White', 'display' => '2.36in LCD']],
            ],
        ],
        [
            'name' => 'Lian Li O11 Dynamic EVO PC Case',
            'sku' => 'LIAN-O11-EVO',
            'category' => 'PC Case',
            'brand' => 'Lian Li',
            'price' => 169.99,
            'short_description' => 'Dual-chamber showcase case with a reversible layout.',
            'description' => 'The O11 Dynamic EVO can be rebuilt as a normal or inverted layout, takes three 360mm radiators, and hides the PSU and cabling in a second chamber.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Mid Tower Black', 'suffix' => 'MID-BLK', 'price' => 169.99, 'stock' => 0, 'attributes' => ['form_factor' => 'Mid Tower', 'color' => 'Black', 'side_panel' => 'Tempered glass']],
                ['label' => 'Mid Tower White', 'suffix' => 'MID-WHT', 'price' => 179.99, 'stock' => 0, 'attributes' => ['form_factor' => 'Mid Tower', 'color' => 'White', 'side_panel' => 'Tempered glass']],
            ],
        ],
        [
            'name' => 'Elgato Stream Deck MK.2',
            'sku' => 'ELG-SD-MK2',
            'category' => 'Streaming Gear',
            'brand' => 'Elgato',
            'price' => 149.99,
            'short_description' => '15 LCD keys for one-touch scene and macro control.',
            'description' => 'Fifteen customisable LCD keys with a detachable USB-C cable and interchangeable faceplates. Integrates with OBS, Streamlabs and Teams.',
            'is_featured' => false,
            'variants' => [
                ['label' => '15 Key Black', 'suffix' => '15-BLK', 'price' => 149.99, 'stock' => 0, 'attributes' => ['keys' => '15', 'color' => 'Black', 'connection' => 'USB-C']],
                ['label' => '15 Key White', 'suffix' => '15-WHT', 'price' => 149.99, 'stock' => 0, 'attributes' => ['keys' => '15', 'color' => 'White', 'connection' => 'USB-C']],
            ],
        ],
    ];

    /** Sub-category => root category, mirroring ProductSeeder's tree. */
    private const CATEGORY_TREE = [
        'Graphics Cards' => 'Computer Components',
        'Processors' => 'Computer Components',
        'Motherboards' => 'Computer Components',
        'Memory' => 'Computer Components',
        'Power Supply' => 'Computer Components',
        'CPU Cooler' => 'Computer Components',
        'PC Case' => 'Computer Components',
        'NVMe SSD' => 'Storage',
        'Hard Drives' => 'Storage',
        'Gaming Laptop' => 'Laptop',
        'Office Monitor' => 'Monitor',
        'Gaming Monitor' => 'Monitor',
        'Keyboard' => 'Accessories',
        'Mouse' => 'Accessories',
        'Streaming Gear' => 'Accessories',
        'Router' => 'Networking',
    ];

    /**
     * The stock spread this seeder is meant to produce, checked after seeding.
     *
     * The counts are easy to break by nudging one variant's stock, and a silent
     * drift would only show up as a filter that looks wrong on the Products
     * screen — so run() reports a mismatch rather than leaving it to be found
     * by eye.
     */
    private const EXPECTED_STOCK_SPREAD = [
        'in stock' => 8,
        'low stock' => 5,
        'out of stock' => 7,
    ];

    public function run(): void
    {
        $this->reset();

        $categories = $this->categories();
        $brands = $this->brands();

        foreach (self::PRODUCTS as $index => $definition) {
            $stock = array_sum(array_column($definition['variants'], 'stock'));

            $product = Product::create([
                'category_id' => $categories[$definition['category']]->id,
                'brand_id' => $brands[$definition['brand']]->id,
                'name' => $definition['name'],
                'slug' => Str::slug($definition['name']),
                'sku' => $definition['sku'],
                'short_description' => $definition['short_description'],
                'description' => $definition['description'],
                'price' => $definition['price'],
                'cost_price' => round($definition['price'] * 0.75, 2),
                'stock_quantity' => $stock,
                'min_stock_alert' => 5,
                'track_inventory' => true,
                'in_stock' => $stock > 0,
                'thumbnail' => $this->imageUrl($definition['sku']),
                'meta_title' => $definition['name'],
                'meta_description' => $definition['short_description'],
                'is_active' => true,
                'is_featured' => $definition['is_featured'],
                'sort_order' => $index,
            ]);

            $this->seedImages($product, $definition);
            $this->seedVariants($product, $definition);
        }

        $this->command?->info('Catalog reset. Seeded '.count(self::PRODUCTS).' products with images and variants.');
        $this->reportStockSpread();
    }

    /**
     * Report the in / low / out split, and flag it when it drifts from intent.
     */
    private function reportStockSpread(): void
    {
        $actual = ['in stock' => 0, 'low stock' => 0, 'out of stock' => 0];

        foreach (Product::all() as $product) {
            $stock = (int) $product->stock_quantity;
            $bucket = match (true) {
                $stock <= 0 => 'out of stock',
                $stock <= (int) $product->min_stock_alert => 'low stock',
                default => 'in stock',
            };
            $actual[$bucket]++;
        }

        foreach ($actual as $bucket => $count) {
            $this->command?->line(sprintf('  %-13s %d', $bucket, $count));
        }

        if ($actual !== self::EXPECTED_STOCK_SPREAD) {
            $this->command?->warn('Stock spread drifted from EXPECTED_STOCK_SPREAD — check the variant stock values.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Reset
    |--------------------------------------------------------------------------
    */

    /**
     * Hard-delete the existing catalog, children first.
     *
     * Raw deletes rather than the models, so soft-deleted rows go too: the
     * variants table carries GLOBAL unique indexes on slug and sku that ignore
     * deleted_at, and a leftover soft-deleted row would collide on re-seed.
     */
    private function reset(): void
    {
        DB::table('product_images')->delete();
        DB::table('product_variants')->delete();
        DB::table('products')->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Taxonomy
    |--------------------------------------------------------------------------
    */

    /** @return array<string, Category> */
    private function categories(): array
    {
        $roots = [];
        foreach (array_unique(array_values(self::CATEGORY_TREE)) as $name) {
            $roots[$name] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }

        $categories = [];
        foreach (self::CATEGORY_TREE as $name => $parent) {
            $categories[$name] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'parent_id' => $roots[$parent]->id, 'is_active' => true]
            );
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
    | Media
    |--------------------------------------------------------------------------
    */

    /**
     * A stable, absolute image URL.
     *
     * The admin list only renders absolute URLs (product thumbnails are
     * normally Cloudinary secure_url values), so seed data has to be absolute
     * too or it falls back to the initials tile. picsum.photos is deterministic
     * per seed, which keeps a given product or variant on the same photo across
     * re-seeds.
     */
    private function imageUrl(string $seed, int $size = 800): string
    {
        return 'https://picsum.photos/seed/'.Str::slug($seed).'/'.$size.'/'.$size;
    }

    /**
     * One primary thumbnail row plus two gallery rows per product.
     *
     * Note: product_images is not exposed by the admin or client API today, the
     * screens read products.thumbnail. These rows are here so a gallery has
     * data the moment an endpoint is added.
     */
    private function seedImages(Product $product, array $definition): void
    {
        $images = [
            ['suffix' => '', 'type' => 'thumbnail', 'primary' => true, 'alt' => $definition['name']],
            ['suffix' => '-gallery-1', 'type' => 'gallery', 'primary' => false, 'alt' => $definition['name'].' front view'],
            ['suffix' => '-gallery-2', 'type' => 'gallery', 'primary' => false, 'alt' => $definition['name'].' detail view'],
        ];

        foreach ($images as $sortOrder => $image) {
            ProductImage::create([
                'product_id' => $product->id,
                'image' => $this->imageUrl($definition['sku'].$image['suffix']),
                'disk' => 'public',
                'mime_type' => 'image/jpeg',
                'alt_text' => $image['alt'],
                'title' => $definition['name'],
                'type' => $image['type'],
                'is_primary' => $image['primary'],
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
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
     * variant called e.g. "1TB" would otherwise collide.
     */
    private function seedVariants(Product $product, array $definition): void
    {
        foreach ($definition['variants'] as $sortOrder => $variant) {
            $sku = $definition['sku'].'-'.$variant['suffix'];

            ProductVariant::create([
                'product_id' => $product->id,
                'name' => $variant['label'],
                'slug' => Str::slug($sku.' '.$variant['label']),
                'sku' => $sku,
                'price' => $variant['price'],
                'cost_price' => round($variant['price'] * 0.75, 2),
                'stock_quantity' => $variant['stock'],
                'min_stock_alert' => 5,
                'track_inventory' => true,
                'in_stock' => $variant['stock'] > 0,
                'image' => $this->imageUrl($sku),
                'attributes' => $variant['attributes'],
                'is_default' => $sortOrder === 0,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
