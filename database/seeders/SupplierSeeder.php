<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Supplier;
use Database\Seeders\Support\DemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Demo suppliers shaped like a Phnom Penh computer shop's real vendor list:
 * local distributors for most brands, a few regional importers with longer
 * lead times, one inactive supplier kept for history, and one store default.
 *
 * Every company, person, phone number, email domain and tax ID below is
 * invented. That is why this is demo-only: it must never reach production.
 *
 * On top of the supplier rows it:
 *  - sets each product's default supplier from the brands a supplier
 *    distributes, only where none is set, so an admin's choice survives;
 *  - fills in supplier and unit cost on the opening-stock receipts that
 *    DemoCatalogSeeder writes, so stock history shows who each delivery came
 *    from. A product with no brand match falls back to the store default.
 *
 * Upserts by code across trashed rows, so re-running updates rather than
 * duplicates.
 *
 * Usage: php artisan db:seed --class=SupplierSeeder
 */
class SupplierSeeder extends Seeder
{
    /**
     * `brands` are the catalog brands a supplier is the usual source for. A
     * brand appears under one supplier only.
     */
    private const SUPPLIERS = [
        [
            'code' => 'SUP-001',
            'name' => 'Mekong Computer Distribution Co., Ltd.',
            'contact_person' => 'Sok Dara',
            'phone' => '+855 23 988 412',
            'email' => 'sales@mekongcomputer-dist.com.kh',
            'address' => 'No. 148, Street 271, Sangkat Tuol Tumpung II, Khan Chamkarmon, Phnom Penh',
            'tax_id' => 'L001-107004128',
            'notes' => "Main local distributor for storage, memory and accessories.\nPayment: Net 30. Lead time: 1-2 days. Free delivery in Phnom Penh over \$300.\nRMA: drop-off at their Street 271 service desk, 7-day turnaround.",
            'is_active' => true,
            'is_default' => true,
            'brands' => ['Kingston', 'Crucial', 'Samsung', 'Seagate', 'Western Digital', 'SanDisk', 'TeamGroup', 'Anker', 'UGREEN', 'TP-Link'],
        ],
        [
            'code' => 'SUP-002',
            'name' => 'Angkor IT Solutions Co., Ltd.',
            'contact_person' => 'Chan Sophea',
            'phone' => '+855 12 845 302',
            'email' => 'b2b@angkoritsolutions.com.kh',
            'address' => 'No. 36B, Preah Monivong Blvd (93), Sangkat Srah Chak, Khan Daun Penh, Phnom Penh',
            'tax_id' => 'L001-104552719',
            'notes' => "Authorised channel partner for laptops, desktops and monitors.\nPayment: 50% deposit, balance on delivery. Lead time: 3-5 days, 2-3 weeks for build-to-order.\nPrices quoted in USD, valid 7 days.",
            'is_active' => true,
            'is_default' => false,
            'brands' => ['ASUS', 'Acer', 'Lenovo', 'Dell', 'HP', 'AOC', 'BenQ', 'LG'],
        ],
        [
            'code' => 'SUP-003',
            'name' => 'Tonle Gaming Gear Trading',
            'contact_person' => 'Kim Rithy',
            'phone' => '+855 77 610 958',
            'email' => 'order@tonlegaminggear.com',
            'address' => 'No. 72, Street 214, Sangkat Boeung Raing, Khan Daun Penh, Phnom Penh',
            'tax_id' => 'M001-901238846',
            'notes' => "Peripherals and gaming accessories.\nPayment: Net 15. Lead time: 1-3 days.\nOrders before 2 PM are delivered the same day.",
            'is_active' => true,
            'is_default' => false,
            'brands' => ['Logitech', 'Razer', 'SteelSeries', 'HyperX', 'Keychron', 'Elgato', 'Corsair'],
        ],
        [
            'code' => 'SUP-004',
            'name' => 'Royal Components (Cambodia) Co., Ltd.',
            'contact_person' => 'Heng Sreymom',
            'phone' => '+855 23 216 774',
            'email' => 'purchasing@royalcomponents.com.kh',
            'address' => 'No. 9E0, Russian Federation Blvd (110), Sangkat Toek Laak I, Khan Toul Kork, Phnom Penh',
            'tax_id' => 'L001-108316045',
            'notes' => "CPUs, motherboards and graphics cards.\nPayment: Net 30 with a \$15,000 credit limit. Lead time: 2-4 days, new GPU launches by allocation only.\nRMA via the manufacturer; allow 2-3 weeks.",
            'is_active' => true,
            'is_default' => false,
            'brands' => ['AMD', 'Intel', 'NVIDIA', 'MSI', 'Gigabyte', 'ASRock', 'Sapphire', 'XFX', 'EVGA'],
        ],
        [
            'code' => 'SUP-005',
            'name' => 'Bayon Power & Cooling',
            'contact_person' => 'Ly Vannak',
            'phone' => '+855 15 330 627',
            'email' => 'vannak@bayonpowercooling.com',
            'address' => 'No. 211, Street 110, Sangkat Phsar Thmei III, Khan Daun Penh, Phnom Penh',
            'tax_id' => 'M001-902457130',
            'notes' => "Power supplies, cooling and PC cases.\nPayment: cash or ABA transfer on delivery. Lead time: 2-3 days.\nBulky items such as cases are delivered by van on Tuesdays and Fridays.",
            'is_active' => true,
            'is_default' => false,
            'brands' => ['Seasonic', 'be quiet!', 'Cooler Master', 'Noctua', 'Arctic', 'NZXT', 'Lian Li', 'Fractal Design'],
        ],
        [
            'code' => 'SUP-006',
            'name' => 'Chaophraya Network Distribution Co., Ltd.',
            'contact_person' => 'Kittipong Srisuk',
            'phone' => '+66 2 117 4580',
            'email' => 'export@chaophraya-netdist.co.th',
            'address' => '88/14 Soi Sukhumvit 71, Phra Khanong Nuea, Watthana, Bangkok 10110, Thailand',
            'tax_id' => '0105559137264',
            'notes' => "Regional importer for networking and memory brands with no local distributor.\nPayment: T/T in USD before shipping. Lead time: 10-14 days by road through Poipet.\nMinimum order \$1,000. Import duty and clearance are billed separately.",
            'is_active' => true,
            'is_default' => false,
            'brands' => ['Ubiquiti', 'G.Skill'],
        ],
        [
            'code' => 'SUP-007',
            'name' => 'Orchid Office Equipment Co., Ltd.',
            'contact_person' => 'Pich Sokunthea',
            'phone' => '+855 23 880 145',
            'email' => 'sales@orchidoffice.com.kh',
            'address' => 'No. 45, Street 163, Sangkat Olympic, Khan Boeung Keng Kang, Phnom Penh',
            'tax_id' => 'L001-105870392',
            'notes' => "Printers, scanners and consumables.\nPayment: Net 30. Lead time: 2-3 days.\nInk and toner on consignment, settled monthly.",
            'is_active' => true,
            'is_default' => false,
            'brands' => ['Epson', 'Canon', 'Brother'],
        ],
        [
            'code' => 'SUP-008',
            'name' => 'Saigon Audio Import JSC',
            'contact_person' => 'Nguyen Thi Thu Ha',
            'phone' => '+84 28 3930 6172',
            'email' => 'thuha.nguyen@saigonaudio-import.vn',
            'address' => '214 Nguyen Dinh Chieu, Vo Thi Sau Ward, District 3, Ho Chi Minh City, Vietnam',
            'tax_id' => '0315627481',
            'notes' => "Headphones and speakers, shipped through the Bavet border crossing.\nPayment: T/T in USD, 30% deposit. Lead time: 7-10 days.\nWarranty claims are handled locally by our store and credited on the next order.",
            'is_active' => true,
            'is_default' => false,
            'brands' => ['Sony', 'Bose', 'JBL', 'Sonos'],
        ],
        [
            'code' => 'SUP-009',
            'name' => 'Riverside Premium Devices Co., Ltd.',
            'contact_person' => 'Touch Bunthoeun',
            'phone' => '+855 92 404 518',
            'email' => 'reseller@riversidedevices.com.kh',
            'address' => 'Level 3, Unit 3-07, No. 66, Preah Norodom Blvd (41), Sangkat Chey Chumneah, Khan Daun Penh, Phnom Penh',
            'tax_id' => 'L001-106294557',
            'notes' => "Apple products for authorised resale.\nPayment: prepaid. Lead time: 3-7 days, depending on allocation.\nRetail price floors apply; check with the account manager before discounting.",
            'is_active' => true,
            'is_default' => false,
            'brands' => ['Apple'],
        ],
        [
            'code' => 'SUP-010',
            'name' => 'Shenzhen Huaxin Electronic Accessories Co., Ltd.',
            'contact_person' => 'Chen Wei',
            'phone' => '+86 755 8362 1945',
            'email' => 'chenwei@hx-accessories.cn',
            'address' => 'Room 1508, Block B, Huaqiang Plaza, Futian District, Shenzhen, Guangdong, China',
            'tax_id' => '91440300MA5F3K2X7Q',
            'notes' => "Unbranded cables, adapters and mounting hardware.\nPayment: T/T 30% deposit, 70% before shipping. Lead time: 3-4 weeks by sea to Sihanoukville.\nMOQ 500 pcs per SKU.",
            'is_active' => true,
            'is_default' => false,
            'brands' => [],
        ],
        [
            'code' => 'SUP-011',
            'name' => 'Kampot Tech Wholesale',
            'contact_person' => 'Mao Chantha',
            'phone' => '+855 33 932 117',
            'email' => 'kampottech.wholesale@gmail.com',
            'address' => 'Street 726, Kampong Bay, Kampot',
            'tax_id' => 'M001-903114268',
            'notes' => "No longer used: stopped trading in March 2026.\nKept so older stock receipts still show where they came from.",
            'is_active' => false,
            'is_default' => false,
            'brands' => [],
        ],
    ];

    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        $suppliers = $this->seedSuppliers();
        $linked = $this->linkProducts($suppliers);
        $receipts = $this->backfillOpeningStock();

        $this->command?->info(sprintf(
            'Suppliers: %d seeded, %d products given a default supplier, %d opening-stock receipts linked.',
            $suppliers->count(),
            $linked,
            $receipts,
        ));
    }

    /**
     * @return Collection<string, Supplier> keyed by code
     */
    private function seedSuppliers(): Collection
    {
        $seeded = collect();

        foreach (self::SUPPLIERS as $definition) {
            $attributes = collect($definition)->except('brands')->all();

            $supplier = Supplier::withTrashed()->firstOrNew(['code' => $definition['code']]);
            $supplier->fill($attributes)->save();
            if ($supplier->trashed()) {
                $supplier->restore();
            }

            $seeded->put($definition['code'], $supplier);
        }

        // One store default, whatever an earlier run or an admin left behind.
        $default = $seeded->first(fn (Supplier $supplier) => $supplier->is_default);
        Supplier::query()
            ->whereKeyNot($default->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);

        return $seeded;
    }

    /**
     * Gives a product its brand's supplier as its default, leaving products that
     * already have one alone.
     *
     * @param  Collection<string, Supplier>  $suppliers
     */
    private function linkProducts(Collection $suppliers): int
    {
        $brandIds = Brand::query()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);

        $linked = 0;
        foreach (self::SUPPLIERS as $definition) {
            $ids = collect($definition['brands'])
                ->map(fn (string $brand) => $brandIds->get(mb_strtolower($brand)))
                ->filter()
                ->values();

            if ($ids->isEmpty()) {
                continue;
            }

            $linked += Product::withTrashed()
                ->whereIn('brand_id', $ids)
                ->whereNull('default_supplier_id')
                ->update(['default_supplier_id' => $suppliers->get($definition['code'])->id]);
        }

        return $linked;
    }

    /**
     * Names the supplier and unit cost on DemoCatalogSeeder's opening receipts:
     * the product's default supplier, else the store default; the variant's cost
     * price, else the product's.
     */
    private function backfillOpeningStock(): int
    {
        $storeDefaultId = Supplier::query()->where('is_default', true)->value('id');
        $variantType = (new ProductVariant)->getMorphClass();
        $productType = (new Product)->getMorphClass();

        $updated = 0;

        StockMovement::query()
            ->where('movement_type', 'stock_in')
            ->where('reference', DemoData::STOCK_MOVEMENT_REFERENCE)
            ->whereNull('supplier_id')
            ->chunkById(200, function (Collection $movements) use ($storeDefaultId, $variantType, $productType, &$updated): void {
                $variants = ProductVariant::withTrashed()
                    ->with(['product' => fn ($query) => $query->withTrashed()])
                    ->whereIn('id', $movements->where('stockable_type', $variantType)->pluck('stockable_id'))
                    ->get()
                    ->keyBy(fn (ProductVariant $variant) => (string) $variant->id);

                $products = Product::withTrashed()
                    ->whereIn('id', $movements->where('stockable_type', $productType)->pluck('stockable_id'))
                    ->get()
                    ->keyBy(fn (Product $product) => (string) $product->id);

                foreach ($movements as $movement) {
                    $variant = $movement->stockable_type === $variantType ? $variants->get((string) $movement->stockable_id) : null;
                    $product = $variant?->product ?? $products->get((string) $movement->stockable_id);
                    if (! $product) {
                        continue;
                    }

                    $cost = $variant?->cost_price ?? $product->cost_price;

                    $movement->forceFill([
                        'supplier_id' => $product->default_supplier_id ?? $storeDefaultId,
                        'unit_cost' => $cost !== null ? (float) $cost : null,
                    ])->save();

                    $updated++;
                }
            });

        return $updated;
    }
}
