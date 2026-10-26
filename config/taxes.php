<?php

return [
    'prices_include_tax' => true,
    'show_tax_at_checkout' => true,
    'tax_id' => 'VAT-2024-118',
    'rates' => [
        ['region' => 'Cambodia', 'name' => 'Standard VAT', 'rate' => 10, 'applies_to' => 'All taxable goods'],
        ['region' => 'International', 'name' => 'No tax', 'rate' => 0, 'applies_to' => 'Exports'],
    ],
];
