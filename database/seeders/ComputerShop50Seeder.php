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
 * A 50-product computer shop catalog, each product carrying its own thumbnail,
 * a small image gallery, and one or two purchasable variants — every variant
 * with its own price, stock and image. Modeled directly on DemoProductSeeder's
 * conventions (same imageUrl/seedImages/seedVariants shape) but a distinct,
 * larger catalog, so both seeders can be run independently.
 *
 * Like DemoProductSeeder, this RESETS the catalog first — every product,
 * variant and product_image row is hard-deleted before the 50 products are
 * written — so it is deliberately NOT wired into DatabaseSeeder.
 *
 * Usage: php artisan db:seed --class=ComputerShop50Seeder
 */
class ComputerShop50Seeder extends Seeder
{
    /*
    |--------------------------------------------------------------------------
    | Catalog definition
    |--------------------------------------------------------------------------
    |
    | Each product has 1 or 2 variants. Variant `attributes` carry the axis
    | values (color / capacity / ram / ...) the admin variant editor reads
    | back. The product's own `price` mirrors its first (default) variant.
    */
    private const PRODUCTS = [
        // ---- Laptops ----
        [
            'name' => 'MSI Katana 15 Gaming Laptop',
            'sku' => 'MSI-KATANA15',
            'category' => 'Gaming Laptop',
            'brand' => 'MSI',
            'short_description' => '15.6" 144Hz gaming laptop with RTX 4060 graphics.',
            'description' => 'A Cooler Boost 5 thermal system keeps the Core i7 and RTX 4060 combo running cool through long sessions, on a 144Hz IPS panel.',
            'is_featured' => false,
            'variants' => [
                ['label' => '16GB / 512GB', 'suffix' => '16-512', 'price' => 1299.00, 'stock' => 18, 'attributes' => ['ram' => '16GB', 'storage' => '512GB SSD', 'gpu' => 'RTX 4060']],
                ['label' => '32GB / 1TB', 'suffix' => '32-1T', 'price' => 1499.00, 'stock' => 9, 'attributes' => ['ram' => '32GB', 'storage' => '1TB SSD', 'gpu' => 'RTX 4060']],
            ],
        ],
        [
            'name' => 'Lenovo ThinkPad X1 Carbon Gen 12',
            'sku' => 'LEN-X1C-G12',
            'category' => 'Business Laptop',
            'brand' => 'Lenovo',
            'short_description' => 'Carbon-fiber business ultrabook with a 14" 2.8K OLED option.',
            'description' => 'MIL-SPEC tested chassis, a fingerprint reader in the power button and up to 24 hours of battery life for the road warrior.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Core i7 / 16GB / 512GB', 'suffix' => 'I7-16-512', 'price' => 1799.00, 'stock' => 14, 'attributes' => ['cpu' => 'Core i7', 'ram' => '16GB', 'storage' => '512GB SSD']],
                ['label' => 'Core i7 / 32GB / 1TB', 'suffix' => 'I7-32-1T', 'price' => 2099.00, 'stock' => 6, 'attributes' => ['cpu' => 'Core i7', 'ram' => '32GB', 'storage' => '1TB SSD']],
            ],
        ],
        [
            'name' => 'Apple MacBook Air 15" M3',
            'sku' => 'APL-MBA15-M3',
            'category' => 'Ultrabook',
            'brand' => 'Apple',
            'short_description' => 'Fanless 15-inch ultrabook powered by the Apple M3 chip.',
            'description' => 'A larger 15.3" Liquid Retina display, silent fanless cooling and up to 18 hours of battery on a single charge.',
            'is_featured' => true,
            'variants' => [
                ['label' => '8GB / 256GB Midnight', 'suffix' => '8-256-MID', 'price' => 1299.00, 'stock' => 22, 'attributes' => ['ram' => '8GB', 'storage' => '256GB', 'color' => 'Midnight']],
                ['label' => '16GB / 512GB Starlight', 'suffix' => '16-512-STAR', 'price' => 1599.00, 'stock' => 11, 'attributes' => ['ram' => '16GB', 'storage' => '512GB', 'color' => 'Starlight']],
            ],
        ],
        [
            'name' => 'HP Spectre x360 14',
            'sku' => 'HP-SPX360-14',
            'category' => 'Ultrabook',
            'brand' => 'HP',
            'short_description' => '2-in-1 convertible ultrabook with a 3K2K OLED touch display.',
            'description' => 'A gem-cut aluminium chassis that folds into tablet, tent or stand mode, with an included Tilt Pen for sketching and notes.',
            'is_featured' => false,
            'variants' => [
                ['label' => '16GB / 1TB', 'suffix' => '16-1T', 'price' => 1449.00, 'stock' => 10, 'attributes' => ['ram' => '16GB', 'storage' => '1TB SSD', 'display' => '3K2K OLED touch']],
            ],
        ],
        [
            'name' => 'Dell Alienware m18 R2',
            'sku' => 'DEL-ALIEN-M18R2',
            'category' => 'Gaming Laptop',
            'brand' => 'Dell',
            'short_description' => '18-inch desktop-replacement gaming laptop with up to an RTX 4090.',
            'description' => 'Cryo-tech thermal architecture and a vapor chamber cooler let this 18-inch chassis push desktop-class GPUs at full power.',
            'is_featured' => true,
            'variants' => [
                ['label' => 'RTX 4070 / 32GB', 'suffix' => '4070-32', 'price' => 2899.00, 'stock' => 5, 'attributes' => ['gpu' => 'RTX 4070', 'ram' => '32GB', 'storage' => '1TB SSD']],
                ['label' => 'RTX 4090 / 64GB', 'suffix' => '4090-64', 'price' => 3999.00, 'stock' => 2, 'attributes' => ['gpu' => 'RTX 4090', 'ram' => '64GB', 'storage' => '2TB SSD']],
            ],
        ],
        [
            'name' => 'ASUS ROG Strix G16',
            'sku' => 'AS-ROGSTRIX-G16',
            'category' => 'Gaming Laptop',
            'brand' => 'ASUS',
            'short_description' => '16-inch gaming laptop with a 165Hz display and Core i9 option.',
            'description' => 'A tri-fan Intelligent Cooling system and a 165Hz QHD panel built for competitive frame rates without sacrificing portability.',
            'is_featured' => true,
            'variants' => [
                ['label' => 'RTX 4060 / 16GB', 'suffix' => '4060-16', 'price' => 1599.00, 'stock' => 13, 'attributes' => ['gpu' => 'RTX 4060', 'ram' => '16GB', 'storage' => '512GB SSD']],
                ['label' => 'RTX 4070 / 32GB', 'suffix' => '4070-32', 'price' => 1999.00, 'stock' => 7, 'attributes' => ['gpu' => 'RTX 4070', 'ram' => '32GB', 'storage' => '1TB SSD']],
            ],
        ],
        [
            'name' => 'Acer Predator Helios Neo 16',
            'sku' => 'ACE-PRED-HN16',
            'category' => 'Gaming Laptop',
            'brand' => 'Acer',
            'short_description' => '16-inch gaming laptop with a mini-LED display and RTX 40-series graphics.',
            'description' => 'A 250-nit mini-LED panel and a fifth-generation AeroBlade fan pair with up to an RTX 4070 for high-refresh gaming on the move.',
            'is_featured' => false,
            'variants' => [
                ['label' => '16GB / 1TB', 'suffix' => '16-1T', 'price' => 1499.00, 'stock' => 12, 'attributes' => ['ram' => '16GB', 'storage' => '1TB SSD', 'gpu' => 'RTX 4060']],
            ],
        ],

        // ---- Desktops ----
        [
            'name' => 'iBUYPOWER Slate MR Gaming Desktop',
            'sku' => 'IBP-SLATEMR',
            'category' => 'Gaming Desktop',
            'brand' => 'iBUYPOWER',
            'short_description' => 'Pre-built gaming tower with tempered-glass side panel and RGB fans.',
            'description' => 'A pre-built ATX tower with tool-less side access, six-fan airflow and a factory-tuned overclock on both CPU and GPU.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'RTX 4060 Ti / 16GB / 1TB', 'suffix' => '4060TI-16-1T', 'price' => 1799.00, 'stock' => 8, 'attributes' => ['gpu' => 'RTX 4060 Ti', 'ram' => '16GB', 'storage' => '1TB SSD']],
                ['label' => 'RTX 4070 Ti / 32GB / 2TB', 'suffix' => '4070TI-32-2T', 'price' => 2399.00, 'stock' => 3, 'attributes' => ['gpu' => 'RTX 4070 Ti', 'ram' => '32GB', 'storage' => '2TB SSD']],
            ],
        ],
        [
            'name' => 'Apple Mac Mini M4',
            'sku' => 'APL-MACMINI-M4',
            'category' => 'Mini PC',
            'brand' => 'Apple',
            'short_description' => 'Compact desktop powered by the Apple M4 chip.',
            'description' => 'A palm-sized aluminium unibody with Thunderbolt 5, built-in Wi-Fi 6E and enough power for everyday creative work.',
            'is_featured' => true,
            'variants' => [
                ['label' => '16GB / 256GB', 'suffix' => '16-256', 'price' => 599.00, 'stock' => 24, 'attributes' => ['ram' => '16GB', 'storage' => '256GB']],
                ['label' => '24GB / 512GB', 'suffix' => '24-512', 'price' => 899.00, 'stock' => 15, 'attributes' => ['ram' => '24GB', 'storage' => '512GB']],
            ],
        ],
        [
            'name' => 'Intel NUC 13 Extreme',
            'sku' => 'INT-NUC13-EXT',
            'category' => 'Mini PC',
            'brand' => 'Intel',
            'short_description' => 'Small-form-factor kit that accepts a full-length desktop GPU.',
            'description' => 'A compute element built around a full desktop Core i7, paired with a chassis that fits a standard-length graphics card.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Core i7 / 16GB / 512GB', 'suffix' => 'I7-16-512', 'price' => 999.00, 'stock' => 9, 'attributes' => ['cpu' => 'Core i7', 'ram' => '16GB', 'storage' => '512GB SSD']],
            ],
        ],
        [
            'name' => 'ASUS ROG Strix GA35 Desktop',
            'sku' => 'AS-ROGSTRIX-GA35',
            'category' => 'Gaming Desktop',
            'brand' => 'ASUS',
            'short_description' => 'Full-tower gaming desktop with liquid cooling and RGB front panel.',
            'description' => 'A 360mm AIO cooler and reinforced motherboard tray built to carry flagship GPUs without sag, in a tool-free chassis.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'RTX 4070 / 32GB', 'suffix' => '4070-32', 'price' => 2199.00, 'stock' => 6, 'attributes' => ['gpu' => 'RTX 4070', 'ram' => '32GB', 'storage' => '1TB SSD']],
                ['label' => 'RTX 4080 / 64GB', 'suffix' => '4080-64', 'price' => 2999.00, 'stock' => 2, 'attributes' => ['gpu' => 'RTX 4080', 'ram' => '64GB', 'storage' => '2TB SSD']],
            ],
        ],

        // ---- Monitors ----
        [
            'name' => 'LG UltraGear 27GR95QE OLED Monitor',
            'sku' => 'LG-UG27GR95QE',
            'category' => 'Gaming Monitor',
            'brand' => 'LG',
            'short_description' => '27" QHD OLED monitor with a 240Hz refresh rate.',
            'description' => 'A True 240Hz QHD OLED panel with 0.03ms response time and DisplayHDR True Black 400 certification.',
            'is_featured' => true,
            'variants' => [
                ['label' => '27-inch', 'suffix' => '27', 'price' => 899.00, 'stock' => 10, 'attributes' => ['size' => '27-inch', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel' => 'OLED']],
            ],
        ],
        [
            'name' => 'Samsung Odyssey G7 32"',
            'sku' => 'SAM-ODY-G7-32',
            'category' => 'Gaming Monitor',
            'brand' => 'Samsung',
            'short_description' => 'Curved 1000R QHD monitor with a 240Hz refresh rate.',
            'description' => 'A 1000R curve that matches the human eye\'s field of view, with 240Hz refresh and 1ms response for competitive play.',
            'is_featured' => false,
            'variants' => [
                ['label' => '32-inch QHD', 'suffix' => '32-QHD', 'price' => 649.00, 'stock' => 14, 'attributes' => ['size' => '32-inch', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz']],
                ['label' => '27-inch QHD', 'suffix' => '27-QHD', 'price' => 549.00, 'stock' => 19, 'attributes' => ['size' => '27-inch', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz']],
            ],
        ],
        [
            'name' => 'Dell UltraSharp U2723QE',
            'sku' => 'DEL-U2723QE',
            'category' => 'Office Monitor',
            'brand' => 'Dell',
            'short_description' => '27" 4K IPS Black monitor with 90W USB-C power delivery.',
            'description' => 'An IPS Black panel with a 2000:1 contrast ratio and single-cable USB-C docking, factory color-calibrated out of the box.',
            'is_featured' => false,
            'variants' => [
                ['label' => '27-inch 4K', 'suffix' => '27-4K', 'price' => 549.00, 'stock' => 17, 'attributes' => ['size' => '27-inch', 'resolution' => '3840x2160', 'panel' => 'IPS Black']],
            ],
        ],
        [
            'name' => 'BenQ PD3220U Designer Monitor',
            'sku' => 'BQ-PD3220U',
            'category' => 'Office Monitor',
            'brand' => 'BenQ',
            'short_description' => '32" 4K monitor with 99% Adobe RGB coverage for creative work.',
            'description' => 'A hardware calibration report ships with every unit, covering 99% Adobe RGB and 100% sRGB for color-critical editing.',
            'is_featured' => false,
            'variants' => [
                ['label' => '32-inch 4K', 'suffix' => '32-4K', 'price' => 1099.00, 'stock' => 5, 'attributes' => ['size' => '32-inch', 'resolution' => '3840x2160', 'color_gamut' => '99% Adobe RGB']],
            ],
        ],
        [
            'name' => 'ViewSonic Elite XG270QG',
            'sku' => 'VS-ELITE-XG270QG',
            'category' => 'Gaming Monitor',
            'brand' => 'ViewSonic',
            'short_description' => '27" QHD IPS monitor with NVIDIA G-SYNC and a 240Hz refresh rate.',
            'description' => 'A Quantum Dot IPS panel with native G-SYNC processing for tear-free, low-latency competitive gaming.',
            'is_featured' => false,
            'variants' => [
                ['label' => '27-inch 240Hz', 'suffix' => '27-240', 'price' => 749.00, 'stock' => 8, 'attributes' => ['size' => '27-inch', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz']],
            ],
        ],

        // ---- Components ----
        [
            'name' => 'AMD Ryzen 9 9950X Processor',
            'sku' => 'AMD-R9-9950X',
            'category' => 'Processors',
            'brand' => 'AMD',
            'short_description' => '16-core AM5 flagship processor built on Zen 5.',
            'description' => 'Sixteen Zen 5 cores and thirty-two threads with a boost clock past 5.7GHz, unlocked for overclocking on X670E boards.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Retail boxed', 'suffix' => 'BOX', 'price' => 649.00, 'stock' => 16, 'attributes' => ['packaging' => 'Retail box', 'cores' => '16', 'socket' => 'AM5']],
                ['label' => 'OEM tray', 'suffix' => 'TRAY', 'price' => 609.00, 'stock' => 7, 'attributes' => ['packaging' => 'OEM tray', 'cores' => '16', 'socket' => 'AM5']],
            ],
        ],
        [
            'name' => 'Intel Core Ultra 9 285K Processor',
            'sku' => 'INT-U9-285K',
            'category' => 'Processors',
            'brand' => 'Intel',
            'short_description' => 'Flagship LGA1851 processor built on the Arrow Lake architecture.',
            'description' => 'A 24-core, 24-thread design with integrated Arc graphics, unlocked for overclocking on Z890 boards.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Retail boxed', 'suffix' => 'BOX', 'price' => 589.00, 'stock' => 12, 'attributes' => ['packaging' => 'Retail box', 'cores' => '24', 'socket' => 'LGA1851']],
                ['label' => 'OEM tray', 'suffix' => 'TRAY', 'price' => 559.00, 'stock' => 4, 'attributes' => ['packaging' => 'OEM tray', 'cores' => '24', 'socket' => 'LGA1851']],
            ],
        ],
        [
            'name' => 'NVIDIA GeForce RTX 5080 Founders Edition',
            'sku' => 'NV-RTX5080-FE',
            'category' => 'Graphics Cards',
            'brand' => 'NVIDIA',
            'short_description' => '16GB flagship-tier card with the reference dual-fan cooler.',
            'description' => 'NVIDIA reference trim with a dual-fan flow-through cooler, 16GB of GDDR7 and full DLSS 4 support.',
            'is_featured' => true,
            'variants' => [
                ['label' => '16GB Founders Edition', 'suffix' => '16G-FE', 'price' => 999.00, 'stock' => 4, 'attributes' => ['memory' => '16GB GDDR7', 'edition' => 'Founders', 'length' => '304mm']],
            ],
        ],
        [
            'name' => 'AMD Radeon RX 7900 XTX',
            'sku' => 'AMD-RX7900XTX',
            'category' => 'Graphics Cards',
            'brand' => 'AMD',
            'short_description' => '24GB flagship Radeon card with RDNA 3 architecture.',
            'description' => 'A triple-fan reference-class cooler over 24GB of GDDR6, tuned for 4K gaming and content creation workloads alike.',
            'is_featured' => false,
            'variants' => [
                ['label' => '24GB Reference', 'suffix' => '24G-REF', 'price' => 949.00, 'stock' => 6, 'attributes' => ['memory' => '24GB GDDR6', 'edition' => 'Reference', 'length' => '287mm']],
                ['label' => '24GB OC Edition', 'suffix' => '24G-OC', 'price' => 999.00, 'stock' => 3, 'attributes' => ['memory' => '24GB GDDR6', 'edition' => 'OC', 'length' => '318mm']],
            ],
        ],
        [
            'name' => 'MSI MPG Z790 Carbon WiFi Motherboard',
            'sku' => 'MSI-Z790-CARBON',
            'category' => 'Motherboards',
            'brand' => 'MSI',
            'short_description' => 'LGA1700 ATX motherboard with Wi-Fi 7 and PCIe 5.0.',
            'description' => 'A 24+1+2 power stage VRM design, PCIe 5.0 x16 slot and pre-installed I/O shield for a fast, tidy build.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'LGA1700 ATX', 'suffix' => 'LGA1700', 'price' => 399.00, 'stock' => 11, 'attributes' => ['chipset' => 'Z790', 'socket' => 'LGA1700', 'form_factor' => 'ATX']],
            ],
        ],
        [
            'name' => 'Gigabyte X670E Aorus Master',
            'sku' => 'GB-X670E-MASTER',
            'category' => 'Motherboards',
            'brand' => 'Gigabyte',
            'short_description' => 'Flagship AM5 ATX board with a 20+2+2 power stage VRM.',
            'description' => 'Direct-touch heat pipes, a 10Gb ethernet port and triple M.2 slots with individual heatsinks for a high-end AM5 build.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'AM5 ATX', 'suffix' => 'AM5', 'price' => 479.00, 'stock' => 8, 'attributes' => ['chipset' => 'X670E', 'socket' => 'AM5', 'form_factor' => 'ATX']],
            ],
        ],
        [
            'name' => 'G.Skill Trident Z5 RGB DDR5',
            'sku' => 'GSK-TZ5-RGB',
            'category' => 'Memory',
            'brand' => 'G.Skill',
            'short_description' => 'DDR5 kit with an addressable RGB light bar and CL30 timings.',
            'description' => 'Hand-binned ICs run tight CL30 timings out of the box, with a diffused RGB light bar tuned for XMP 3.0 and AMD EXPO boards.',
            'is_featured' => false,
            'variants' => [
                ['label' => '32GB (2x16GB) 6000MHz', 'suffix' => '32G-6000', 'price' => 189.00, 'stock' => 20, 'attributes' => ['capacity' => '32GB', 'speed' => '6000MHz', 'kit' => '2x16GB']],
                ['label' => '64GB (2x32GB) 6000MHz', 'suffix' => '64G-6000', 'price' => 339.00, 'stock' => 9, 'attributes' => ['capacity' => '64GB', 'speed' => '6000MHz', 'kit' => '2x32GB']],
            ],
        ],
        [
            'name' => 'Crucial P3 Plus NVMe SSD',
            'sku' => 'CRU-P3PLUS',
            'category' => 'NVMe SSD',
            'brand' => 'Crucial',
            'short_description' => 'Budget-friendly PCIe 4.0 M.2 drive reaching 5,000 MB/s.',
            'description' => 'QLC NAND on a PCIe 4.0 x4 interface, a solid everyday upgrade for boot drives and game libraries.',
            'is_featured' => false,
            'variants' => [
                ['label' => '1TB', 'suffix' => '1TB', 'price' => 59.99, 'stock' => 42, 'attributes' => ['capacity' => '1TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
                ['label' => '2TB', 'suffix' => '2TB', 'price' => 89.99, 'stock' => 27, 'attributes' => ['capacity' => '2TB', 'interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280']],
            ],
        ],
        [
            'name' => 'SanDisk Extreme Portable SSD',
            'sku' => 'SD-EXT-PORT',
            'category' => 'External Storage',
            'brand' => 'SanDisk',
            'short_description' => 'IP65-rated portable SSD reaching 1,050 MB/s over USB-C.',
            'description' => 'A rugged, drop-resistant enclosure rated IP65 for dust and water, with a built-in USB-C to USB-A cable.',
            'is_featured' => false,
            'variants' => [
                ['label' => '1TB', 'suffix' => '1TB', 'price' => 99.99, 'stock' => 30, 'attributes' => ['capacity' => '1TB', 'interface' => 'USB-C 3.2']],
                ['label' => '2TB', 'suffix' => '2TB', 'price' => 179.99, 'stock' => 16, 'attributes' => ['capacity' => '2TB', 'interface' => 'USB-C 3.2']],
            ],
        ],
        [
            'name' => 'Seagate Backup Plus Hub',
            'sku' => 'SEA-BKPLUS-HUB',
            'category' => 'External Storage',
            'brand' => 'Seagate',
            'short_description' => 'Desktop external hard drive with a built-in 2-port USB hub.',
            'description' => 'A desktop drive that doubles as a USB hub, with two extra ports for keyboards, mice or card readers.',
            'is_featured' => false,
            'variants' => [
                ['label' => '8TB', 'suffix' => '8TB', 'price' => 149.99, 'stock' => 13, 'attributes' => ['capacity' => '8TB', 'interface' => 'USB 3.0']],
            ],
        ],
        [
            'name' => 'Cooler Master MasterLiquid 360 Atmos',
            'sku' => 'CM-ML360-ATMOS',
            'category' => 'CPU Cooler',
            'brand' => 'Cooler Master',
            'short_description' => '360mm AIO cooler with a ring-style infinity mirror pump head.',
            'description' => 'A dual-chamber pump and an infinity-mirror LCD ring on the pump head, compatible with both LGA1700 and AM5.',
            'is_featured' => false,
            'variants' => [
                ['label' => '360mm Black', 'suffix' => '360-BLK', 'price' => 219.99, 'stock' => 10, 'attributes' => ['radiator' => '360mm', 'color' => 'Black']],
                ['label' => '360mm White', 'suffix' => '360-WHT', 'price' => 229.99, 'stock' => 6, 'attributes' => ['radiator' => '360mm', 'color' => 'White']],
            ],
        ],
        [
            'name' => 'NZXT H9 Flow PC Case',
            'sku' => 'NZXT-H9-FLOW',
            'category' => 'PC Case',
            'brand' => 'NZXT',
            'short_description' => 'Dual-chamber mid-tower with a mesh front and dual-sided glass.',
            'description' => 'A dual-chamber layout hides cabling and the PSU behind the motherboard tray, with dual tempered-glass panels up front and on the side.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 159.99, 'stock' => 14, 'attributes' => ['color' => 'Black', 'form_factor' => 'Mid Tower']],
                ['label' => 'White', 'suffix' => 'WHT', 'price' => 159.99, 'stock' => 11, 'attributes' => ['color' => 'White', 'form_factor' => 'Mid Tower']],
            ],
        ],
        [
            'name' => 'Fractal Design North PC Case',
            'sku' => 'FD-NORTH',
            'category' => 'PC Case',
            'brand' => 'Fractal Design',
            'short_description' => 'Mid-tower case with a real wood front panel and mesh airflow.',
            'description' => 'Walnut or oak slats front a high-airflow mesh insert, giving the North a warmer, less "gamer" look without giving up thermals.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Chalk White / Oak', 'suffix' => 'CHALK-OAK', 'price' => 169.99, 'stock' => 9, 'attributes' => ['color' => 'Chalk White', 'wood' => 'Oak']],
                ['label' => 'Charcoal Black / Walnut', 'suffix' => 'CHAR-WALNUT', 'price' => 169.99, 'stock' => 7, 'attributes' => ['color' => 'Charcoal Black', 'wood' => 'Walnut']],
            ],
        ],
        [
            'name' => 'Corsair RM1000x SHIFT Power Supply',
            'sku' => 'COR-RM1000X-SHIFT',
            'category' => 'Power Supply',
            'brand' => 'Corsair',
            'short_description' => '1000W 80+ Gold PSU with side-mounted cable connectors.',
            'description' => 'Side-mounted modular connectors make cable routing tidier in cases with restrictive PSU shrouds, at full 80+ Gold efficiency.',
            'is_featured' => false,
            'variants' => [
                ['label' => '1000W', 'suffix' => '1000W', 'price' => 219.99, 'stock' => 15, 'attributes' => ['wattage' => '1000W', 'efficiency' => '80+ Gold', 'modular' => 'Fully modular']],
            ],
        ],
        [
            'name' => 'be quiet! Dark Power 13',
            'sku' => 'BQ-DARKPOWER13',
            'category' => 'Power Supply',
            'brand' => 'be quiet!',
            'short_description' => '80+ Titanium PSU with a near-silent fan curve.',
            'description' => 'Titanium-rated efficiency and a virtually silent fan profile under typical desktop loads, backed by a 10-year warranty.',
            'is_featured' => false,
            'variants' => [
                ['label' => '850W', 'suffix' => '850W', 'price' => 259.99, 'stock' => 8, 'attributes' => ['wattage' => '850W', 'efficiency' => '80+ Titanium', 'modular' => 'Fully modular']],
                ['label' => '1000W', 'suffix' => '1000W', 'price' => 299.99, 'stock' => 4, 'attributes' => ['wattage' => '1000W', 'efficiency' => '80+ Titanium', 'modular' => 'Fully modular']],
            ],
        ],

        // ---- Peripherals ----
        [
            'name' => 'Logitech G Pro X Superlight 2',
            'sku' => 'LOG-GPROX-SL2',
            'category' => 'Mouse',
            'brand' => 'Logitech',
            'short_description' => 'Sub-60g wireless esports mouse with a HERO 2 sensor.',
            'description' => 'A HERO 2 sensor reading up to 32,000 DPI in a shell under 60 grams, built for competitive FPS play.',
            'is_featured' => true,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 159.99, 'stock' => 25, 'attributes' => ['color' => 'Black', 'connectivity' => 'LIGHTSPEED wireless']],
                ['label' => 'White', 'suffix' => 'WHT', 'price' => 159.99, 'stock' => 18, 'attributes' => ['color' => 'White', 'connectivity' => 'LIGHTSPEED wireless']],
            ],
        ],
        [
            'name' => 'Razer DeathAdder V3 Pro',
            'sku' => 'RAZ-DA-V3PRO',
            'category' => 'Mouse',
            'brand' => 'Razer',
            'short_description' => 'Ergonomic wireless mouse with a Focus Pro 30K sensor.',
            'description' => 'The classic DeathAdder ergonomic shell, now wireless, with a 30,000 DPI optical sensor and up to 90 hours of battery life.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 149.99, 'stock' => 21, 'attributes' => ['color' => 'Black', 'connectivity' => 'HyperSpeed wireless']],
                ['label' => 'White', 'suffix' => 'WHT', 'price' => 149.99, 'stock' => 12, 'attributes' => ['color' => 'White', 'connectivity' => 'HyperSpeed wireless']],
            ],
        ],
        [
            'name' => 'SteelSeries Apex Pro TKL',
            'sku' => 'SS-APEXPRO-TKL',
            'category' => 'Keyboard',
            'brand' => 'SteelSeries',
            'short_description' => 'Tenkeyless keyboard with adjustable-actuation OmniPoint switches.',
            'description' => 'OmniPoint 2.0 magnetic switches let every key\'s actuation point be tuned per-key, from a hair-trigger 0.1mm to a deliberate 4.0mm.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'US Layout Black', 'suffix' => 'US-BLK', 'price' => 219.99, 'stock' => 9, 'attributes' => ['layout' => 'TKL', 'switch' => 'OmniPoint 2.0', 'color' => 'Black']],
            ],
        ],
        [
            'name' => 'Keychron Q1 Pro',
            'sku' => 'KEY-Q1PRO',
            'category' => 'Keyboard',
            'brand' => 'Keychron',
            'short_description' => 'Wireless QMK/VIA custom mechanical keyboard with a gasket mount.',
            'description' => 'A CNC aluminium case with a gasket mount for a softer typing feel, fully programmable through open-source QMK/VIA firmware.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Carbon Black', 'suffix' => 'CARBON', 'price' => 199.99, 'stock' => 10, 'attributes' => ['layout' => '75%', 'color' => 'Carbon Black']],
                ['label' => 'Silver Gray', 'suffix' => 'SILVER', 'price' => 199.99, 'stock' => 6, 'attributes' => ['layout' => '75%', 'color' => 'Silver Gray']],
            ],
        ],
        [
            'name' => 'HyperX Cloud Alpha Wireless',
            'sku' => 'HX-CLOUDALPHA-WL',
            'category' => 'Headset',
            'brand' => 'HyperX',
            'short_description' => 'Wireless gaming headset rated for up to 300 hours of battery life.',
            'description' => 'Dual-chamber 50mm drivers and a battery rated for up to 300 hours, so it rarely needs to leave the desk to charge.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Black / Red', 'suffix' => 'BLK-RED', 'price' => 199.99, 'stock' => 17, 'attributes' => ['color' => 'Black/Red', 'connectivity' => '2.4GHz wireless']],
            ],
        ],
        [
            'name' => 'Razer BlackShark V2 Pro',
            'sku' => 'RAZ-BSV2-PRO',
            'category' => 'Headset',
            'brand' => 'Razer',
            'short_description' => 'Lightweight esports headset with TriForce Titanium drivers.',
            'description' => 'TriForce Titanium 50mm drivers split the frequency range for clearer footsteps and gunfire, in a 320g wireless shell.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 179.99, 'stock' => 14, 'attributes' => ['color' => 'Black', 'connectivity' => 'HyperSpeed wireless']],
            ],
        ],
        [
            'name' => 'Logitech Brio 500 Webcam',
            'sku' => 'LOG-BRIO500',
            'category' => 'Webcam',
            'brand' => 'Logitech',
            'short_description' => '1080p webcam with HDR and auto light correction.',
            'description' => 'RightLight technology auto-corrects exposure in mixed or backlit lighting, with a built-in privacy shutter.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Graphite', 'suffix' => 'GRAPHITE', 'price' => 99.99, 'stock' => 23, 'attributes' => ['color' => 'Graphite', 'resolution' => '1080p']],
                ['label' => 'Rose', 'suffix' => 'ROSE', 'price' => 99.99, 'stock' => 11, 'attributes' => ['color' => 'Rose', 'resolution' => '1080p']],
            ],
        ],
        [
            'name' => 'Elgato Facecam MK.2',
            'sku' => 'ELG-FACECAM-MK2',
            'category' => 'Webcam',
            'brand' => 'Elgato',
            'short_description' => 'True 1080p60 webcam with a fixed-focus glass lens built for streaming.',
            'description' => 'A sensor built for video rather than photos, with a fixed-focus lens and full manual control over exposure, white balance and FOV.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Standard', 'suffix' => 'STD', 'price' => 149.99, 'stock' => 8, 'attributes' => ['resolution' => '1080p60', 'lens' => 'Fixed focus glass']],
            ],
        ],
        [
            'name' => 'Logitech Z407 Bluetooth Speakers',
            'sku' => 'LOG-Z407',
            'category' => 'Speakers',
            'brand' => 'Logitech',
            'short_description' => '2.1 desktop speaker system with a wireless subwoofer and control pod.',
            'description' => 'A wireless subwoofer and a wired control pod with volume dial and Bluetooth pairing button keep the desk cable-free.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Standard', 'suffix' => 'STD', 'price' => 99.99, 'stock' => 16, 'attributes' => ['channels' => '2.1', 'connectivity' => 'Bluetooth + 3.5mm']],
            ],
        ],

        // ---- Networking ----
        [
            'name' => 'TP-Link Archer AXE300 WiFi 6E Router',
            'sku' => 'TPL-AXE300',
            'category' => 'Router',
            'brand' => 'TP-Link',
            'short_description' => 'Tri-band WiFi 6E router with a dedicated 6GHz band.',
            'description' => 'A dedicated 6GHz band avoids the congestion of the 5GHz band entirely for compatible devices, alongside a 10G WAN port.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Single unit', 'suffix' => 'SINGLE', 'price' => 329.99, 'stock' => 10, 'attributes' => ['wifi' => 'WiFi 6E', 'ports' => '10G WAN']],
            ],
        ],
        [
            'name' => 'Netgear Nighthawk RS700S',
            'sku' => 'NGR-RS700S',
            'category' => 'Router',
            'brand' => 'Netgear',
            'short_description' => 'WiFi 7 router with multi-gig ports for fiber connections.',
            'description' => 'Tri-band WiFi 7 with a 10G WAN/LAN port, built for households running multi-gig fiber and dozens of connected devices.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Single unit', 'suffix' => 'SINGLE', 'price' => 599.99, 'stock' => 4, 'attributes' => ['wifi' => 'WiFi 7', 'ports' => '10G WAN/LAN']],
            ],
        ],
        [
            'name' => 'TP-Link TL-SG1024 Switch',
            'sku' => 'TPL-SG1024',
            'category' => 'Switch',
            'brand' => 'TP-Link',
            'short_description' => '24-port unmanaged Gigabit switch in a metal desktop/rackmount case.',
            'description' => 'Fanless and unmanaged, with a metal case and rackmount ears included for wiring closets and home labs alike.',
            'is_featured' => false,
            'variants' => [
                ['label' => '24-Port', 'suffix' => '24PORT', 'price' => 89.99, 'stock' => 13, 'attributes' => ['ports' => '24', 'speed' => 'Gigabit']],
            ],
        ],

        // ---- Other peripherals ----
        [
            'name' => 'APC Back-UPS Pro 1500VA',
            'sku' => 'APC-BUPS-1500',
            'category' => 'UPS',
            'brand' => 'APC',
            'short_description' => 'Battery backup with surge protection for a desktop and monitor.',
            'description' => 'Ten outlets split between battery-backed and surge-only, with a USB port for graceful shutdown software on connected PCs.',
            'is_featured' => false,
            'variants' => [
                ['label' => '1500VA', 'suffix' => '1500VA', 'price' => 219.99, 'stock' => 9, 'attributes' => ['capacity' => '1500VA', 'outlets' => '10']],
            ],
        ],
        [
            'name' => 'Anker 675 USB-C Docking Station',
            'sku' => 'ANK-675-DOCK',
            'category' => 'Docking Station',
            'brand' => 'Anker',
            'short_description' => '12-in-1 USB-C dock with dual HDMI and 100W passthrough charging.',
            'description' => 'Twelve ports on a single USB-C cable, including dual HDMI for a two-monitor setup and 100W power delivery passthrough.',
            'is_featured' => false,
            'variants' => [
                ['label' => '12-in-1', 'suffix' => '12IN1', 'price' => 219.99, 'stock' => 12, 'attributes' => ['ports' => '12', 'power_delivery' => '100W']],
            ],
        ],
        [
            'name' => 'Belkin Thunderbolt 4 Dock',
            'sku' => 'BLK-TB4-DOCK',
            'category' => 'Docking Station',
            'brand' => 'Belkin',
            'short_description' => 'Thunderbolt 4 dock supporting dual 4K displays and 90W charging.',
            'description' => 'Certified Thunderbolt 4 bandwidth drives dual 4K60 displays from a single cable, with 90W of power delivery back to the host laptop.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Pro Dock', 'suffix' => 'PRO', 'price' => 299.99, 'stock' => 7, 'attributes' => ['ports' => '11', 'power_delivery' => '90W']],
            ],
        ],
        [
            'name' => 'Epson EcoTank ET-4850 Printer',
            'sku' => 'EPS-ET4850',
            'category' => 'Printer',
            'brand' => 'Epson',
            'short_description' => 'Cartridge-free all-in-one printer with refillable ink tanks.',
            'description' => 'Refillable ink tanks replace cartridges entirely, with bundled ink rated to cover years of typical home-office printing.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Standard', 'suffix' => 'STD', 'price' => 399.99, 'stock' => 6, 'attributes' => ['type' => 'All-in-one', 'ink_system' => 'EcoTank']],
            ],
        ],
        [
            'name' => 'Canon PIXMA TS9520',
            'sku' => 'CAN-PIXMA-TS9520',
            'category' => 'Printer',
            'brand' => 'Canon',
            'short_description' => 'Compact all-in-one photo printer with a built-in disc tray.',
            'description' => 'A six-ink system tuned for photo output, plus a disc tray for printing directly onto printable CDs and DVDs.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Black', 'suffix' => 'BLK', 'price' => 199.99, 'stock' => 10, 'attributes' => ['color' => 'Black', 'type' => 'All-in-one photo']],
                ['label' => 'White', 'suffix' => 'WHT', 'price' => 199.99, 'stock' => 5, 'attributes' => ['color' => 'White', 'type' => 'All-in-one photo']],
            ],
        ],
        [
            'name' => 'Secretlab TITAN Evo Gaming Chair',
            'sku' => 'SEC-TITAN-EVO',
            'category' => 'Gaming Chair',
            'brand' => 'Secretlab',
            'short_description' => 'Ergonomic gaming chair with an integrated lumbar support system.',
            'description' => 'A magnetic memory foam head pillow and a built-in adjustable lumbar curve, rated for a wide range of heights and weights.',
            'is_featured' => true,
            'variants' => [
                ['label' => 'Fabric Black', 'suffix' => 'FAB-BLK', 'price' => 549.99, 'stock' => 8, 'attributes' => ['material' => 'SoftWeave Fabric', 'color' => 'Black']],
                ['label' => 'Leather Black', 'suffix' => 'LTH-BLK', 'price' => 619.99, 'stock' => 5, 'attributes' => ['material' => 'NEO Hybrid Leatherette', 'color' => 'Black']],
            ],
        ],
        [
            'name' => 'Fully Jarvis Standing Desk',
            'sku' => 'FUL-JARVIS',
            'category' => 'Desk',
            'brand' => 'Fully',
            'short_description' => 'Electric height-adjustable standing desk frame with memory presets.',
            'description' => 'A dual-motor electric lift frame with four programmable height presets, quiet enough for a video call while it adjusts.',
            'is_featured' => false,
            'variants' => [
                ['label' => 'Bamboo Top', 'suffix' => 'BAMBOO', 'price' => 649.99, 'stock' => 6, 'attributes' => ['top_material' => 'Bamboo']],
                ['label' => 'Laminate Top', 'suffix' => 'LAMINATE', 'price' => 549.99, 'stock' => 10, 'attributes' => ['top_material' => 'Laminate']],
            ],
        ],
    ];

    /** Sub-category => root category. */
    private const CATEGORY_TREE = [
        'Graphics Cards' => 'Computer Components',
        'Processors' => 'Computer Components',
        'Motherboards' => 'Computer Components',
        'Memory' => 'Computer Components',
        'Power Supply' => 'Computer Components',
        'CPU Cooler' => 'Computer Components',
        'PC Case' => 'Computer Components',
        'NVMe SSD' => 'Storage',
        'External Storage' => 'Storage',
        'Gaming Laptop' => 'Laptop',
        'Business Laptop' => 'Laptop',
        'Ultrabook' => 'Laptop',
        'Gaming Desktop' => 'Desktop',
        'Mini PC' => 'Desktop',
        'Gaming Monitor' => 'Monitor',
        'Office Monitor' => 'Monitor',
        'Mouse' => 'Accessories',
        'Keyboard' => 'Accessories',
        'Headset' => 'Accessories',
        'Webcam' => 'Accessories',
        'Speakers' => 'Accessories',
        'Router' => 'Networking',
        'Switch' => 'Networking',
        'UPS' => 'Peripherals',
        'Docking Station' => 'Peripherals',
        'Printer' => 'Peripherals',
        'Gaming Chair' => 'Furniture',
        'Desk' => 'Furniture',
    ];

    public function run(): void
    {
        $this->reset();

        $categories = $this->categories();
        $brands = $this->brands();

        foreach (self::PRODUCTS as $index => $definition) {
            $stock = array_sum(array_column($definition['variants'], 'stock'));
            $price = $definition['variants'][0]['price'];

            $product = Product::create([
                'category_id' => $categories[$definition['category']]->id,
                'brand_id' => $brands[$definition['brand']]->id,
                'name' => $definition['name'],
                'slug' => Str::slug($definition['name']),
                'sku' => $definition['sku'],
                'short_description' => $definition['short_description'],
                'description' => $definition['description'],
                'price' => $price,
                'cost_price' => round($price * 0.75, 2),
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

        $this->command?->info('Catalog reset. Seeded '.count(self::PRODUCTS).' computer shop products with images and variants.');
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
     * A stable, absolute image URL. picsum.photos is deterministic per seed,
     * which keeps a given product or variant on the same photo across
     * re-seeds.
     */
    private function imageUrl(string $seed, int $size = 800): string
    {
        return 'https://picsum.photos/seed/'.Str::slug($seed).'/'.$size.'/'.$size;
    }

    /** One primary thumbnail row plus two gallery rows per product. */
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
