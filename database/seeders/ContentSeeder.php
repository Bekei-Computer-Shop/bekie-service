<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContentItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the two "Content" admin screens for a computer-shop storefront:
 *
 *  - type `page` : the website's standing pages (About, Warranty, Shipping…)
 *                  managed on the "Content: Pages" screen.
 *  - type `news` : the mini-blog — launches, guides, promos and events —
 *                  managed on the "Content: News" screen. News rows also carry
 *                  a `category` and a cover `image_url`.
 *
 * 20 of each. Statuses are spread across draft / published / archived so both
 * list screens have something to show under every filter. Idempotent:
 * re-running updates the row for a (type, title) pair instead of duplicating.
 */
class ContentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $author = User::where('email', 'admin@example.com')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $author) {
            $this->command?->warn('ContentSeeder skipped: no users to attribute content to.');

            return;
        }

        foreach ($this->pages() as $index => $page) {
            ContentItem::updateOrCreate(
                ['type' => 'page', 'title' => $page['title']],
                [
                    'body' => $page['body'],
                    'category' => null,
                    'image_url' => null,
                    'status' => $page['status'],
                    'author_id' => $author->id,
                    'published_at' => $page['status'] === 'published'
                        ? now()->subDays(120 - $index * 3)
                        : null,
                ]
            );
        }

        foreach ($this->news() as $index => $article) {
            ContentItem::updateOrCreate(
                ['type' => 'news', 'title' => $article['title']],
                [
                    'body' => $article['body'],
                    'category' => $article['category'],
                    'image_url' => $this->cover($article['seed']),
                    'status' => $article['status'],
                    'author_id' => $author->id,
                    'published_at' => $article['status'] === 'published'
                        ? now()->subDays(90 - $index * 4)
                        : null,
                ]
            );
        }

        $this->command?->info('Seeded 20 pages and 20 news articles for the computer shop.');
    }

    /**
     * Deterministic 1200x630 cover placeholder — the size the news form
     * recommends. Seeded by name so an article keeps its image across re-runs.
     */
    private function cover(string $seed): string
    {
        return "https://picsum.photos/seed/{$seed}/1200/630";
    }

    /**
     * @return array<int, array{title: string, body: string, status: string}>
     */
    private function pages(): array
    {
        $p = fn (string ...$paras): string => implode("\n\n", $paras);

        return [
            [
                'title' => 'About BackieDeal Computers',
                'status' => 'published',
                'body' => $p(
                    'BackieDeal Computers has been building, selling and servicing PCs since 2014. What started as a two-person repair bench is now a full storefront stocking components, laptops, peripherals and pre-built systems for gamers, creators and businesses.',
                    'Every custom build leaves our workshop only after a 24-hour stress test and a full thermal and stability check. We stand behind the parts we sell and the systems we assemble.'
                ),
            ],
            [
                'title' => 'Custom PC Build Service',
                'status' => 'published',
                'body' => $p(
                    'Pick your parts and our technicians assemble, cable-manage, install the operating system and stress-test the finished machine free of charge with any complete parts list bought in store.',
                    'Not sure where to start? Book a free 30-minute consultation and we will spec a build to your budget, whether that is a first gaming rig, a video-editing workstation or a silent office PC.'
                ),
            ],
            [
                'title' => 'Warranty & Returns Policy',
                'status' => 'published',
                'body' => $p(
                    'All new components carry the manufacturer warranty, ranging from 1 to 10 years depending on the part. Custom-built systems include a 2-year BackieDeal labour warranty on top of the individual part warranties.',
                    'Unopened items may be returned within 30 days for a full refund. Opened items in working condition are eligible for store credit less a 15% restocking fee. Software, custom-cut cables and clearance items are final sale.'
                ),
            ],
            [
                'title' => 'Shipping & Delivery Information',
                'status' => 'published',
                'body' => $p(
                    'Orders over $99 ship free within the country. Standard delivery takes 2 to 4 business days; express next-day delivery is available at checkout.',
                    'Fully assembled desktop systems ship double-boxed with custom foam bracing to protect the GPU and cooler in transit. Local customers can also choose free in-store pickup, usually ready within two hours.'
                ),
            ],
            [
                'title' => 'Trade-In & Upgrade Program',
                'status' => 'published',
                'body' => $p(
                    'Bring in your old graphics card, CPU, or complete system for an on-the-spot valuation. Trade-in credit can be applied directly to a new purchase or an upgrade service.',
                    'We accept working hardware up to two generations old. Non-working parts may still have recycling value — ask at the counter.'
                ),
            ],
            [
                'title' => 'Financing Options',
                'status' => 'published',
                'body' => $p(
                    'Spread the cost of a new system over 6, 12 or 24 months with our financing partner. Applications take a few minutes and an instant decision is given at checkout, online or in store.',
                    '0% interest is available on approved credit for 6-month plans on purchases over $750.'
                ),
            ],
            [
                'title' => 'Business & Education Accounts',
                'status' => 'published',
                'body' => $p(
                    'Registered businesses and schools get access to volume pricing, net-30 invoicing, and a dedicated account manager for quotes and fleet rollouts.',
                    'We handle imaging, asset tagging and on-site installation for orders of ten machines or more.'
                ),
            ],
            [
                'title' => 'Repair & Diagnostics Service',
                'status' => 'published',
                'body' => $p(
                    'Our bench technicians diagnose hardware and software faults on any desktop or laptop, regardless of where it was bought. Diagnostics are $35, credited toward the repair if you proceed.',
                    'Common jobs — thermal paste replacement, fan swaps, OS reinstalls, data recovery and virus removal — are usually completed within 48 hours.'
                ),
            ],
            [
                'title' => 'Privacy Policy',
                'status' => 'published',
                'body' => $p(
                    'We collect only the information needed to process your orders, provide warranty support and, if you opt in, send you occasional news. We never sell customer data.',
                    'Payment details are handled by our PCI-compliant payment processor and are not stored on our servers.'
                ),
            ],
            [
                'title' => 'Terms & Conditions',
                'status' => 'published',
                'body' => $p(
                    'By placing an order you agree to our pricing, warranty, returns and shipping terms as published on this website. Prices are subject to change and stock is not guaranteed until an order is confirmed.',
                    'Component compatibility is the customer\'s responsibility unless the parts list was specified or approved by BackieDeal staff.'
                ),
            ],
            [
                'title' => 'Price Match Promise',
                'status' => 'published',
                'body' => $p(
                    'Find a lower advertised price on an identical in-stock item from a local competitor and we will match it. Bring the advert or a link to the counter, or contact us before ordering online.',
                    'The promise covers new, sealed products from authorised retailers. It excludes marketplace sellers, clearance, open-box and bundle pricing.'
                ),
            ],
            [
                'title' => 'Loyalty Rewards Club',
                'status' => 'published',
                'body' => $p(
                    'Earn one point for every dollar spent. Points convert to store credit at 100 points = $5 and can be redeemed against parts, peripherals or workshop services.',
                    'Members also get early access to sales and a free annual PC health check.'
                ),
            ],
            [
                'title' => 'Frequently Asked Questions',
                'status' => 'published',
                'body' => $p(
                    'Answers to the questions we hear most: build lead times, warranty transfers, whether we install customer-supplied parts, BIOS update support, and how trade-in valuations work.',
                    'If your question is not covered here, contact the store and a technician will get back to you the same day.'
                ),
            ],
            [
                'title' => 'Contact & Store Hours',
                'status' => 'published',
                'body' => $p(
                    'The store and workshop are open Monday to Friday 9am to 7pm, Saturday 10am to 5pm, and closed Sunday. Phone lines and live chat follow the same hours.',
                    'For warranty claims please have your order number ready to speed things up.'
                ),
            ],
            [
                'title' => 'Careers at BackieDeal',
                'status' => 'draft',
                'body' => $p(
                    'We are always interested in hearing from experienced PC builders, bench technicians and retail staff who know their hardware.',
                    'Send a short introduction and your CV to careers@backiedeal.example and tell us about the last system you built.'
                ),
            ],
            [
                'title' => 'Sustainability & E-Waste Recycling',
                'status' => 'draft',
                'body' => $p(
                    'Drop off old electronics — cards, drives, cables, batteries — at our recycling station any time during opening hours, free of charge.',
                    'We partner with a certified processor to make sure nothing usable goes to landfill and data-bearing devices are wiped or shredded.'
                ),
            ],
            [
                'title' => 'Gift Cards',
                'status' => 'draft',
                'body' => $p(
                    'Physical and digital gift cards are available from $25 to $500 and never expire. They can be spent on anything in store, online or in the workshop.',
                    'A gift card is the safe choice when you are not sure which graphics card someone is holding out for.'
                ),
            ],
            [
                'title' => 'Affiliate Program',
                'status' => 'draft',
                'body' => $p(
                    'Content creators and system reviewers can earn commission on referred sales. We provide tracked links, a product feed and build-list widgets.',
                    'Applications are reviewed weekly. Tell us where your audience is and what you cover.'
                ),
            ],
            [
                'title' => 'COVID-19 Store Measures',
                'status' => 'archived',
                'body' => $p(
                    'This notice covered the additional cleaning, distancing and contactless-pickup measures that were in place at the store during 2020 and 2021.',
                    'These measures have since been lifted. The page is kept for reference.'
                ),
            ],
            [
                'title' => 'Old Store Location (Riverside Mall)',
                'status' => 'archived',
                'body' => $p(
                    'Until 2023 our storefront was located in Unit 14 of the Riverside Mall. This page carried directions and parking information for that address.',
                    'We have since moved to the larger workshop-and-showroom on Tech Avenue. Please update your bookmarks.'
                ),
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, category: string, seed: string, status: string, body: string}>
     */
    private function news(): array
    {
        $p = fn (string ...$paras): string => implode("\n\n", $paras);

        return [
            [
                'title' => 'RTX 50-Series Graphics Cards Now In Stock',
                'category' => 'Product News',
                'seed' => 'backiedeal-rtx50',
                'status' => 'published',
                'body' => $p(
                    'The next generation of NVIDIA GeForce RTX cards has landed at BackieDeal. We have launch stock of the 5070, 5080 and 5090 across Founders Edition and partner models from ASUS, MSI and Gigabyte.',
                    'Add one to a custom build order this week and assembly is bumped to the front of the queue.'
                ),
            ],
            [
                'title' => 'How to Build Your First Gaming PC in 2026',
                'category' => 'Guides',
                'seed' => 'backiedeal-first-build',
                'status' => 'published',
                'body' => $p(
                    'A start-to-finish walkthrough: choosing a balanced parts list, preparing the case, seating the CPU and cooler, installing memory and storage, cable management, first boot and BIOS setup.',
                    'Written for someone who has never picked up a screwdriver in a case before. Total build time is about two hours at a relaxed pace.'
                ),
            ],
            [
                'title' => 'Back-to-School Laptop Buying Guide',
                'category' => 'Guides',
                'seed' => 'backiedeal-school-laptops',
                'status' => 'published',
                'body' => $p(
                    'How much laptop does a student actually need? We break it down by course: general study, engineering and CAD, design and video, and computer science.',
                    'Our current picks range from a $549 all-day ultrabook to a $1,699 mobile workstation.'
                ),
            ],
            [
                'title' => 'DDR5 Prices Drop Again — Time to Upgrade',
                'category' => 'Product News',
                'seed' => 'backiedeal-ddr5',
                'status' => 'published',
                'body' => $p(
                    'DDR5 kits have fallen roughly 30% since the start of the year. A 32GB 6000MT/s kit that was $180 in January is now under $120.',
                    'If you built an early AM5 or 12th-gen Intel system on 16GB, this is a cheap and easy jump to 32GB or 64GB.'
                ),
            ],
            [
                'title' => 'Summer Cooling Clinic: Keep Your PC Quiet and Cool',
                'category' => 'Guides',
                'seed' => 'backiedeal-cooling',
                'status' => 'published',
                'body' => $p(
                    'Ambient temperatures climb in summer and so do component temperatures. This guide covers fan curves, case airflow layout, dust filtering, repasting intervals and when an AIO or air cooler upgrade is worth it.',
                    'Book a workshop cooling service and we will clean, repaste and re-tune fan curves in about an hour.'
                ),
            ],
            [
                'title' => 'New Assembly Workshop Now Open',
                'category' => 'Company',
                'seed' => 'backiedeal-workshop',
                'status' => 'published',
                'body' => $p(
                    'Our expanded workshop doubles bench capacity and adds a dedicated photo and testing area, so custom build turnaround is now 2 to 3 days instead of a week.',
                    'Come by for a look — the glass wall means you can watch builds in progress from the showroom floor.'
                ),
            ],
            [
                'title' => 'Free Shipping on All Orders Over $99',
                'category' => 'Promotions',
                'seed' => 'backiedeal-free-shipping',
                'status' => 'published',
                'body' => $p(
                    'For a limited time, every order over $99 ships free nationwide with no coupon needed — the discount is applied automatically at checkout.',
                    'Assembled desktop systems are included and ship with reinforced GPU bracing.'
                ),
            ],
            [
                'title' => 'PCIe 5.0 SSDs: Do You Actually Need One?',
                'category' => 'Reviews',
                'seed' => 'backiedeal-pcie5-ssd',
                'status' => 'published',
                'body' => $p(
                    'We tested three PCIe 5.0 drives against a fast Gen4 drive in game load times, project builds and large file copies.',
                    'The short version: real-world gains are small for gaming but meaningful for content workflows moving hundreds of gigabytes a day. The heatsink is not optional.'
                ),
            ],
            [
                'title' => 'Mechanical Keyboard Week — Switch Sampler In Store',
                'category' => 'Promotions',
                'seed' => 'backiedeal-keyboard-week',
                'status' => 'published',
                'body' => $p(
                    'Try before you buy: our switch-tester board is loaded with 18 different linear, tactile and clicky switches so you can feel the difference before committing.',
                    'Hot-swappable boards and keycap sets are 15% off all week.'
                ),
            ],
            [
                'title' => 'Customer Build Spotlight: A Silent 4K Editing Rig',
                'category' => 'Company',
                'seed' => 'backiedeal-build-spotlight',
                'status' => 'published',
                'body' => $p(
                    'A local videographer came to us wanting a machine that could scrub 4K timelines without sounding like a hairdryer. The result: a Ryzen 9 with 96GB of RAM, a large air cooler and noise-optimised fan curves.',
                    'Idle noise is barely audible and it never breaks 22 dBA under an export.'
                ),
            ],
            [
                'title' => 'Windows 11 24H2: What Changed and Should You Update',
                'category' => 'Guides',
                'seed' => 'backiedeal-win11-24h2',
                'status' => 'published',
                'body' => $p(
                    'The latest feature update brings a revised File Explorer, faster search and some driver-model changes that affect older peripherals.',
                    'We recommend waiting two weeks after release before updating a working system, and always taking a backup first.'
                ),
            ],
            [
                'title' => 'Trade In Your Old GPU for Store Credit',
                'category' => 'Promotions',
                'seed' => 'backiedeal-gpu-tradein',
                'status' => 'published',
                'body' => $p(
                    'Upgrading to a 50-series card? Bring your current GPU in for a valuation. This month we are adding a 10% bonus on trade-in credit toward any new graphics card.',
                    'Cards from the last two generations in working order qualify.'
                ),
            ],
            [
                'title' => 'Understanding PC Power Supplies and 80 PLUS Ratings',
                'category' => 'Guides',
                'seed' => 'backiedeal-psu-guide',
                'status' => 'published',
                'body' => $p(
                    'Wattage is only part of the story. This guide explains rails, efficiency ratings, the new ATX 3.1 spec and the 12V-2x6 connector, and how to size a PSU for a modern high-power GPU.',
                    'Rule of thumb: total system draw plus 30% headroom, from a reputable brand with a long warranty.'
                ),
            ],
            [
                'title' => 'Store Anniversary Sale — Three Days Only',
                'category' => 'Promotions',
                'seed' => 'backiedeal-anniversary',
                'status' => 'published',
                'body' => $p(
                    'We are turning twelve. To celebrate, everything in store is discounted for three days, with doorbuster pricing on select monitors, SSDs and pre-built systems while stock lasts.',
                    'Loyalty club members get first access one day early.'
                ),
            ],
            [
                'title' => 'Laptop vs Desktop for Programming Students',
                'category' => 'Guides',
                'seed' => 'backiedeal-laptop-vs-desktop',
                'status' => 'published',
                'body' => $p(
                    'Portability versus power and value. We look at what a computer-science workload really demands, when a laptop alone is enough, and when a cheap desktop plus a modest laptop beats one expensive machine.',
                    'For most students on a budget, 16GB of RAM and a fast SSD matter more than the CPU tier.'
                ),
            ],
            [
                'title' => 'Now an Authorised Service Centre for Three More Brands',
                'category' => 'Announcements',
                'seed' => 'backiedeal-service-centre',
                'status' => 'published',
                'body' => $p(
                    'BackieDeal is now an authorised warranty service partner for three additional laptop and peripheral brands, which means in-warranty repairs can be handled here instead of shipping your device away.',
                    'Bring proof of purchase and we will log the claim on the spot.'
                ),
            ],
            [
                'title' => 'Black Friday 2026: Early Deal Preview',
                'category' => 'Promotions',
                'seed' => 'backiedeal-black-friday',
                'status' => 'draft',
                'body' => $p(
                    'A preview of the doorbusters we are lining up for Black Friday week: GPU bundles, monitor markdowns, storage multi-buys and discounted assembly on custom builds.',
                    'This post is a draft — pricing is not final and will be confirmed closer to the date.'
                ),
            ],
            [
                'title' => 'Hands-On: Next-Gen CPU Coolers Compared',
                'category' => 'Reviews',
                'seed' => 'backiedeal-cooler-roundup',
                'status' => 'draft',
                'body' => $p(
                    'We are testing six new air and liquid coolers on a high-wattage CPU, measuring noise, thermals and installation friction.',
                    'Draft — results and photos still being finalised in the workshop.'
                ),
            ],
            [
                'title' => 'Holiday Opening Hours 2026',
                'category' => 'Announcements',
                'seed' => 'backiedeal-holiday-hours',
                'status' => 'draft',
                'body' => $p(
                    'Our planned store and workshop hours over the December holiday period, including the last order dates for guaranteed pre-holiday delivery and custom-build completion.',
                    'Draft — dates to be confirmed by management.'
                ),
            ],
            [
                'title' => 'RTX 40-Series Pre-Order Announcement (2022)',
                'category' => 'Product News',
                'seed' => 'backiedeal-rtx40-archive',
                'status' => 'archived',
                'body' => $p(
                    'This post announced pre-orders for the RTX 40-series when it launched in 2022.',
                    'Kept for reference. For current graphics card stock see the latest product news.'
                ),
            ],
        ];
    }
}
