<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Models\Setting;
use App\Services\AbaPayWayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreSettingsController extends BaseAdminController
{
    public function __construct(private readonly AbaPayWayService $payWay) {}
    private const SUPPORTED_CURRENCIES = ['USD', 'KHR'];

    private const SUPPORTED_PAYMENT_METHODS = ['aba_payway', 'cod'];

    private const DEFAULTS = [
        'store_name' => 'Beckie Deal Webstore',
        'store_url' => '',
        'contact_email' => '',
        'timezone' => 'Asia/Phnom_Penh',
        'currency' => 'USD',
        'payment_methods' => ['aba_payway', 'cod'],
        'aba_enabled' => false,
        'cod_enabled' => true,
        'cod_instructions' => 'Pay cash when your order is delivered.',
    ];

    public function show(): JsonResponse
    {
        return $this->success([
            'settings' => $this->readSettings(),
            'payway' => $this->payWay->publicConfig(),
            'options' => [
                'currencies' => self::SUPPORTED_CURRENCIES,
                'payment_methods' => self::SUPPORTED_PAYMENT_METHODS,
                'timezones' => ['Asia/Phnom_Penh', 'Asia/Bangkok', 'UTC'],
            ],
        ], 'Store settings retrieved successfully.');
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_name' => ['sometimes', 'required', 'string', 'max:255'],
            'store_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'timezone' => ['sometimes', 'required', 'in:Asia/Phnom_Penh,Asia/Bangkok,UTC'],
            'currency' => ['sometimes', 'required', 'in:USD,KHR'],
            'payment_methods' => ['sometimes', 'array', 'min:1'],
            'payment_methods.*' => ['in:aba_payway,cod'],
            'aba_enabled' => ['sometimes', 'boolean'],
            'cod_enabled' => ['sometimes', 'boolean'],
            'cod_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $methods = $validated['payment_methods'] ?? $this->readSettings()['payment_methods'];
        if (empty($methods)) {
            return $this->error('At least one payment method must be enabled.', 422, [
                'payment_methods' => ['Select ABA PayWay or Cash on delivery.'],
            ]);
        }

        $validated['aba_enabled'] = in_array('aba_payway', $methods, true);
        $validated['cod_enabled'] = in_array('cod', $methods, true);
        $validated['payment_methods'] = array_values(array_unique($methods));

        DB::transaction(function () use ($validated): void {
            foreach ($validated as $key => $value) {
                Setting::putStore($key, $value);
            }
        });

        return $this->success([
            'settings' => $this->readSettings(),
            'payway' => $this->payWay->publicConfig(),
        ], 'Store settings updated successfully.');
    }

    public function checkPaywayConnection(): JsonResponse
    {
        $result = $this->payWay->connectionCheck();

        return $this->success(
            ['payway' => $result],
            $result['connected'] ? 'ABA PayWay connection verified.' : 'ABA PayWay connection check failed.',
        );
    }

    private function readSettings(): array
    {
        $values = self::DEFAULTS;
        $stored = Setting::query()->whereIn('key', array_keys(self::DEFAULTS))->get();

        foreach ($stored as $setting) {
            $values[$setting->key] = $setting->decodedValue();
        }

        $values['payment_methods'] = array_values(array_intersect(
            $values['payment_methods'] ?: self::SUPPORTED_PAYMENT_METHODS,
            self::SUPPORTED_PAYMENT_METHODS,
        ));
        $values['aba_enabled'] = (bool) config('services.payway.merchant_id') && $values['aba_enabled'];

        return $values;
    }
}
