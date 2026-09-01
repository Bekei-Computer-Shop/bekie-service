<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\Stock\BulkStockMovementRequest;
use App\Http\Requests\Api\Admin\V1\Stock\StockMovementRequest;
use App\Http\Resources\Api\Admin\V1\StockMovementResource;
use App\Http\Resources\Api\Admin\V1\StockSummaryResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $query = Product::query()->with(['category:id,name,slug', 'brand:id,name,slug'])
            ->select([
                'uuid',
                'name',
                'sku',
                'price',
                'cost_price',
                'stock_quantity',
                'min_stock_alert',
                'track_inventory',
                'in_stock',
                'thumbnail',
                'category_id',
                'brand_id',
                'created_at',
                'updated_at',
            ])
            ->where('track_inventory', true);

        if ($request->boolean('include_variants')) {
            $query->with(['variants:id,product_id,name,sku,stock_quantity,min_stock_alert']);
        }

        $search = $request->input('q') ?? $request->input('search');
        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        if ($stockStatus = $request->input('stock_status')) {
            match ($stockStatus) {
                'out' => $query->whereColumn('stock_quantity', '<=', 0),
                'low' => $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock_alert'),
                'healthy' => $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '>', 'min_stock_alert'),
                default => null,
            };
        }

        if ($request->filled('updated_from')) {
            $query->whereDate('updated_at', '>=', Carbon::parse($request->input('updated_from')));
        }

        if ($request->filled('updated_to')) {
            $query->whereDate('updated_at', '<=', Carbon::parse($request->input('updated_to')));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'min_stock_alert');
        }

        $sort = $request->input('sort', 'updated_at');
        $sort = $sort === 'id' ? 'updated_at' : $sort;
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['name', 'sku', 'stock_quantity', 'created_at', 'updated_at'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('stock_quantity', 'asc');
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
            ->where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_alert')
            ->orderBy('stock_quantity')
            ->get();

        return $this->success($products->map(fn (Product $product) => [
            'id' => $product->uuid,
            'numeric_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'stock_quantity' => (int) $product->stock_quantity,
            'min_stock_alert' => (int) $product->min_stock_alert,
        ]));
    }

    #[Endpoint(title: 'Get stock item details', description: 'Returns product stock details and full stock movement history for a given product ID or UUID.')]
    #[Response(status: 200, description: 'Product stock details with movement history.')]
    public function show(string $id, Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('track_inventory', true)
            ->with([
                'category:id,name,slug',
                'brand:id,name,slug',
                'variants:id,product_id,name,sku,stock_quantity,min_stock_alert,in_stock',
            ]);

        if (Str::isUuid($id)) {
            $product = $query->where('uuid', $id)->firstOrFail();
        } else {
            $product = $query->where('sku', $id)->firstOrFail();
        }

        $movementsQuery = $product->stockMovements()->latest()->with('createdBy:id,first_name,last_name,email');

        if ($request->filled('movement_type')) {
            $movementsQuery->where('movement_type', $request->input('movement_type'));
        }
        if ($request->filled('movements_from')) {
            $movementsQuery->whereDate('created_at', '>=', Carbon::parse($request->input('movements_from')));
        }
        if ($request->filled('movements_to')) {
            $movementsQuery->whereDate('created_at', '<=', Carbon::parse($request->input('movements_to')));
        }

        $movementsPage = (int) $request->input('movements_page', 1);
        $movements = $movementsQuery->paginate(20, ['*'], 'movements_page', $movementsPage);

        return $this->success([
            'id' => $product->id,
            'uuid' => $product->uuid,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'price' => $product->price,
            'cost_price' => $product->cost_price,
            'stock_quantity' => (int) $product->stock_quantity,
            'min_stock_alert' => (int) $product->min_stock_alert,
            'reorder_point' => (int) $product->reorder_point,
            'track_inventory' => (bool) $product->track_inventory,
            'in_stock' => (bool) $product->in_stock,
            'warehouse_location' => $product->warehouse_location,
            'thumbnail' => $product->thumbnail,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'variants' => $product->variants,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'movements' => [
                'items' => StockMovementResource::collection($movements->items()),
                'pagination' => [
                    'total' => $movements->total(),
                    'per_page' => $movements->perPage(),
                    'current_page' => $movements->currentPage(),
                    'last_page' => $movements->lastPage(),
                ],
            ],
        ]);
    }

    #[Endpoint(title: 'List stock movements', description: 'Returns the audit history of stock changes, including adjustments, reconciliations, receipts, issues, and transfers.')]
    #[Response(status: 200, description: 'Paginated stock movement history.')]
    public function movements(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $query = StockMovement::query()->latest()->with(['createdBy:id,first_name,last_name,email', 'stockable']);

        if ($request->filled('product_id')) {
            $productId = (string) $request->input('product_id');
            $resolvedId = Str::isUuid($productId)
                ? Product::where('uuid', $productId)->value('uuid')
                : Product::where('sku', $productId)->value('uuid');

            if ($resolvedId) {
                $query->where('stockable_type', Product::class)
                    ->where('stockable_id', $resolvedId);
            }
        } elseif ($request->filled('stockable_type') && $request->filled('stockable_id')) {
            $stockableType = $request->input('stockable_type');
            if ($stockableType === 'product' || $stockableType === 'Product') {
                $stockableType = Product::class;
            } elseif ($stockableType === 'variant' || $stockableType === 'ProductVariant') {
                $stockableType = ProductVariant::class;
            }

            $query->where('stockable_type', $stockableType)
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
        $stockable = $this->resolveStockable($request->input('stockable_type'), (int) $request->input('stockable_id'));
        $movementType = $request->input('movement_type');
        $quantity = (int) $request->input('quantity');
        $reason = $request->input('reason');
        $reference = $request->input('reference');
        $metadata = (array) $request->input('metadata', []);

        try {
            $movement = match ($movementType) {
                'adjust' => $this->stockService->adjust($stockable, $quantity, $reason, $reference, $metadata),
                'reconcile' => $this->stockService->reconcile($stockable, $quantity, $reason, $reference, $metadata),
                'stock_in' => $this->stockService->stockIn($stockable, $quantity, $reason, $reference, $metadata),
                'stock_out' => $this->stockService->stockOut($stockable, $quantity, $reason, $reference, $metadata),
                'transfer' => $this->stockService->transfer($stockable, $quantity, (string) $request->input('source_location', ''), (string) $request->input('destination_location', ''), $reason, $metadata),
                default => throw new \InvalidArgumentException('Unsupported stock movement type.'),
            };

            return $this->created(new StockMovementResource($movement));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'quantity' => [$e->getMessage()],
            ]);
        }
    }

    protected function resolveStockable(string $stockableType, int $stockableId): Product|ProductVariant
    {
        $normalizedType = match (strtolower(trim($stockableType))) {
            'product', 'app\\models\\product' => Product::class,
            'variant', 'product_variant', 'productvariant', 'app\\models\\productvariant' => ProductVariant::class,
            default => $stockableType,
        };

        $modelClass = match ($normalizedType) {
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

    public function summary(): JsonResponse
    {
        $tracked = Product::where('track_inventory', true);
        $total = (clone $tracked)->count();
        $out = (clone $tracked)->where('stock_quantity', '<=', 0)->count();
        $low = (clone $tracked)->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock_alert')->count();
        $inStock = $total - $out - $low;
        $stockValue = (clone $tracked)->selectRaw('COALESCE(SUM(CAST(stock_quantity AS DECIMAL) * CAST(cost_price AS DECIMAL)), 0) as total_value')->value('total_value');

        return $this->success(new StockSummaryResource([
            'total' => $total,
            'in_stock' => $inStock,
            'low_stock' => $low,
            'out_of_stock' => $out,
            'total_stock_value' => round((float) $stockValue, 2),
        ]));
    }

    public function bulkStore(BulkStockMovementRequest $request): JsonResponse
    {
        $items = $request->input('items');
        $globalReason = $request->input('reason');
        $globalReference = $request->input('reference');

        $prepared = [];
        foreach ($items as $item) {
            $stockable = $this->resolveStockable($item['stockable_type'], (int) $item['stockable_id']);
            $prepared[] = [
                'stockable' => $stockable,
                'movement_type' => $item['movement_type'],
                'quantity' => (int) $item['quantity'],
                'reason' => $item['reason'] ?? $globalReason,
                'reference' => $item['reference'] ?? $globalReference,
                'metadata' => $item['metadata'] ?? [],
            ];
        }

        try {
            $movements = $this->stockService->bulkAdjust($prepared);

            return $this->created(StockMovementResource::collection($movements));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'items' => [$e->getMessage()],
            ]);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->select(['uuid', 'name', 'sku', 'barcode', 'stock_quantity', 'min_stock_alert', 'cost_price', 'price', 'in_stock', 'warehouse_location', 'category_id', 'brand_id', 'updated_at'])
            ->where('track_inventory', true);

        if ($request->filled('q')) {
            $like = '%'.$request->input('q').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }
        if ($stock_status = $request->input('stock_status')) {
            match ($stock_status) {
                'out' => $query->where('stock_quantity', '<=', 0),
                'low' => $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock_alert'),
                default => null,
            };
        }
        if ($request->filled('updated_from')) {
            $query->whereDate('updated_at', '>=', Carbon::parse($request->input('updated_from')));
        }
        if ($request->filled('updated_to')) {
            $query->whereDate('updated_at', '<=', Carbon::parse($request->input('updated_to')));
        }

        $filename = 'stock-export-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'SKU', 'Barcode', 'Category', 'Brand', 'Stock Qty', 'Min Alert', 'Cost Price', 'Sale Price', 'In Stock', 'Warehouse', 'Last Updated']);
            $query->orderBy('name')->chunk(500, function ($products) use ($handle) {
                foreach ($products as $p) {
                    fputcsv($handle, [
                        $p->id, $p->name, $p->sku, $p->barcode,
                        $p->category?->name ?? '', $p->brand?->name ?? '',
                        $p->stock_quantity, $p->min_stock_alert,
                        $p->cost_price, $p->price,
                        $p->in_stock ? 'Yes' : 'No',
                        $p->warehouse_location ?? 'Main Warehouse',
                        $p->updated_at?->toDateTimeString(),
                    ]);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }

    public function movementExport(Request $request): StreamedResponse
    {
        $query = StockMovement::query()
            ->with(['createdBy:id,first_name,last_name', 'stockable'])
            ->latest();

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->input('movement_type'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }
        if ($request->filled('product_id')) {
            $query->where('stockable_type', Product::class)->where('stockable_id', $request->integer('product_id'));
        }

        $filename = 'stock-movements-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Product', 'SKU', 'Type', 'Qty Change', 'Prev Qty', 'New Qty', 'Reason', 'Reference', 'Source', 'Destination', 'Adjusted By']);
            $query->chunk(500, function ($movements) use ($handle) {
                foreach ($movements as $m) {
                    fputcsv($handle, [
                        $m->created_at?->toDateTimeString(),
                        $m->stockable?->name ?? '',
                        $m->stockable?->sku ?? '',
                        $m->movement_type,
                        $m->quantity,
                        $m->previous_quantity,
                        $m->new_quantity,
                        $m->reason ?? '',
                        $m->reference ?? '',
                        $m->source_location ?? '',
                        $m->destination_location ?? '',
                        $m->createdBy ? trim(($m->createdBy->first_name ?? '').' '.($m->createdBy->last_name ?? '')) : 'System',
                    ]);
                }
            });
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
