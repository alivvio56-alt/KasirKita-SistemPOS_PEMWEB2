<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);
        $f = $request->validate([
            'search' => 'nullable|string|max:100',
            'role' => 'nullable|in:admin,kasir',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $users = User::query()
            ->withCount('orders')
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($f['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')
            ->paginate($f['per_page'] ?? 15)
            ->withQueryString();

        return $this->respond(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);
        $user = User::create(array_filter($request->validated(), fn ($v) => $v !== null));

        return $this->respond(new UserResource($user), 'Pengguna berhasil dibuat.', 201);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return $this->respond(new UserResource($user->loadCount('orders')));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);
        $data = array_filter($request->validated(), fn ($v, $k) => $k !== 'password' || filled($v), ARRAY_FILTER_USE_BOTH);
        $user->update($data);

        if (array_key_exists('is_active', $data) && ! $user->is_active) {
            $user->tokens()->delete(); // paksa logout akun yang dinonaktifkan
        }

        return $this->respond(new UserResource($user), 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        if ($user->orders()->exists()) {
            $user->update(['is_active' => false]);
            $user->tokens()->delete();

            return $this->respond(new UserResource($user), 'Pengguna memiliki riwayat transaksi, sehingga dinonaktifkan (bukan dihapus).');
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->respond(null, 'Pengguna berhasil dihapus.');
    }
}
