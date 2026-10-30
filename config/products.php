<?php

return [
    /*
    |--------------------------------------------------------------------------
    | New Arrival Period
    |--------------------------------------------------------------------------
    |
    | How many days after it is first published a product is flagged as a new
    | arrival (`is_new_arrival` on the product list API). The boundary is
    | inclusive: a product published exactly this many days ago still counts.
    |
    */

    'new_arrival_days' => (int) env('PRODUCT_NEW_ARRIVAL_DAYS', 2),
];
