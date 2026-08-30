<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Seed 10 homepage carousel slides for the admin "Content: Homepage Slides"
     * screen.
     *
     * The set deliberately covers every state that screen can render:
     * active / scheduled / expired / draft, single-image and multi-frame
     * slides, and one slide with no image at all so the gradient fallback has
     * something to show.
     *
     * Frames beyond the first live in `meta`, matching how the admin UI splits
     * a slide: `image_desktop` is the cover the storefront reads, and
     * `meta.frames` carries the rest of the sequence along with its playback
     * settings. See `src/services/slides.js` in the frontend.
     *
     * Idempotent: re-running updates the existing row for a title rather than
     * stacking duplicates.
     */
    public function run(): void
    {
        foreach ($this->slides() as $index => $slide) {
            $frames = $slide['frames'];

            Banner::updateOrCreate(
                [
                    'title' => $slide['title'],
                    'position' => 'homepage',
                ],
                [
                    'subtitle' => $slide['subtitle'],
                    'image_desktop' => $frames[0] ?? null,
                    // The admin editor has no mobile-specific control: frame 2
                    // means "next frame", not "the phone version".
                    'image_mobile' => null,
                    'button_text' => $slide['button_text'],
                    'button_url' => $slide['button_url'],
                    'is_active' => $slide['is_active'],
                    'sort_order' => $index + 1,
                    'starts_at' => $slide['starts_at'],
                    'ends_at' => $slide['ends_at'],
                    'meta' => [
                        'frames' => array_slice($frames, 1),
                        'durationMs' => $slide['duration_ms'],
                        'transition' => $slide['transition'],
                        'gradient' => $slide['gradient'],
                    ],
                ]
            );
        }
    }

    /**
     * Deterministic 1600x700 placeholders — the size the admin form recommends.
     * Seeded by name so a given slide keeps the same photo across re-runs.
     */
    private function frame(string $seed): string
    {
        return "https://picsum.photos/seed/{$seed}/1600/700";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slides(): array
    {
        return [
            [
                'title' => 'Ultimate Gaming Setup 2026',
                'subtitle' => 'Build your dream PC with the RTX 50-series',
                'frames' => [$this->frame('bekie-gaming-1'), $this->frame('bekie-gaming-2'), $this->frame('bekie-gaming-3')],
                'button_text' => 'Shop Now',
                'button_url' => '/categories/graphics-cards',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 5000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #1b2a4a 0%, #6d28d9 100%)',
            ],
            [
                'title' => 'Student Laptop Deals',
                'subtitle' => 'Up to 20% off MacBooks and ThinkPads',
                'frames' => [$this->frame('bekie-laptop-1')],
                'button_text' => 'View Deals',
                'button_url' => '/categories/laptop',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #d9c7a8 0%, #8a6f4d 100%)',
            ],
            [
                'title' => 'Custom Water Cooling Kits',
                'subtitle' => 'Take your thermal performance to the next level',
                'frames' => [$this->frame('bekie-cooling-1'), $this->frame('bekie-cooling-2')],
                'button_text' => 'Learn More',
                'button_url' => '/categories/cpu-cooler',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 2000,
                'transition' => 'cut',
                'gradient' => 'linear-gradient(135deg, #7c1f9e 0%, #e0218a 100%)',
            ],
            [
                'title' => 'Ultrawide Monitor Upgrade',
                'subtitle' => '49-inch curved panels now in stock',
                'frames' => [$this->frame('bekie-monitor-1')],
                'button_text' => 'Browse Monitors',
                'button_url' => '/categories/gaming-monitor',
                'is_active' => true,
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->addDays(30),
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #0f766e 0%, #134e4a 100%)',
            ],
            [
                'title' => 'Build Your Own PC',
                'subtitle' => 'Pick every part, we assemble and test it free',
                'frames' => [
                    $this->frame('bekie-build-1'),
                    $this->frame('bekie-build-2'),
                    $this->frame('bekie-build-3'),
                    $this->frame('bekie-build-4'),
                ],
                'button_text' => 'Start Building',
                'button_url' => '/build',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 2000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #1e3a8a 0%, #0ea5e9 100%)',
            ],
            [
                // No images at all: the admin list and preview fall back to the
                // gradient, which nothing else in this set exercises.
                'title' => 'Free Shipping Over $99',
                'subtitle' => 'Nationwide delivery on every order this month',
                'frames' => [],
                'button_text' => 'See Terms',
                'button_url' => '/pages/shipping',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #b45309 0%, #f59e0b 100%)',
            ],
            [
                // Scheduled: active, but its window has not opened yet.
                'title' => 'Back to School Bundle',
                'subtitle' => 'Laptop, mouse and backpack from $699',
                'frames' => [$this->frame('bekie-school-1'), $this->frame('bekie-school-2')],
                'button_text' => 'Get the Bundle',
                'button_url' => '/promotions/back-to-school',
                'is_active' => true,
                'starts_at' => now()->addDays(14),
                'ends_at' => now()->addDays(45),
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #166534 0%, #4ade80 100%)',
            ],
            [
                // Scheduled, further out.
                'title' => 'Black Friday Mega Sale',
                'subtitle' => 'Doorbusters on GPUs, SSDs and monitors',
                'frames' => [$this->frame('bekie-blackfriday-1'), $this->frame('bekie-blackfriday-2'), $this->frame('bekie-blackfriday-3')],
                'button_text' => 'Preview Deals',
                'button_url' => '/promotions/black-friday',
                'is_active' => true,
                'starts_at' => now()->addDays(60),
                'ends_at' => now()->addDays(67),
                'duration_ms' => 5000,
                'transition' => 'cut',
                'gradient' => 'linear-gradient(135deg, #111827 0%, #b91c1c 100%)',
            ],
            [
                // Expired: active, but its window has already closed.
                'title' => 'Summer Clearance',
                'subtitle' => 'Everything must go - 50% off last-gen parts',
                'frames' => [$this->frame('bekie-clearance-1')],
                'button_text' => 'Clearance',
                'button_url' => '/promotions/summer-clearance',
                'is_active' => true,
                'starts_at' => now()->subDays(60),
                'ends_at' => now()->subDays(20),
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #b08968 0%, #7f5539 100%)',
            ],
            [
                // Draft: switched off entirely, dates irrelevant.
                'title' => 'Mechanical Keyboard Week',
                'subtitle' => 'Hot-swappable switches and custom keycaps',
                'frames' => [$this->frame('bekie-keyboard-1'), $this->frame('bekie-keyboard-2')],
                'button_text' => 'Shop Keyboards',
                'button_url' => '/categories/keyboard',
                'is_active' => false,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 2000,
                'transition' => 'cut',
                'gradient' => 'linear-gradient(135deg, #312e81 0%, #6366f1 100%)',
            ],
        ];
    }
}
