<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Create a clean, realistic default category list for the storefront.
     *
     * The first ten entries (Laptops through Gaming) keep the exact slugs that
     * ProductSeeder::run() looks up via Category::firstOrCreate(['slug' => ...]),
     * so product-category links stay intact when this seeder re-runs.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Laptops',
                'description' => 'Portable performance for work, study, and travel.',
                'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=80',
                'icon' => 'laptop',
                'meta_title' => 'Laptops - Shop Portable Computers',
                'meta_description' => 'Browse laptops for work, gaming, and everyday computing from top brands.',
            ],
            [
                'name' => 'Desktops',
                'description' => 'Powerful desktop systems built for productivity and gaming.',
                'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=900&q=80',
                'icon' => 'desktop',
                'meta_title' => 'Desktop Computers - Towers & All-in-Ones',
                'meta_description' => 'Shop desktop towers and all-in-one PCs for home, office, and gaming setups.',
            ],
            [
                'name' => 'Monitors',
                'description' => 'Sharp displays for immersive work and entertainment.',
                'image' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=900&q=80',
                'icon' => 'monitor',
                'meta_title' => 'Monitors - HD, 4K & Ultrawide Displays',
                'meta_description' => 'Find the right monitor size and resolution for gaming, design, or office work.',
            ],
            [
                'name' => 'Keyboards',
                'description' => 'Responsive keyboards for typing, gaming, and creative workflows.',
                'image' => 'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?auto=format&fit=crop&w=900&q=80',
                'icon' => 'keyboard',
                'meta_title' => 'Keyboards - Mechanical & Wireless',
                'meta_description' => 'Mechanical, membrane, and wireless keyboards for typing and gaming.',
            ],
            [
                'name' => 'Mice',
                'description' => 'Ergonomic precision tools for efficient navigation.',
                'image' => 'https://images.unsplash.com/photo-1527814050087-3793815479db?auto=format&fit=crop&w=900&q=80',
                'icon' => 'mouse',
                'meta_title' => 'Computer Mice - Wired & Wireless',
                'meta_description' => 'Ergonomic and gaming mice with precision tracking and programmable buttons.',
            ],
            [
                'name' => 'Storage',
                'description' => 'Fast SSDs and reliable drives for every workflow.',
                'image' => 'https://images.unsplash.com/photo-1600347523505-8ba5c50d8f0f?auto=format&fit=crop&w=900&q=80',
                'icon' => 'database',
                'meta_title' => 'Storage - SSDs, HDDs & Flash Drives',
                'meta_description' => 'Internal and external drives, NVMe SSDs, and flash storage for any need.',
            ],
            [
                'name' => 'Networking',
                'description' => 'Reliable connectivity solutions for homes and offices.',
                'image' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?auto=format&fit=crop&w=900&q=80',
                'icon' => 'wifi',
                'meta_title' => 'Networking - Routers & Mesh WiFi',
                'meta_description' => 'Routers, mesh WiFi systems, and networking gear for fast, stable connections.',
            ],
            [
                'name' => 'Audio',
                'description' => 'Immersive sound gear for music, calls, and gaming.',
                'image' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=900&q=80',
                'icon' => 'headphones',
                'meta_title' => 'Audio - Headphones & Speakers',
                'meta_description' => 'Headphones, headsets, and speakers for music, calls, and gaming.',
            ],
            [
                'name' => 'Accessories',
                'description' => 'Everyday essentials that complete your setup.',
                'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80',
                'icon' => 'tool',
                'meta_title' => 'Computer Accessories & Peripherals',
                'meta_description' => 'Hubs, cables, stands, and other accessories to complete your desk setup.',
            ],
            [
                'name' => 'Gaming',
                'description' => 'High-performance gear designed for competitive play.',
                'image' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=900&q=80',
                'icon' => 'gamepad',
                'meta_title' => 'Gaming Gear - PCs, Peripherals & More',
                'meta_description' => 'Gaming PCs, cases, and peripherals for competitive and casual players.',
            ],
            [
                'name' => 'Smartphones',
                'description' => 'Feature-packed phones for productivity and photography.',
                'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80',
                'icon' => 'smartphone',
                'meta_title' => 'Smartphones - Latest Mobile Phones',
                'meta_description' => 'Shop the latest smartphones with top cameras, displays, and performance.',
            ],
            [
                'name' => 'Tablets',
                'description' => 'Lightweight tablets for browsing, drawing, and entertainment.',
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=900&q=80',
                'icon' => 'tablet',
                'meta_title' => 'Tablets - iPads & Android Tablets',
                'meta_description' => 'Tablets for reading, drawing, streaming, and light productivity on the go.',
            ],
            [
                'name' => 'Printers & Scanners',
                'description' => 'Home and office printing, scanning, and copying devices.',
                'image' => 'https://images.unsplash.com/photo-1612815154858-60aa4c59eaa6?auto=format&fit=crop&w=900&q=80',
                'icon' => 'printer',
                'meta_title' => 'Printers & Scanners - Home & Office',
                'meta_description' => 'Inkjet and laser printers, all-in-ones, and scanners for home or office.',
            ],
            [
                'name' => 'Cameras',
                'description' => 'Digital cameras and webcams for creators and streamers.',
                'image' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=80',
                'icon' => 'camera',
                'meta_title' => 'Cameras & Webcams',
                'meta_description' => 'Digital cameras, action cams, and webcams for content creation and calls.',
            ],
            [
                'name' => 'Wearables',
                'description' => 'Smartwatches and fitness trackers to stay connected on the move.',
                'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=80',
                'icon' => 'watch',
                'meta_title' => 'Wearables - Smartwatches & Fitness Trackers',
                'meta_description' => 'Smartwatches and fitness bands that track health, notifications, and more.',
            ],
            [
                'name' => 'Software & Licenses',
                'description' => 'Operating systems, productivity suites, and security software.',
                'image' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&w=900&q=80',
                'icon' => 'disc',
                'meta_title' => 'Software & Digital Licenses',
                'meta_description' => 'Genuine software licenses for operating systems, office suites, and security.',
            ],
            [
                'name' => 'Power & Charging',
                'description' => 'Chargers, power banks, and surge protectors to keep devices running.',
                'image' => 'https://images.unsplash.com/photo-1585338667683-5ff0b3f2b6c0?auto=format&fit=crop&w=900&q=80',
                'icon' => 'battery-charging',
                'meta_title' => 'Power & Charging Accessories',
                'meta_description' => 'Chargers, power banks, cables, and surge protectors for every device.',
            ],
            [
                'name' => 'Smart Home',
                'description' => 'Connected devices for lighting, security, and home automation.',
                'image' => 'https://images.unsplash.com/photo-1558002038-1055907df827?auto=format&fit=crop&w=900&q=80',
                'icon' => 'home',
                'meta_title' => 'Smart Home Devices',
                'meta_description' => 'Smart plugs, cameras, lighting, and hubs to automate your home.',
            ],
            [
                'name' => 'Graphics Cards',
                'description' => 'High-performance GPUs for gaming, rendering, and AI workloads.',
                'image' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?auto=format&fit=crop&w=900&q=80',
                'icon' => 'cpu',
                'meta_title' => 'Graphics Cards (GPUs)',
                'meta_description' => 'Discrete graphics cards for gaming, content creation, and workstation use.',
            ],
            [
                'name' => 'Office Furniture',
                'description' => 'Desks, chairs, and organizers designed for long work sessions.',
                'image' => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?auto=format&fit=crop&w=900&q=80',
                'icon' => 'armchair',
                'meta_title' => 'Office Furniture - Desks & Chairs',
                'meta_description' => 'Ergonomic desks, chairs, and storage to build a comfortable workspace.',
            ],
        ];

        foreach ($categories as $index => $category) {
            Category::firstOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'image' => $category['image'],
                    'icon' => $category['icon'],
                    'meta_title' => $category['meta_title'],
                    'meta_description' => $category['meta_description'],
                    'is_active' => true,
                    'is_featured' => $index < 3,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
