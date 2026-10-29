<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\User;
use Database\Seeders\Support\DemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Customer groups for group-targeted promotions, and the shoppers in them.
 *
 * CustomerSeeder's ten shoppers stay the Customers-screen showcase; these 30
 * fill the groups and cover the account states that list does not: never
 * verified, banned, deactivated, deleted, no saved address, several addresses.
 *
 * Groups:
 *  - vip, students, gamers-club, b2b-wholesale — active, with members;
 *  - inactive-promo-group — switched off, so its promotion never applies;
 *  - empty-group — active but nobody in it.
 *
 * Re-running is safe: groups match on slug, shoppers on their `@bekie.test`
 * email, addresses are only added to a shopper that has none, and group
 * membership is synced without detaching anyone added by hand.
 *
 * Usage: php artisan db:seed --class=CustomerGroupSeeder
 */
class CustomerGroupSeeder extends Seeder
{
    private const GROUPS = [
        ['slug' => CustomerGroup::VIP_SLUG, 'name' => 'VIP', 'description' => 'High-value customers.', 'is_active' => true],
        ['slug' => 'students', 'name' => 'Students', 'description' => 'Verified university and high-school students.', 'is_active' => true],
        ['slug' => 'gamers-club', 'name' => 'Gamers Club', 'description' => 'Members of the in-store gaming club.', 'is_active' => true],
        ['slug' => 'b2b-wholesale', 'name' => 'B2B / Wholesale', 'description' => 'Business accounts buying in volume.', 'is_active' => true],
        ['slug' => 'inactive-promo-group', 'name' => 'Inactive Promo Group', 'description' => 'A retired campaign audience, kept switched off.', 'is_active' => false],
        ['slug' => 'empty-group', 'name' => 'Empty Group', 'description' => 'An active group with no members yet.', 'is_active' => true],
    ];

    /**
     * first, last, groups, account flags, addresses to create.
     *
     * Flags: `unverified` (no email_verified_at), `inactive`, `banned`,
     * `deleted` (soft-deleted). Shoppers with none of those and at least one
     * address are the ones demo orders are placed for.
     *
     * @var list<array{0:string,1:string,2:list<string>,3:list<string>,4:int}>
     */
    private const SHOPPERS = [
        ['Narin', 'Chea', ['vip'], [], 1],
        ['Sophea', 'Keo', ['vip'], [], 2],
        ['Mony', 'Tep', ['vip', 'gamers-club'], [], 1],
        ['Ratana', 'Ouk', ['vip'], [], 1],
        ['Pisey', 'Hun', ['students'], [], 1],
        ['Vanna', 'Sam', ['students'], [], 1],
        ['Chanthy', 'Prak', ['students'], [], 1],
        ['Seyha', 'Long', ['students', 'gamers-club'], [], 1],
        ['Daravy', 'Mao', ['students'], ['unverified'], 1],
        ['Kosal', 'Nhem', ['students'], [], 0],
        ['Leakhena', 'Soun', ['students'], [], 3],
        ['Tola', 'Vong', ['students'], ['inactive'], 1],
        ['Rachana', 'Seng', ['gamers-club'], [], 1],
        ['Veasna', 'Chhay', ['gamers-club'], [], 1],
        ['Makara', 'Phan', ['gamers-club'], [], 2],
        ['Sokunthea', 'Ros', ['gamers-club'], ['banned'], 1],
        ['Bunthoeun', 'Yim', ['gamers-club'], [], 1],
        ['Sitha', 'Kong', ['b2b-wholesale'], [], 3],
        ['Phalla', 'Touch', ['b2b-wholesale'], [], 1],
        ['Samnang', 'Kim', ['b2b-wholesale'], [], 1],
        ['Thida', 'Uy', ['b2b-wholesale'], ['deleted'], 1],
        ['Vuthy', 'Chum', ['b2b-wholesale'], [], 2],
        ['Sreyneang', 'Hak', ['inactive-promo-group'], [], 1],
        ['Kimheng', 'Lay', ['inactive-promo-group'], [], 1],
        ['Socheata', 'Pich', ['inactive-promo-group'], [], 1],
        ['Borey', 'Nuon', [], [], 1],
        ['Channary', 'Eng', [], ['unverified'], 0],
        ['Rotha', 'Sar', [], [], 1],
        ['Kunthea', 'Bun', [], ['inactive', 'banned'], 1],
        ['Sopheak', 'Ly', [], [], 1],
    ];

    /** street, district, city, postcode — rotated through the shoppers. */
    private const ADDRESSES = [
        ['21 Street 178', 'Daun Penh', 'Phnom Penh', '12206'],
        ['5 Russian Blvd', 'Toul Kork', 'Phnom Penh', '12102'],
        ['132 Street 51', 'Chamkarmon', 'Phnom Penh', '12302'],
        ['40 National Road 6', 'Svay Dangkum', 'Siem Reap', '17252'],
        ['77 Street 3', 'Sangkat 2', 'Battambang', '02101'],
        ['9 Ekareach St', 'Sangkat 3', 'Sihanoukville', '18203'],
        ['260 Monivong Blvd', 'Prampi Makara', 'Phnom Penh', '12253'],
        ['14 Street 7', 'Kampong Cham', 'Kampong Cham', '03101'],
    ];

    public static function emailFor(string $first, int $index): string
    {
        return 'shopper.'.strtolower($first).($index + 1).'@bekie.test';
    }

    /**
     * Shoppers in good standing with an address, who can place demo orders.
     *
     * @return list<string>
     */
    public static function buyerEmails(?string $group = null): array
    {
        $emails = [];
        foreach (self::SHOPPERS as $index => [$first, , $groups, $flags, $addresses]) {
            if ($flags === [] && $addresses > 0 && ($group === null || in_array($group, $groups, true))) {
                $emails[] = self::emailFor($first, $index);
            }
        }

        return $emails;
    }

    public function run(): void
    {
        DemoData::ensureAllowed(self::class);

        $groups = [];
        foreach (self::GROUPS as $data) {
            $group = CustomerGroup::query()->firstOrCreate(['slug' => $data['slug']], $data);
            if ($group->is_active !== $data['is_active']) {
                $group->update(['is_active' => $data['is_active']]);
            }
            $groups[$data['slug']] = $group;
        }

        foreach (self::SHOPPERS as $index => [$first, $last, $groupSlugs, $flags, $addressCount]) {
            $shopper = $this->shopper($first, $last, $index, $flags);
            $shopper->customerGroups()->syncWithoutDetaching(
                array_map(fn (string $slug): int => $groups[$slug]->id, $groupSlugs)
            );
            $this->ensureAddresses($shopper, $index, $addressCount);

            if (in_array('deleted', $flags, true) && ! $shopper->trashed()) {
                $shopper->delete();
            }
        }

        $this->command?->info(sprintf('Seeded %d customer groups and %d grouped shoppers.', count(self::GROUPS), count(self::SHOPPERS)));
    }

    /** @param list<string> $flags */
    private function shopper(string $first, string $last, int $index, array $flags): User
    {
        $email = self::emailFor($first, $index);
        $shopper = User::withTrashed()->firstOrNew(['email' => $email]);
        $isNew = ! $shopper->exists;

        $shopper->fill([
            'first_name' => $first,
            'last_name' => $last,
            'username' => 'grouped_'.strtolower($first).($index + 1),
            // 93-prefixed: clear of CustomerSeeder's 92 range and real numbers.
            'phone' => '+85593'.str_pad((string) (200001 + $index), 6, '0', STR_PAD_LEFT),
            'is_admin' => false,
            'role' => 'user',
            'is_active' => ! in_array('inactive', $flags, true),
            'is_banned' => in_array('banned', $flags, true),
        ]);

        if ($isNew) {
            $shopper->password = Hash::make('password');
            $shopper->email_verified_at = in_array('unverified', $flags, true) ? null : now()->subDays(20 + $index * 9);
        }

        $shopper->save();

        if ($shopper->trashed() && ! in_array('deleted', $flags, true)) {
            $shopper->restore();
        }

        if (! $shopper->hasRole('user')) {
            $shopper->assignRole('user');
        }

        return $shopper;
    }

    /**
     * The first address is the default shipping one (the schema allows only
     * one per shopper); extra ones are a billing and an office address.
     */
    private function ensureAddresses(User $shopper, int $index, int $count): void
    {
        if ($count === 0 || $shopper->addresses()->exists()) {
            return;
        }

        $kinds = [['shipping', 'Home', true], ['billing', 'Billing', false], ['shipping', 'Office', false]];

        for ($n = 0; $n < $count; $n++) {
            [$line1, $district, $city, $postal] = self::ADDRESSES[($index + $n) % count(self::ADDRESSES)];
            [$type, $label, $default] = $kinds[$n];

            $shopper->addresses()->create([
                'type' => $type,
                'label' => $label,
                'full_name' => trim($shopper->first_name.' '.$shopper->last_name),
                'phone' => $shopper->phone,
                'email' => $shopper->email,
                'company' => $label === 'Office' ? 'Bekie Demo Co.' : null,
                'address_line_1' => $line1,
                'address_line_2' => $district,
                'city' => $city,
                'postal_code' => $postal,
                'country' => 'Cambodia',
                'is_default' => $default,
                'is_active' => true,
            ]);
        }
    }
}
