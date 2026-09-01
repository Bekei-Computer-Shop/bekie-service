<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Create a clean, realistic default category list for the storefront.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Laptops',
                'description' => 'Portable performance for work, study, and travel.',
                'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=80',
                'icon' => 'laptop',
            ],
            [
                'name' => 'Desktops',
                'description' => 'Powerful desktop systems built for productivity and gaming.',
                'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=900&q=80',
                'icon' => 'desktop',
            ],
            [
                'name' => 'Monitors',
                'description' => 'Sharp displays for immersive work and entertainment.',
                'image' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=900&q=80',
                'icon' => 'monitor',
            ],
            [
                'name' => 'Keyboards',
                'description' => 'Responsive keyboards for typing, gaming, and creative workflows.',
                'image' => 'https://images.unsplash.com/photo-1511467687858-23d96c32e4ae?auto=format&fit=crop&w=900&q=80',
                'icon' => 'keyboard',
            ],
            [
                'name' => 'Mice',
                'description' => 'Ergonomic precision tools for efficient navigation.',
                'image' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=900&q=80',
                'icon' => 'mouse',
            ],
            [
                'name' => 'Storage',
                'description' => 'Fast SSDs and reliable drives for every workflow.',
                'image' => 'https://images.unsplash.com/photo-1583394838336-acd977736f90?auto=format&fit=crop&w=900&q=80',
                'icon' => 'database',
            ],
            [
                'name' => 'Networking',
                'description' => 'Reliable connectivity solutions for homes and offices.',
                'image' => 'https://images.unsplash.com/photo-1558494949cc5c8f2f0f9d2d0?auto=format&fit=crop&w=900&q=80',
                'icon' => 'wifi',
            ],
            [
                'name' => 'Audio',
                'description' => 'Immersive sound gear for music, calls, and gaming.',
                'image' => 'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=900&q=80',
                'icon' => 'headphones',
            ],
            [
                'name' => 'Accessories',
                'description' => 'Everyday essentials that complete your setup.',
                'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80',
                'icon' => 'tool',
            ],
            [
                'name' => 'Gaming',
                'description' => 'High-performance gear designed for competitive play.',
                'image' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=900&q=80',
                'icon' => 'gamepad',
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
                    'is_active' => true,
                    'is_featured' => $index < 3,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
