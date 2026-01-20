<?php

namespace App\Http\Traits;

use App\Http\Resources\Resources\UserResource;
use Illuminate\Http\JsonResponse;

trait ApiResponseHelper
{
    /**
     * @param mixed $data
     * @param string $name
     * @param int $code
     * @param string|null $message
     * @return JsonResponse
     */
    public function successResponse(
        mixed   $data,
        string  $name = 'data',
        int     $code = 200,
        ?string $message = null
    ): JsonResponse {
        $response = [
            'success' => true,
            $name => $data,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }

    /**
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function errorResponse(
        string $message,
        int    $code = 400
    ): JsonResponse {
        // Безопасная обработка ошибок - не раскрываем внутренние детали
        $safeMessage = $this->sanitizeErrorMessage($message);

        return response()->json([
            'success' => false,
            'message' => $safeMessage,
        ], $code);
    }

    /**
     * @param array $authData ['user' => User, 'token' => string]
     * @param int $code
     * @param string|null $message
     * @return JsonResponse
     */
    public function authResponse(
        array $authData,
        int $code = 200,
        ?string $message = null
    ): JsonResponse {
        return $this->successResponse([
            'user' => new UserResource($authData['user']),
            'token' => $authData['token'],
        ], 'data', $code, $message);
    }

    /**
     * @param string $message
     * @return string
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Убираем пути к файлам
        $message = preg_replace('/\/[^\s]+\.php/', '[file]', $message);

        // Убираем номера строк
        $message = preg_replace('/line \d+/', 'line [hidden]', $message);

        // Убираем стек трейсы
        $message = preg_replace('/Stack trace:.*$/s', '[stack trace hidden]', $message);

        // Убираем информацию о базе данных
        $message = preg_replace('/SQLSTATE\[.*?\]/', '[database error]', $message);

        // Убираем пути к классам
        $message = preg_replace('/App\\\\[^\\s]+/', '[class]', $message);

        return $message;
    }
}


