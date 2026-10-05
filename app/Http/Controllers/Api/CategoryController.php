<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Category::class);
        $request->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|min:1|max:100']);

        $categories = Category::query()
            ->withCount('products')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return $this->respond(CategoryResource::collection($categories));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);
        $category = Category::create($request->validated());

        return $this->respond(new CategoryResource($category), 'Kategori berhasil dibuat.', 201);
    }

    public function show(Category $category): JsonResponse
    {
        Gate::authorize('view', $category);

        return $this->respond(new CategoryResource($category->loadCount('products')));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        Gate::authorize('update', $category);
        $category->update($request->validated());

        return $this->respond(new CategoryResource($category), 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        if ($category->products()->withTrashed()->exists()) {
            throw new BusinessException('Kategori masih memiliki produk sehingga tidak dapat dihapus.', 409);
        }

        $category->delete();

        return $this->respond(null, 'Kategori berhasil dihapus.');
    }
}
