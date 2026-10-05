<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class Controller
{
    /**
     * Response sukses yang konsisten: { success, message, data, [links, meta] }.
     */
    protected function respond(JsonResource|ResourceCollection|array|null $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            return $data->additional(['success' => true, 'message' => $message])
                ->response()
                ->setStatusCode($status);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
