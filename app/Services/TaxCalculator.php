<?php

namespace App\Services;

use App\Models\Setting;

class TaxCalculator
{
    public function settings(): array
    {
        $stored = Setting::query()
            ->where('group', 'settings')
            ->whereIn('key', [
                'taxes.prices_include_tax',
                'taxes.show_tax_at_checkout',
                'taxes.rates',
            ])
            ->get()
            ->keyBy('key');

        return [
            'prices_include_tax' => $stored->has('taxes.prices_include_tax')
                ? (bool) $stored->get('taxes.prices_include_tax')->decodedValue()
                : (bool) config('taxes.prices_include_tax', true),
            'show_tax_at_checkout' => $stored->has('taxes.show_tax_at_checkout')
                ? (bool) $stored->get('taxes.show_tax_at_checkout')->decodedValue()
                : (bool) config('taxes.show_tax_at_checkout', true),
            'rates' => $stored->has('taxes.rates')
                ? (array) $stored->get('taxes.rates')->decodedValue()
                : (array) config('taxes.rates', []),
        ];
    }

    public function calculate(float $subtotal, float $discount, string $country): array
    {
        $settings = $this->settings();
        $rateConfiguration = $this->rateConfigurationForCountry($settings['rates'], $country);
        $rate = max(0, (float) ($rateConfiguration['rate'] ?? 0));
        $taxableAmount = max(0, $subtotal - $discount);
        $taxTotal = $settings['prices_include_tax']
            ? ($rate > 0 ? $taxableAmount * $rate / (100 + $rate) : 0)
            : $taxableAmount * $rate / 100;

        $taxTotal = round($taxTotal, 2);
        $grandTotal = $taxableAmount + ($settings['prices_include_tax'] ? 0 : $taxTotal);
        $taxSettings = [
            'enabled' => $rate > 0,
            'hide_tax' => ! $settings['show_tax_at_checkout'],
            'rate' => $rate,
            'calculation_type' => $settings['prices_include_tax'] ? 'inclusive' : 'exclusive',
            'label' => trim((string) ($rateConfiguration['name'] ?? '')) ?: 'Tax',
        ];

        return [
            'tax_total' => $taxTotal,
            'grand_total' => round($grandTotal, 2),
            'prices_include_tax' => $settings['prices_include_tax'],
            'show_tax_at_checkout' => $settings['show_tax_at_checkout'],
            'tax_rate' => $rate,
            'tax_settings' => $taxSettings,
        ];
    }

    public function checkoutSettings(string $country = 'Cambodia'): array
    {
        return $this->calculate(0, 0, $country)['tax_settings'];
    }

    private function rateConfigurationForCountry(array $rates, string $country): array
    {
        $internationalRate = null;
        $legacyCountryName = strtoupper($country) === 'KH' ? 'Cambodia' : $country;

        foreach ($rates as $taxRate) {
            if (! is_array($taxRate) || ! isset($taxRate['region'], $taxRate['rate'])) {
                continue;
            }

            if (strcasecmp((string) $taxRate['region'], $country) === 0
                || strcasecmp((string) $taxRate['region'], $legacyCountryName) === 0
                || strcasecmp((string) ($taxRate['country_code'] ?? ''), $country) === 0) {
                return $taxRate;
            }

            if (strcasecmp((string) $taxRate['region'], 'International') === 0) {
                $internationalRate = $taxRate;
            }
        }

        return $internationalRate ?? ['name' => 'Tax', 'rate' => 0];
    }
}
