<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);
        // Hosting di belakang proxy/CDN (Cloudflare, load balancer)
        $middleware->trustProxies(at: '*');
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pelanggaran aturan bisnis adalah kondisi normal, tidak perlu dicatat di log
        $exceptions->dontReport(BusinessException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Format error yang konsisten untuk seluruh API:
        // { "success": false, "message": "...", "errors": { ... } }
        $json = fn (string $message, int $status, array $errors = []) => response()->json(
            array_filter(['success' => false, 'message' => $message, 'errors' => $errors ?: null], fn ($v) => $v !== null),
            $status,
        );

        $exceptions->render(function (Throwable $e, Request $request) use ($json) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null; // biarkan halaman web memakai handler bawaan
            }

            return match (true) {
                $e instanceof BusinessException => $json($e->getMessage(), $e->status(), $e->errors()),
                $e instanceof ValidationException => $json(
                    $e->status === 401 ? $e->getMessage() : 'Data yang dikirim tidak valid.',
                    $e->status,
                    $e->errors(),
                ),
                $e instanceof AuthenticationException => $json('Tidak terautentikasi. Silakan login terlebih dahulu.', 401),
                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => $json(
                    ($e->getMessage() && $e->getMessage() !== 'This action is unauthorized.')
                        ? $e->getMessage()
                        : 'Anda tidak memiliki hak akses untuk tindakan ini.',
                    403,
                ),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => $json('Data atau endpoint tidak ditemukan.', 404),
                $e instanceof MethodNotAllowedHttpException => $json('Metode HTTP tidak diizinkan untuk endpoint ini.', 405),
                $e instanceof ThrottleRequestsException => $json('Terlalu banyak permintaan. Coba lagi nanti.', 429),
                $e instanceof HttpExceptionInterface => $json($e->getMessage() ?: 'Terjadi kesalahan.', $e->getStatusCode()),
                default => $json(
                    config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server.',
                    500,
                ),
            };
        });
    })->create();
