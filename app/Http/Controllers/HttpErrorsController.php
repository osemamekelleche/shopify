<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HttpErrorsController extends Controller
{
    public static function sendHttpMethodNotAllowed(): JsonResponse
    {
        return self::sendHttpError(405, 'Method Not Allowed');
    }

    public static function sendHttpNotFound(): JsonResponse
    {
        return self::sendHttpError(404, 'Not Found');
    }

    public static function sendHttpUnsupportedMediaType(): JsonResponse
    {
        return self::sendHttpError(415, 'Unsupported Media Type');
    }

    public static function sendHttpBadRequest(): JsonResponse 
    {
        return self::sendHttpError(400, 'Bad Request');
    }

    private static function sendHttpError(int $errorCode, string $errorDescription): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $errorCode,
                'description' => $errorDescription
            ]
        ], $errorCode);
    }
}
