<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);
        $request->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|min:1|max:100']);

        $customers = Customer::query()
            ->search($request->search)
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->where('status', OrderStatus::Completed)], 'total')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return $this->respond(CustomerResource::collection($customers));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);
        $customer = Customer::create($request->validated());

        return $this->respond(new CustomerResource($customer), 'Pelanggan berhasil ditambahkan.', 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);
        $customer->loadCount('orders')
            ->loadSum(['orders as total_spent' => fn ($q) => $q->where('status', OrderStatus::Completed)], 'total');

        return $this->respond(new CustomerResource($customer));
    }

    /** GET /api/customers/{customer}/orders — riwayat transaksi pelanggan. */
    public function orders(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        $orders = $customer->orders()
            ->withCount('items')
            ->with('cashier')
            ->paginate($request->integer('per_page', 10));

        return $this->respond(OrderResource::collection($orders));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);
        $customer->update($request->validated());

        return $this->respond(new CustomerResource($customer), 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        Gate::authorize('delete', $customer);

        if ($customer->orders()->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->exists()) {
            throw new BusinessException('Pelanggan masih memiliki pesanan aktif.', 409);
        }

        $customer->delete();

        return $this->respond(null, 'Pelanggan berhasil dihapus.');
    }
}
