<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the full computer-shop category taxonomy: top-level departments plus
 * their sub-categories, with every catalog-facing column populated (image
 * thumbnail, icon, SEO meta, sort order, featured flag). Idempotent — safe
 * to run repeatedly via firstOrCreate on slug.
 */
class CategorySeeder extends Seeder
{
    /**
     * Each top-level entry may carry a `children` array of the same shape.
     * Images are real, verified Unsplash photos matching the subject and are
     * requested pre-cropped to a 600x400 thumbnail via URL params.
     */
    private const CATEGORIES = [
        [
            'name' => 'Laptops',
            'description' => 'Portable Windows and macOS laptops for work, study, gaming, and everyday use.',
            'image' => '1496181133206-80ce9b88a853',
            'icon' => 'laptop',
            'is_featured' => true,
        ],
        [
            'name' => 'Desktop Computers',
            'description' => 'Pre-built desktop towers and all-in-one PCs for the office, home, and gaming setups.',
            'image' => '1587831990711-23ca6441447b',
            'icon' => 'desktop',
            'is_featured' => true,
        ],
        [
            'name' => 'Monitors',
            'description' => 'Office, 4K, ultrawide, and high-refresh gaming monitors for every desk.',
            'image' => '1527443224154-c4a3942d3acf',
            'icon' => 'monitor',
            'is_featured' => true,
        ],
        [
            'name' => 'PC Components',
            'description' => 'Graphics cards, processors, motherboards, memory, and everything needed to build or upgrade a PC.',
            'image' => '1591799264318-7e6ef8ddb7ea',
            'icon' => 'cpu',
            'is_featured' => true,
            'children' => [
                [
                    'name' => 'Graphics Cards',
                    'description' => 'Discrete GPUs for gaming, content creation, and workstation rendering.',
                    'image' => '1591488320449-011701bb6704',
                    'icon' => 'gpu-card',
                ],
                [
                    'name' => 'Processors',
                    'description' => 'Desktop CPUs from Intel and AMD for gaming, productivity, and creator builds.',
                    'image' => '1591799264318-7e6ef8ddb7ea',
                    'icon' => 'cpu',
                ],
                [
                    'name' => 'Motherboards',
                    'description' => 'ATX, Micro-ATX, and Mini-ITX motherboards to match any CPU platform.',
                    'image' => '1518770660439-4636190af475',
                    'icon' => 'circuit-board',
                ],
                [
                    'name' => 'Memory (RAM)',
                    'description' => 'DDR4 and DDR5 memory kits for gaming rigs, workstations, and laptops.',
                    'image' => '1672923491001-3e58a608e418',
                    'icon' => 'memory-stick',
                ],
                [
                    'name' => 'Power Supplies',
                    'description' => '80+ certified modular and non-modular power supply units for reliable builds.',
                    'image' => '1716062890647-60feae0609d0',
                    'icon' => 'power',
                ],
                [
                    'name' => 'PC Cases',
                    'description' => 'Mid-tower, full-tower, and compact cases with tempered-glass and RGB options.',
                    'image' => '1587202372775-e229f172b9d7',
                    'icon' => 'box',
                ],
                [
                    'name' => 'CPU Coolers',
                    'description' => 'Air coolers and AIO liquid coolers to keep every build running quiet and cool.',
                    'image' => '1628009151393-a6fa59844e67',
                    'icon' => 'fan',
                ],
            ],
        ],
        [
            'name' => 'Storage',
            'description' => 'Internal SSDs, hard drives, and portable external storage for every capacity need.',
            'image' => '1593448848024-77a27f0690b1',
            'icon' => 'hard-drive',
            'is_featured' => true,
            'children' => [
                [
                    'name' => 'Internal SSDs',
                    'description' => 'NVMe and SATA solid-state drives for fast boot times and application loading.',
                    'image' => '1604590003050-14c5b8521015',
                    'icon' => 'ssd',
                ],
                [
                    'name' => 'Hard Drives',
                    'description' => 'High-capacity HDDs for bulk storage, backups, and NAS setups.',
                    'image' => '1593448848024-77a27f0690b1',
                    'icon' => 'hard-drive',
                ],
                [
                    'name' => 'External Storage',
                    'description' => 'Portable external SSDs and flash drives for backups and on-the-go storage.',
                    'image' => '1756142752564-586c77618757',
                    'icon' => 'usb',
                ],
            ],
        ],
        [
            'name' => 'Peripherals',
            'description' => 'Keyboards, mice, and webcams to complete any desktop or laptop setup.',
            'image' => '1587829741301-dc798b83add3',
            'icon' => 'keyboard',
            'children' => [
                [
                    'name' => 'Keyboards',
                    'description' => 'Mechanical and membrane keyboards for typing, productivity, and gaming.',
                    'image' => '1587829741301-dc798b83add3',
                    'icon' => 'keyboard',
                ],
                [
                    'name' => 'Mice',
                    'description' => 'Wired and wireless mice for precision, ergonomics, and competitive gaming.',
                    'image' => '1527864550417-7fd91fc51a46',
                    'icon' => 'mouse',
                ],
                [
                    'name' => 'Webcams',
                    'description' => 'HD and 4K webcams for video calls, streaming, and content creation.',
                    'image' => '1750975314977-374f2290db53',
                    'icon' => 'webcam',
                ],
            ],
        ],
        [
            'name' => 'Networking',
            'description' => 'Routers, switches, and networking gear for reliable home and office connectivity.',
            'image' => '1544197150-b99a580bb7a8',
            'icon' => 'wifi',
        ],
        [
            'name' => 'Audio',
            'description' => 'Headphones, headsets, and speakers for music, calls, and gaming.',
            'image' => '1505740420928-5e560c06d30e',
            'icon' => 'headphones',
            'children' => [
                [
                    'name' => 'Headphones & Headsets',
                    'description' => 'Over-ear, in-ear, and gaming headsets with and without active noise cancellation.',
                    'image' => '1505740420928-5e560c06d30e',
                    'icon' => 'headphones',
                ],
                [
                    'name' => 'Speakers',
                    'description' => 'Desktop and bookshelf speakers for immersive sound at any desk.',
                    'image' => '1545454675-3531b543be5d',
                    'icon' => 'speaker',
                ],
            ],
        ],
        [
            'name' => 'Printers & Scanners',
            'description' => 'All-in-one inkjet and laser printers, plus scanners, for home and office documents.',
            'image' => '1612815154858-60aa4c59eaa6',
            'icon' => 'printer',
        ],
        [
            'name' => 'Accessories',
            'description' => 'Cables, laptop bags, power banks, and everyday essentials that complete your setup.',
            'image' => '1758640920659-0bb864175983',
            'icon' => 'tool',
            'children' => [
                [
                    'name' => 'Cables & Adapters',
                    'description' => 'USB, HDMI, and power cables and adapters for connecting every device.',
                    'image' => '1473831818960-c89731aabc3e',
                    'icon' => 'cable',
                ],
                [
                    'name' => 'Laptop Bags & Sleeves',
                    'description' => 'Backpacks, sleeves, and carry cases to protect laptops on the move.',
                    'image' => '1553062407-98eeb64c6a62',
                    'icon' => 'backpack',
                ],
                [
                    'name' => 'Power Banks & Chargers',
                    'description' => 'Portable power banks and fast chargers for laptops, phones, and tablets.',
                    'image' => '1609091839311-d5365f9ff1c5',
                    'icon' => 'battery-charging',
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $sortOrder = 0;

        foreach (self::CATEGORIES as $topLevel) {
            $sortOrder++;
            $parent = $this->createCategory($topLevel, null, $sortOrder);

            $childSortOrder = 0;
            foreach ($topLevel['children'] ?? [] as $child) {
                $childSortOrder++;
                $this->createCategory($child, $parent, $childSortOrder);
            }
        }
    }

    private function createCategory(array $data, ?Category $parent, int $sortOrder): Category
    {
        $slug = Str::slug($data['name']);

        return Category::firstOrCreate(
            ['slug' => $slug],
            [
                'parent_id' => $parent?->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'image' => $this->thumbnailUrl($data['image']),
                'icon' => $data['icon'],
                'meta_title' => $data['name'].' | Bekie Computer Shop',
                'meta_description' => Str::limit($data['description'], 150),
                'is_active' => true,
                'is_featured' => $data['is_featured'] ?? false,
                'sort_order' => $sortOrder,
            ]
        );
    }

    /**
     * Unsplash CDN photo IDs, requested pre-cropped to a 600x400 thumbnail.
     */
    private function thumbnailUrl(string $photoId): string
    {
        return "https://images.unsplash.com/photo-{$photoId}?auto=format&fit=crop&w=600&h=400&q=80";
    }
}
