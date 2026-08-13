<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ComputerShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Brands
        $this->command->getOutput()->progressStart(5);
        $this->command->info(' Seeding Brands...');
        $brandNames = ['Apple', 'Dell', 'HP', 'Lenovo', 'Asus', 'Logitech', 'Samsung', 'Corsair', 'Nvidia', 'AMD', 'Razer', 'SteelSeries'];
        $brandsData = collect($brandNames)->map(fn ($name) => ['name' => $name, 'slug' => Str::slug($name)])->all();
        Brand::insert($brandsData);
        $brands = Brand::all()->keyBy('name');
        $this->command->getOutput()->progressAdvance();

        // 2. Create Categories and Sub-categories
        $this->command->info('Seeding Categories...');
        $categories = collect([
            'Laptops' => ['Gaming Laptops', 'Ultrabooks', 'Workstation Laptops'],
            'Desktops' => ['Gaming PCs', 'All-in-One PCs'],
            'Monitors' => ['Gaming Monitors', '4K Monitors', 'Ultrawide Monitors'],
            'Peripherals' => ['Keyboards', 'Mice', 'Headsets', 'Webcams'],
            'Components' => ['CPUs', 'GPUs', 'RAM', 'Motherboards', 'Storage'],
        ]);

        $categoryModels = [];
        foreach ($categories as $parentName => $childNames) {
            $parent = Category::create(['name' => $parentName, 'slug' => Str::slug($parentName)]);
            $categoryModels[$parent->slug] = $parent;
            $children = [];
            foreach ($childNames as $childName) {
                $child = ['name' => $childName, 'slug' => Str::slug($childName), 'parent_id' => $parent->id];
                $children[] = $child;
            }
            Category::insert($children);
        }
        $allCategories = Category::all()->keyBy('slug');
        $this->command->getOutput()->progressAdvance();

        $laptops = $allCategories['laptops'];
        $desktops = $allCategories['desktops'];
        $mice = $allCategories['mice'];

        // 3. Create Products with Variants
        $this->command->info('Seeding Products and Variants...');

        // Product 1: MacBook Pro with variants
        $macbook = Product::create([
            'name' => 'MacBook Pro 14"',
            'slug' => 'macbook-pro-14',
            'description' => 'The ultimate pro laptop, supercharged by M3 Pro or M3 Max.',
            'short_description' => 'Powerful. Portable. Professional.',
            'price' => 1999.00, // Base price
            'sku' => 'MBP14-BASE',
            'stock' => 50,
            'category_id' => $allCategories['ultrabooks']->id,
            'brand_id' => $brands['Apple']->id,
            'is_featured' => true,
        ]);

        $macbook->variants()->createMany([
            [
                'name' => 'M3 Pro / 18GB RAM / 512GB SSD',
                'sku' => 'MBP14-M3P-18-512',
                'price' => 1999.00,
                'stock' => 20,
                'attributes' => ['Chip' => 'M3 Pro', 'RAM' => '18GB', 'Storage' => '512GB'],
            ],
            [
                'name' => 'M3 Pro / 18GB RAM / 1TB SSD',
                'sku' => 'MBP14-M3P-18-1T',
                'price' => 2199.00,
                'stock' => 15,
                'attributes' => ['Chip' => 'M3 Pro', 'RAM' => '18GB', 'Storage' => '1TB'],
            ],
            [
                'name' => 'M3 Max / 36GB RAM / 1TB SSD',
                'sku' => 'MBP14-M3M-36-1T',
                'price' => 3199.00,
                'stock' => 5,
                'attributes' => ['Chip' => 'M3 Max', 'RAM' => '36GB', 'Storage' => '1TB'],
            ],
        ]);

        // Product 2: Dell XPS Desktop (no variants)
        Product::create([
            'name' => 'Dell XPS Desktop',
            'slug' => 'dell-xps-desktop',
            'description' => 'A powerful and expandable desktop for creators and gamers.',
            'short_description' => 'Performance powerhouse.',
            'price' => 1499.00,
            'sku' => 'XPS-DT-i7-3060',
            'stock' => 30,
            'category_id' => $desktops->id,
            'brand_id' => $brands['Dell']->id,
        ]);

        // Product 3: Logitech MX Master 3S with variants
        $mxMaster = Product::create([
            'name' => 'Logitech MX Master 3S',
            'slug' => 'logitech-mx-master-3s',
            'description' => 'An iconic mouse, remastered for performance.',
            'short_description' => 'Precision and comfort.',
            'price' => 99.99,
            'sku' => 'MXM3S-BASE',
            'stock' => 100,
            'category_id' => $mice->id,
            'brand_id' => $brands['Logitech']->id,
            'is_featured' => true,
        ]);

        $mxMaster->variants()->createMany([
            [
                'name' => 'Graphite',
                'sku' => 'MXM3S-GRAPHITE',
                'price' => 99.99,
                'stock' => 60,
                'attributes' => ['Color' => 'Graphite'],
            ],
            [
                'name' => 'Pale Gray',
                'sku' => 'MXM3S-GRAY',
                'price' => 99.99,
                'stock' => 40,
                'attributes' => ['Color' => 'Pale Gray'],
            ],
        ]);
        $this->command->getOutput()->progressAdvance();

        // 4. Create more products using factories for variety
        $this->command->info('Seeding additional products using factories...');
        Product::factory(50)
            ->state(new Sequence(
                fn (Sequence $sequence) => [
                    'category_id' => $allCategories->where('parent_id', '!=', null)->random()->id,
                    'brand_id' => $brands->random()->id,
                ],
            ))
            ->create();
        $this->command->getOutput()->progressAdvance();

        // 5. Add variants to some of the factory-created products
        $this->command->info('Seeding additional variants...');
        $productsToGetVariants = Product::whereDoesntHave('variants')->inRandomOrder()->take(15)->get();

        foreach ($productsToGetVariants as $product) {
            $product->variants()->createMany([
                [
                    'name' => 'Standard',
                    'sku' => $product->sku.'-STD',
                    'price' => $product->price,
                    'stock' => $product->stock > 1 ? floor($product->stock / 2) : $product->stock,
                    'attributes' => ['Option' => 'Standard'],
                ],
                [
                    'name' => 'Premium',
                    'sku' => $product->sku.'-PREM',
                    'price' => $product->price * 1.2,
                    'stock' => $product->stock > 1 ? floor($product->stock / 2) : 0,
                    'attributes' => ['Option' => 'Premium'],
                ],
            ]);
        }
        $this->command->getOutput()->progressFinish();
    }
}
