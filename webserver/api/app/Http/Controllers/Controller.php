<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function error(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['error' => $message] + $extra, $status);
    }
}
