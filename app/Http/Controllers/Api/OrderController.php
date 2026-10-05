<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\IndexOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\StorePaymentRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /**
     * GET /api/orders?search=&status=pending,processing&type=&payment_status=&date_from=&date_to=&per_page=
     */
    public function index(IndexOrderRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::query()
            ->with(['customer', 'cashier'])
            ->withCount('items')
            ->filter($request->validated())
            ->latest('id')
            ->paginate($request->validated('per_page') ?? 15)
            ->withQueryString();

        return $this->respond(OrderResource::collection($orders));
    }

    /** Kasir mencatat pesanan baru. */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        Gate::authorize('create', Order::class);
        $order = $this->orders->create($request->validated(), $request->user());

        return $this->respond(new OrderResource($order), "Pesanan {$order->code} berhasil dicatat.", 201);
    }

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return $this->respond(new OrderResource($order->load(['items', 'customer', 'cashier'])));
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);
        $order = $this->orders->update($order, $request->validated());

        return $this->respond(new OrderResource($order), 'Pesanan berhasil diperbarui.');
    }

    public function destroy(Order $order): JsonResponse
    {
        Gate::authorize('delete', $order);
        $order->delete();

        return $this->respond(null, "Pesanan {$order->code} berhasil dihapus.");
    }

    /** PATCH /api/orders/{order}/status — memajukan alur pesanan. */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $target = $request->targetStatus();
        Gate::authorize('changeStatus', [$order, $target]);

        $order = $this->orders->changeStatus($order, $target, $request->user(), $request->validated('reason'));

        return $this->respond(new OrderResource($order), "Status pesanan menjadi '{$order->status->label()}'.");
    }

    /** POST /api/orders/{order}/payment — mencatat pembayaran. */
    public function pay(StorePaymentRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('pay', $order);

        $order = $this->orders->pay(
            $order,
            PaymentMethod::from($request->validated('payment_method')),
            (float) $request->validated('paid_amount'),
        );

        return $this->respond(new OrderResource($order), 'Pembayaran berhasil dicatat.', 201);
    }
}
