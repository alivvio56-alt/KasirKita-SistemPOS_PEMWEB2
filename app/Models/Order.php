<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'customer_id', 'user_id', 'type', 'status', 'pickup_at',
        'subtotal', 'discount', 'total', 'payment_status', 'payment_method',
        'paid_amount', 'change_amount', 'stock_deducted', 'notes', 'cancel_reason',
        'paid_at', 'processed_at', 'completed_at', 'cancelled_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'stock_deducted' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'pickup_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'stock_deducted' => 'boolean',
            'paid_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Kasir yang mencatat pesanan. */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot(['quantity', 'price', 'subtotal'])
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $q, $term) {
                $q->where(function (Builder $q) use ($term) {
                    $q->where('code', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->whereIn('status', explode(',', $v)))
            ->when($filters['type'] ?? null, fn (Builder $q, $v) => $q->where('type', $v))
            ->when($filters['payment_status'] ?? null, fn (Builder $q, $v) => $q->where('payment_status', $v))
            ->when($filters['customer_id'] ?? null, fn (Builder $q, $v) => $q->where('customer_id', $v))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
