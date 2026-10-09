<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /** GET /api/dashboard — ringkasan operasional hari ini (semua role). */
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today();

        $statusCounts = Order::query()
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $completedToday = Order::where('status', OrderStatus::Completed)->whereDate('completed_at', $today);

        $upcomingPreorders = Order::query()
            ->with('customer')
            ->where('type', 'preorder')
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->orderBy('pickup_at')
            ->limit(5)
            ->get();

        return $this->respond([
            'queue' => [
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'processing' => (int) ($statusCounts['processing'] ?? 0),
                'ready' => (int) ($statusCounts['ready'] ?? 0),
            ],
            'today' => [
                'orders_completed' => (clone $completedToday)->count(),
                'revenue' => (float) (clone $completedToday)->sum('total'),
                'orders_created' => Order::whereDate('created_at', $today)->count(),
            ],
            'low_stock_count' => Product::active()->lowStock()->count(),
            'upcoming_preorders' => OrderResource::collection($upcomingPreorders),
        ]);
    }

    /** GET /api/reports/sales?date_from=&date_to= — laporan penjualan (admin). */
    public function sales(Request $request): JsonResponse
    {
        Gate::authorize('admin');
        $f = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        $from = Carbon::parse($f['date_from'] ?? now()->subDays(6))->startOfDay();
        $to = Carbon::parse($f['date_to'] ?? now())->endOfDay();

        $completed = Order::where('status', OrderStatus::Completed)->whereBetween('completed_at', [$from, $to]);

        $daily = (clone $completed)
            ->select(DB::raw('DATE(completed_at) as date'), DB::raw('COUNT(*) as orders'), DB::raw('SUM(total) as revenue'))
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'orders' => (int) $r->orders, 'revenue' => (float) $r->revenue]);

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereBetween('orders.completed_at', [$from, $to])
            ->select('order_items.product_id', 'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as qty'), DB::raw('SUM(order_items.subtotal) as revenue'))
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['product_id' => $r->product_id, 'product_name' => $r->product_name, 'qty' => (int) $r->qty, 'revenue' => (float) $r->revenue]);

        $byMethod = (clone $completed)
            ->select('payment_method', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total) as revenue'))
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($r) => ['payment_method' => $r->payment_method?->value, 'label' => $r->payment_method?->label(), 'orders' => (int) $r->orders, 'revenue' => (float) $r->revenue]);

        return $this->respond([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'orders_completed' => (clone $completed)->count(),
                'revenue' => (float) (clone $completed)->sum('total'),
                'discount_given' => (float) (clone $completed)->sum('discount'),
                'orders_cancelled' => Order::where('status', OrderStatus::Cancelled)->whereBetween('cancelled_at', [$from, $to])->count(),
                'unpaid_active' => Order::where('payment_status', PaymentStatus::Unpaid)->whereNotIn('status', [OrderStatus::Cancelled])->count(),
            ],
            'daily' => $daily,
            'top_products' => $topProducts,
            'by_payment_method' => $byMethod,
        ]);
    }
}
