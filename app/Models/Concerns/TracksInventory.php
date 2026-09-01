<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Shared inventory computation for stockable models (Product, ProductVariant).
 *
 * The backend is the single source of truth for stock figures — the frontend
 * only renders what these accessors produce, never derives status itself.
 *
 * Status priority (documented here so the storefront and admin agree):
 *  1. pending        — no sellable units on hand but stock is on the way
 *  2. out_of_stock   — no sellable units on hand and nothing incoming
 *  3. overstock      — on/over the configured upper bound
 *  4. low_stock      — above zero but at/below the minimum
 *  5. in_stock       — everything else
 */
trait TracksInventory
{
    public function getStockStatusAttribute(): string
    {
        $onHand = (int) $this->stock_quantity;
        $minimum = (int) $this->min_stock_alert;
        $maximum = $this->max_stock_level !== null ? (int) $this->max_stock_level : null;
        $incoming = (int) $this->incoming_stock;

        if ($onHand <= 0 && $incoming > 0) {
            return 'pending';
        }

        if ($onHand <= 0) {
            return 'out_of_stock';
        }

        if ($maximum !== null && $onHand >= $maximum) {
            return 'overstock';
        }

        if ($onHand <= $minimum) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * Units a customer can actually buy right now.
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, (int) $this->stock_quantity - (int) $this->reserved_stock);
    }

    /**
     * Whether this stockable should be surfaced in inventory management.
     */
    public function getTrackedAttribute(): bool
    {
        return (bool) ($this->track_inventory ?? true);
    }
}
