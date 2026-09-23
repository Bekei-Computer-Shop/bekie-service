<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends BaseAdminController
{
    private const DEFAULTS = [
        'general' => [
            'store_name' => 'Beckie Deal Webstore',
            'store_url' => 'https://beckiedeal.com',
            'contact_email' => 'orders@yourstore.com',
            'phone' => '+855 12 345 678',
            'timezone' => 'Asia/Phnom_Penh',
            'currency' => 'USD',
            'language' => 'English',
            'weight_unit' => 'kg',
            'length_unit' => 'cm',
            'business_address' => '15 Street 184, Phnom Penh, Cambodia',
            'country' => 'Cambodia',
        ],
        'payments' => [
            'providers' => [
                ['id' => 'cod', 'name' => 'Cash on delivery', 'status' => 'Active', 'enabled' => true, 'mode' => 'Live', 'connected' => true],
                ['id' => 'bank', 'name' => 'Bank transfer', 'status' => 'Needs setup', 'enabled' => false, 'mode' => 'Test', 'connected' => false],
                ['id' => 'card', 'name' => 'Card', 'status' => 'Active', 'enabled' => true, 'mode' => 'Live', 'connected' => true],
                ['id' => 'paypal', 'name' => 'PayPal', 'status' => 'Inactive', 'enabled' => false, 'mode' => 'Test', 'connected' => false],
            ],
            'minimum_order' => 15,
            'instructions' => 'Please pay the total upon delivery and keep the receipt for confirmation.',
        ],
        'shipping' => [
            'zones' => [
                ['name' => 'Cambodia', 'regions' => 'Phnom Penh, Siem Reap', 'methods' => 'Standard, Express', 'rates' => '$2.50 / $8.00'],
                ['name' => 'Regional', 'regions' => 'Thailand, Vietnam', 'methods' => 'Standard', 'rates' => '$9.00'],
            ],
            'delivery' => [
                ['id' => 'standard', 'name' => 'Standard', 'enabled' => true, 'days' => '3–5 days', 'price' => '$4.50'],
                ['id' => 'express', 'name' => 'Express', 'enabled' => true, 'days' => '1–2 days', 'price' => '$12.00'],
                ['id' => 'pickup', 'name' => 'Pickup', 'enabled' => false, 'days' => 'Same day', 'price' => 'Free'],
            ],
            'warehouse' => '1204 Preah Sihanouk Blvd, Phnom Penh',
            'package_weight' => 1.2,
            'free_shipping_threshold' => 99,
        ],
        'taxes' => [
            'prices_include_tax' => true,
            'show_tax_at_checkout' => true,
            'tax_id' => 'VAT-2024-118',
            'rates' => [
                ['region' => 'Cambodia', 'name' => 'Standard VAT', 'rate' => 10, 'applies_to' => 'All taxable goods'],
                ['region' => 'International', 'name' => 'No tax', 'rate' => 0, 'applies_to' => 'Exports'],
            ],
        ],
        'notifications' => [
            'rows' => [
                ['name' => 'New order', 'email' => true, 'in_app' => true, 'sms' => false],
                ['name' => 'Order shipped', 'email' => true, 'in_app' => true, 'sms' => true],
                ['name' => 'Low stock', 'email' => true, 'in_app' => false, 'sms' => false],
                ['name' => 'New customer', 'email' => false, 'in_app' => true, 'sms' => false],
                ['name' => 'Refund requested', 'email' => true, 'in_app' => true, 'sms' => true],
            ],
            'recipients' => ['ops@beckiedeal.com', 'finance@beckiedeal.com'],
            'sender_name' => 'Beckie Deal',
            'reply_to' => 'support@beckiedeal.com',
        ],
        'team' => [
            'filters' => ['All roles', 'Owner', 'Admin', 'Manager', 'Staff'],
            'members' => [
                ['name' => 'Alya Vann', 'email' => 'alya@beckiedeal.com', 'role' => 'Owner', 'status' => 'Active', 'last_active' => '2 min ago', 'avatar' => 'AV'],
                ['name' => 'Nim Sothea', 'email' => 'nim@beckiedeal.com', 'role' => 'Admin', 'status' => 'Active', 'last_active' => '18 min ago', 'avatar' => 'NS'],
                ['name' => 'Rith Chenda', 'email' => 'rith@beckiedeal.com', 'role' => 'Manager', 'status' => 'Pending', 'last_active' => '1 day ago', 'avatar' => 'RC'],
            ],
        ],
        'integrations' => [
            'cards' => [
                ['id' => 'analytics', 'name' => 'Analytics', 'status' => 'Connected', 'description' => 'Track orders, traffic, and conversions', 'enabled' => true],
                ['id' => 'facebook', 'name' => 'Facebook Pixel', 'status' => 'Not connected', 'description' => 'Track ad conversions and retargeting', 'enabled' => false],
                ['id' => 'telegram', 'name' => 'Telegram', 'status' => 'Connected', 'description' => 'Send order and stock alerts', 'enabled' => true],
                ['id' => 'email', 'name' => 'Email service', 'status' => 'Needs setup', 'description' => 'Sync product emails and automation', 'enabled' => false],
                ['id' => 'maps', 'name' => 'Google Maps', 'status' => 'Connected', 'description' => 'Store location and directions', 'enabled' => true],
                ['id' => 'webhooks', 'name' => 'Webhooks', 'status' => 'Connected', 'description' => 'Sent to fulfillment and CRM tools', 'enabled' => true],
            ],
            'webhooks' => [
                ['endpoint' => 'https://hooks.example.com/shipments', 'events' => 'Order shipped', 'secret' => '••••••••', 'status' => 'Healthy'],
                ['endpoint' => 'https://hooks.example.com/inventory', 'events' => 'Low stock', 'secret' => '••••••••', 'status' => 'Failed 2h ago'],
            ],
        ],
    ];

    public function show(): JsonResponse
    {
        return $this->success([
            'settings' => $this->readSettings(),
        ], 'Settings retrieved successfully.');
    }

    public function update(Request $request): JsonResponse
    {
        $payload = $request->input('settings');

        if (! is_array($payload)) {
            return $this->error('The settings payload is invalid.', 422);
        }

        DB::transaction(function () use ($payload): void {
            foreach ($payload as $section => $sectionValues) {
                if (! is_array($sectionValues)) {
                    continue;
                }

                foreach ($sectionValues as $key => $value) {
                    Setting::putStore(sprintf('%s.%s', $section, $key), $value, 'settings');
                }
            }
        });

        return $this->success([
            'settings' => $this->readSettings(),
        ], 'Settings updated successfully.');
    }

    private function readSettings(): array
    {
        $settings = self::DEFAULTS;

        $stored = Setting::query()
            ->where('group', 'settings')
            ->get()
            ->keyBy('key');

        foreach ($settings as $section => $fields) {
            foreach ($fields as $key => $defaultValue) {
                $storageKey = sprintf('%s.%s', $section, $key);

                if ($stored->has($storageKey)) {
                    $settings[$section][$key] = $stored->get($storageKey)->decodedValue();
                }
            }
        }

        return $settings;
    }
}
