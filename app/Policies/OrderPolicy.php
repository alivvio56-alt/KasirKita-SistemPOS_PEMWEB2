<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Kasir hanya boleh mengubah pesanan yang ia catat sendiri; admin bebas. */
    public function update(User $user, Order $order): Response
    {
        return $user->isAdmin() || $order->user_id === $user->id
            ? Response::allow()
            : Response::deny('Anda hanya dapat mengubah pesanan yang Anda catat sendiri.');
    }

    /**
     * Pembatalan pesanan yang stoknya sudah dipotong (diproses/siap) hanya
     * boleh dilakukan admin, untuk mencegah penyalahgunaan oleh kasir.
     */
    public function changeStatus(User $user, Order $order, OrderStatus $target): Response
    {
        if ($target === OrderStatus::Cancelled
            && in_array($order->status, [OrderStatus::Processing, OrderStatus::Ready], true)
            && ! $user->isAdmin()) {
            return Response::deny('Hanya admin yang dapat membatalkan pesanan yang sudah diproses.');
        }

        return Response::allow();
    }

    public function pay(User $user, Order $order): bool
    {
        return true;
    }

    public function delete(User $user, Order $order): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny('Hanya admin yang dapat menghapus pesanan.');
        }

        return in_array($order->status, [OrderStatus::Pending, OrderStatus::Cancelled], true)
            ? Response::allow()
            : Response::deny('Hanya pesanan berstatus Menunggu atau Dibatalkan yang dapat dihapus. Pesanan lain tersimpan sebagai riwayat transaksi.');
    }
}
