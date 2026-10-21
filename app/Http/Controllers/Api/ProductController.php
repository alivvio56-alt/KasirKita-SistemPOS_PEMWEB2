<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function __construct(private StockService $stock) {}

    /**
     * GET /api/products?search=&category_id=&is_active=&low_stock=1&sort=-price&per_page=
     */
    public function index(IndexProductRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);
        $f = $request->validated();

        $sort = $f['sort'] ?? 'name';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $products = Product::query()
            ->with('category')
            ->search($f['search'] ?? null)
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(isset($f['is_active']), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('low_stock'), fn ($q) => $q->lowStock())
            ->orderBy(ltrim($sort, '-'), $direction)
            ->paginate($f['per_page'] ?? 15)
            ->withQueryString();

        return $this->respond(ProductResource::collection($products));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $product = DB::transaction(function () use ($request) {
            // buang nilai null agar default kolom (min_stock, unit, is_active) dipakai
            $data = array_filter($request->validated(), fn ($v, $k) => $v !== null || $k === 'description', ARRAY_FILTER_USE_BOTH);
            $initial = (int) $data['stock'];
            $product = Product::create([...$data, 'stock' => 0]);

            if ($initial > 0) {
                $this->stock->move($product, StockMovementType::In, $initial, $request->user(), null, 'Stok awal');
            }

            return $product;
        });

        return $this->respond(new ProductResource($product->load('category')), 'Produk berhasil ditambahkan.', 201);
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return $this->respond(new ProductResource($product->load('category')));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);
        $product->update($request->validated());

        return $this->respond(new ProductResource($product->load('category')), 'Produk berhasil diperbarui.');
    }

    /** Soft delete agar riwayat transaksi lama tetap utuh. */
    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);
        $product->update(['is_active' => false]);
        $product->delete();

        return $this->respond(null, 'Produk berhasil dihapus.');
    }
}
