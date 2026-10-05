<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // kasir pencatat
            $table->enum('type', ['dine_in', 'take_away', 'preorder'])->default('take_away');
            $table->enum('status', ['pending', 'processing', 'ready', 'completed', 'cancelled'])->default('pending')->index();
            $table->dateTime('pickup_at')->nullable(); // jadwal ambil untuk preorder
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid')->index();
            $table->enum('payment_method', ['cash', 'qris', 'transfer'])->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->boolean('stock_deducted')->default(false);
            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
