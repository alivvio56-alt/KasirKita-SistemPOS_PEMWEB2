<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Registrasi akun kasir baru. Role admin hanya dapat diberikan oleh admin. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'password']),
            'role' => Role::Kasir,
            'is_active' => true,
        ]);

        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return $this->respond([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registrasi berhasil.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ])->status(401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi admin.',
            ], 403);
        }

        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return $this->respond([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->respond(new UserResource($request->user()));
    }

    /** Logout: mencabut token yang sedang dipakai. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->respond(null, 'Logout berhasil.');
    }
}
