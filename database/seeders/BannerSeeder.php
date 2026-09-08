<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Seed 20 homepage carousel slides for the admin "Content: Homepage Slides"
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
     * Images are keyword-matched (LoremFlickr) so each slide's cover actually
     * looks like the computer-shop product it promotes, rather than a random
     * photo. `lock` pins a deterministic photo per keyword+variant so re-runs
     * don't shuffle the pictures.
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
     * Deterministic 1600x700 computer-shop photo — the size the admin form
     * recommends. `$keyword` steers the subject (comma-separated LoremFlickr
     * tags), `$variant` picks a different photo for the same keyword so a
     * multi-frame slide doesn't repeat the same shot.
     */
    private function frame(string $keyword, int $variant = 1): string
    {
        $lock = crc32($keyword.'-'.$variant) % 100000;
        $tags = str_replace(' ', '', $keyword);

        return "https://loremflickr.com/1600/700/{$tags}?lock={$lock}";
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
                'frames' => [$this->frame('gaming,pc,rgb', 1), $this->frame('gaming,pc,rgb', 2), $this->frame('gaming,pc,rgb', 3)],
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
                'frames' => [$this->frame('laptop,macbook', 1)],
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
                'frames' => [$this->frame('watercooling,pc', 1), $this->frame('watercooling,pc', 2)],
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
                'frames' => [$this->frame('monitor,ultrawide', 1)],
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
                    $this->frame('pcbuild,computer', 1),
                    $this->frame('pcbuild,computer', 2),
                    $this->frame('pcbuild,computer', 3),
                    $this->frame('pcbuild,computer', 4),
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
                'frames' => [$this->frame('laptop,backpack', 1), $this->frame('laptop,backpack', 2)],
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
                'frames' => [$this->frame('graphicscard,gpu', 1), $this->frame('graphicscard,gpu', 2), $this->frame('graphicscard,gpu', 3)],
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
                'frames' => [$this->frame('computerparts,pc', 1)],
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
                'frames' => [$this->frame('mechanicalkeyboard', 1), $this->frame('mechanicalkeyboard', 2)],
                'button_text' => 'Shop Keyboards',
                'button_url' => '/categories/keyboard',
                'is_active' => false,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 2000,
                'transition' => 'cut',
                'gradient' => 'linear-gradient(135deg, #312e81 0%, #6366f1 100%)',
            ],
            [
                'title' => 'Wireless Mouse Roundup',
                'subtitle' => 'Precision, comfort, and weeks of battery life',
                'frames' => [$this->frame('wirelessmouse', 1), $this->frame('wirelessmouse', 2)],
                'button_text' => 'Shop Mice',
                'button_url' => '/categories/mice',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #334155 0%, #64748b 100%)',
            ],
            [
                'title' => 'Streaming & Webcam Gear',
                'subtitle' => 'Go live in 1080p with studio-quality audio',
                'frames' => [$this->frame('webcam,streaming', 1)],
                'button_text' => 'Shop Webcams',
                'button_url' => '/categories/webcams',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #9d174d 0%, #db2777 100%)',
            ],
            [
                'title' => 'SSD Storage Upgrade',
                'subtitle' => 'NVMe drives up to 4TB, prices slashed',
                'frames' => [$this->frame('ssd,harddrive', 1), $this->frame('ssd,harddrive', 2)],
                'button_text' => 'Shop Storage',
                'button_url' => '/categories/storage',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 2500,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #0e7490 0%, #22d3ee 100%)',
            ],
            [
                'title' => 'Graphics Card Restock',
                'subtitle' => 'RTX and Radeon cards back in stock today',
                'frames' => [$this->frame('graphicscard', 1)],
                'button_text' => 'Check Stock',
                'button_url' => '/categories/graphics-cards',
                'is_active' => true,
                'starts_at' => now()->subDays(2),
                'ends_at' => now()->addDays(10),
                'duration_ms' => 3000,
                'transition' => 'cut',
                'gradient' => 'linear-gradient(135deg, #14532d 0%, #16a34a 100%)',
            ],
            [
                'title' => 'Motherboard Bundle Deals',
                'subtitle' => 'Save more when you pair board, CPU and RAM',
                'frames' => [$this->frame('motherboard,pc', 1), $this->frame('motherboard,pc', 2)],
                'button_text' => 'View Bundles',
                'button_url' => '/categories/motherboards',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #78350f 0%, #d97706 100%)',
            ],
            [
                'title' => 'Gaming Headset Sale',
                'subtitle' => '7.1 surround sound, up to 40% off',
                'frames' => [$this->frame('gamingheadset', 1)],
                'button_text' => 'Shop Headsets',
                'button_url' => '/categories/headsets',
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%)',
            ],
            [
                'title' => 'Wi-Fi 7 Router Launch',
                'subtitle' => 'The fastest home network we have ever sold',
                'frames' => [$this->frame('router,networking', 1)],
                'button_text' => 'Learn More',
                'button_url' => '/categories/networking',
                'is_active' => true,
                'starts_at' => now()->addDays(7),
                'ends_at' => now()->addDays(37),
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #1e293b 0%, #3b82f6 100%)',
            ],
            [
                // Draft: new category, still being reviewed before launch.
                'title' => 'Security Software Bundle',
                'subtitle' => 'Antivirus, VPN and backup for every device',
                'frames' => [],
                'button_text' => 'Shop Software',
                'button_url' => '/categories/software',
                'is_active' => false,
                'starts_at' => null,
                'ends_at' => null,
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #0f172a 0%, #1e40af 100%)',
            ],
            [
                'title' => 'Refurbished PC Clearance',
                'subtitle' => 'Certified refurbished desktops from $349',
                'frames' => [$this->frame('refurbished,computer', 1), $this->frame('refurbished,computer', 2)],
                'button_text' => 'Shop Refurbished',
                'button_url' => '/categories/desktops',
                'is_active' => true,
                'starts_at' => now()->subDays(90),
                'ends_at' => now()->subDays(45),
                'duration_ms' => 3000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #57534e 0%, #a8a29e 100%)',
            ],
            [
                'title' => 'VR Headset Launch',
                'subtitle' => 'Step into the next generation of gaming',
                'frames' => [$this->frame('vr,headset', 1), $this->frame('vr,headset', 2), $this->frame('vr,headset', 3)],
                'button_text' => 'Preorder Now',
                'button_url' => '/categories/vr',
                'is_active' => true,
                'starts_at' => now()->addDays(21),
                'ends_at' => now()->addDays(51),
                'duration_ms' => 4000,
                'transition' => 'fade',
                'gradient' => 'linear-gradient(135deg, #581c87 0%, #a855f7 100%)',
            ],
        ];
    }
}
