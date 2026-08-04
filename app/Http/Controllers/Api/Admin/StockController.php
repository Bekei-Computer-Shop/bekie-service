<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseAdminController;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Product;

class StockController extends BaseAdminController
{
    public function alerts()
    {
        $products = Product::with('category')
            ->whereColumn('stock_quantity', '<=', 'min_stock_alert')
            ->orderBy('stock_quantity')
            ->get();

        return $this->success(ProductResource::collection($products));
    }
}
