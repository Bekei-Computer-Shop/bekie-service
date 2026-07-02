<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\Stock\StockMovementRequest;
use App\Http\Resources\Api\Admin\V1\StockMovementResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(name: 'Stock Management', description: 'Inventory overview, low-stock alerts, and stock movement history for admin operators.')]
class StockController extends BaseAdminController
{
    public function __construct(protected StockService $stockService) {}

    #[Endpoint(title: 'List stock items', description: 'Returns tracked products with current stock levels and low-stock thresholds for admin inventory review.')]
    #[Response(status: 200, description: 'Inventory listing with pagination.')]
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        $query = Product::query()->with(['category:id,name', 'brand:id,name'])
            ->select(['id', 'uuid', 'name', 'sku', 'stock_quantity', 'min_stock_alert', 'track_inventory', 'in_stock', 'category_id', 'brand_id'])
            ->where('track_inventory', true)
            ->orderBy('stock_quantity');

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', $search)
                    ->orWhere('sku', 'like', $search);
            });
        }

        $items = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'items' => $items->items(),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    #[Endpoint(title: 'List low-stock alerts', description: 'Returns products whose current stock quantity is at or below the configured minimum stock alert threshold.')]
    #[Response(status: 200, description: 'Low-stock alert list.')]
    public function alerts(): JsonResponse
    {
        $products = Product::query()
            ->whereColumn('stock_quantity', '<=', 'min_stock_alert')
            ->orderBy('stock_quantity')
            ->get();

        return $this->success($products->map(fn (Product $product) => [
            'id' => $product->uuid,
            'name' => $product->name,
            'sku' => $product->sku,
            'stock_quantity' => (int) $product->stock_quantity,
            'min_stock_alert' => (int) $product->min_stock_alert,
        ]));
    }

    #[Endpoint(title: 'List stock movements', description: 'Returns the audit history of stock changes, including adjustments, reconciliations, receipts, issues, and transfers.')]
    #[Response(status: 200, description: 'Paginated stock movement history.')]
    public function movements(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $query = StockMovement::query()->latest()->with('createdBy:id,name,email');

        if ($request->filled('stockable_type') && $request->filled('stockable_id')) {
            $query->where('stockable_type', $request->input('stockable_type'))
                ->where('stockable_id', (int) $request->input('stockable_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->input('movement_type'));
        }

        $items = $query->paginate($perPage);

        return $this->success([
            'items' => StockMovementResource::collection($items->items()),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'count' => $items->count(),
            ],
        ]);
    }

    #[Endpoint(title: 'Create stock movement', description: 'Applies a stock adjustment, reconciliation, receipt, issue, or transfer to a product or product variant and records it in the audit trail.')]
    #[Response(status: 201, description: 'Stock movement created successfully.')]
    public function store(StockMovementRequest $request): JsonResponse
    {
        $stockable = $this->resolveStockable($request->input('stockable_type'), $request->input('stockable_id'));
        $movementType = $request->input('movement_type');
        $quantity = (int) $request->input('quantity');
        $reason = $request->input('reason');
        $reference = $request->input('reference');
        $metadata = (array) $request->input('metadata', []);

        $movement = match ($movementType) {
            'adjust' => $this->stockService->adjust($stockable, $quantity, $reason, $reference, $metadata),
            'reconcile' => $this->stockService->reconcile($stockable, $quantity, $reason, $reference, $metadata),
            'stock_in' => $this->stockService->stockIn($stockable, $quantity, $reason, $reference, $metadata),
            'stock_out' => $this->stockService->stockOut($stockable, $quantity, $reason, $reference, $metadata),
            'transfer' => $this->stockService->transfer($stockable, $quantity, $request->input('source_location'), $request->input('destination_location'), $reason, $metadata),
            default => throw new \InvalidArgumentException('Unsupported stock movement type.'),
        };

        return $this->created(new StockMovementResource($movement));
    }

    protected function resolveStockable(string $stockableType, int $stockableId): Product|ProductVariant
    {
        $modelClass = match ($stockableType) {
            Product::class => Product::class,
            ProductVariant::class => ProductVariant::class,
            default => throw new \InvalidArgumentException('Unsupported stockable type.'),
        };

        $stockable = $modelClass::findOrFail($stockableId);

        if ($stockable instanceof ProductVariant) {
            $stockable->loadMissing('product');
        }

        return $stockable;
    }
}
