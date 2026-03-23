<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message = 'Error', int $code = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }
}
