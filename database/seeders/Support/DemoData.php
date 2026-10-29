<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use RuntimeException;

/**
 * What the demo seeders own, in one place, so `demo:reset` deletes exactly
 * those rows and nothing an operator or a real customer created.
 *
 * Orders are owned through `metadata->seeded_by`; everything hanging off a
 * demo order (items, coupon usages, payments, serials sold with it, reviews
 * of it) is owned through that order.
 */
final class DemoData
{
    /** `orders.metadata->seeded_by` values written by the demo order seeders. */
    public const ORDER_TAGS = [
        'customer-seeder',
        'demo-order-seeder',
        'this-week-order-seeder',
        'promotion-usage-seeder',
    ];

    /** `product_serials.receiving_reference` on units created as sold with a demo order. */
    public const ORDER_SERIAL_REFERENCE = 'DEMO-ORDER-SALE';

    /** `product_serials.receiving_reference` on demo stock received into the warehouse. */
    public const STOCK_SERIAL_REFERENCE = 'DEMO-STOCK-RECEIPT';

    /** `stock_movements.reference` on the opening receipts DemoCatalogSeeder writes. */
    public const STOCK_MOVEMENT_REFERENCE = 'DEMO-OPENING-STOCK';

    /** Environments the demo data seeders and `demo:reset` may touch. */
    public const ENVIRONMENTS = ['local', 'testing'];

    public static function allowed(): bool
    {
        return app()->environment(self::ENVIRONMENTS);
    }

    /**
     * Stops a demo seeder before it writes anything outside local/testing.
     */
    public static function ensureAllowed(string $seeder): void
    {
        if (! self::allowed()) {
            throw new RuntimeException(sprintf(
                '%s writes disposable demo data and only runs when APP_ENV is %s (current: %s).',
                class_basename($seeder),
                implode(' or ', self::ENVIRONMENTS),
                app()->environment(),
            ));
        }
    }
}
