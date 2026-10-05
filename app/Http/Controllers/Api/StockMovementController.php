<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreStockMovementRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StockMovementController extends Controller
{
    public function __construct(private \App\Services\StockService $stock) {}

    /** GET /api/stock-movements — kartu stok semua produk (admin). */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('admin');
        $f = $request->validate([
            'product_id' => 'nullable|integer',
            'type' => 'nullable|in:in,out,adjustment,return',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $movements = StockMovement::query()
            ->with(['product', 'order', 'user'])
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')
            ->paginate($f['per_page'] ?? 20)
            ->withQueryString();

        return $this->respond(StockMovementResource::collection($movements));
    }

    /** GET /api/products/{product}/stock-movements */
    public function forProduct(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        $movements = $product->stockMovements()
            ->with(['order', 'user'])
            ->paginate($request->integer('per_page', 20));

        return $this->respond(StockMovementResource::collection($movements));
    }

    /** POST /api/products/{product}/stock-movements — restock / stock opname (admin). */
    public function store(StoreStockMovementRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('manageStock', $product);

        $movement = $this->stock->adjust(
            $product,
            StockMovementType::from($request->validated('type')),
            (int) $request->validated('quantity'),
            $request->user(),
            $request->validated('note'),
        );

        return $this->respond([
            'movement' => new StockMovementResource($movement),
            'product' => new ProductResource($product->refresh()->load('category')),
        ], 'Stok berhasil diperbarui.', 201);
    }
}
