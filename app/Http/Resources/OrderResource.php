<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'allowed_transitions' => collect($this->status?->allowedTransitions() ?? [])->map->value->values(),
            'pickup_at' => $this->pickup_at?->toIso8601String(),
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status?->label(),
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'paid_amount' => (float) $this->paid_amount,
            'change_amount' => (float) $this->change_amount,
            'stock_deducted' => $this->stock_deducted,
            'notes' => $this->notes,
            'cancel_reason' => $this->cancel_reason,
            'items_count' => $this->whenCounted('items'),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'cashier' => new UserResource($this->whenLoaded('cashier')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
