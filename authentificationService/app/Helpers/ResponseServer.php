<?php

namespace App\Helpers;


use Illuminate\Http\JsonResponse;

class ResponseServer
{

    public static function unauthorization(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthorized',
        ], 400);
    }
}
