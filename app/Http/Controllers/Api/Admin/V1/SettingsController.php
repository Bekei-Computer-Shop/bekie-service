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
            'logo_url' => '',
            'logo_path' => '',
            'favicon_url' => '',
            'favicon_path' => '',
        ],
        'payments' => [
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
                ['id' => 'analytics', 'name' => 'Analytics', 'status' => 'Not connected', 'description' => 'Track orders, traffic, and conversions', 'enabled' => false],
                ['id' => 'facebook', 'name' => 'Facebook Pixel', 'status' => 'Not connected', 'description' => 'Track ad conversions and retargeting', 'enabled' => false],
                ['id' => 'telegram', 'name' => 'Telegram', 'status' => 'Not connected', 'description' => 'Send order and stock alerts', 'enabled' => false],
                ['id' => 'email', 'name' => 'Email service', 'status' => 'Needs setup', 'description' => 'Sync product emails and automation', 'enabled' => false],
                ['id' => 'maps', 'name' => 'Google Maps', 'status' => 'Not connected', 'description' => 'Store location and directions', 'enabled' => false],
                ['id' => 'webhooks', 'name' => 'Webhooks', 'status' => 'Not connected', 'description' => 'Send events to fulfillment and CRM tools', 'enabled' => false],
            ],
            'webhooks' => [
                'webhooks' => [],
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
        $defaults = $this->defaultSettings();

        if (! is_array($payload)) {
            return $this->error('The settings payload is invalid.', 422);
        }

        foreach ($payload as $section => $sectionValues) {
            if (! is_array($sectionValues) || ! array_key_exists($section, $defaults)) {
                return $this->error('The settings section is invalid.', 422);
            }

            $unknownKeys = array_diff(array_keys($sectionValues), array_keys($defaults[$section]));
            if ($unknownKeys !== []) {
                return $this->error('The settings payload contains unsupported fields.', 422, [
                    'section' => $section,
                    'fields' => array_values($unknownKeys),
                ]);
            }
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
        $settings = $this->defaultSettings();

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

    private function defaultSettings(): array
    {
        return [
            ...self::DEFAULTS,
            'taxes' => config('taxes'),
        ];
    }
}
