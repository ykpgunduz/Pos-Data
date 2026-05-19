<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data Service güvenlik katmanı.
 * Gelen isteklerin DATA_SERVICE_TOKEN ile doğrulanmasını sağlar.
 */
class VerifyServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.data_service.token');

        if (empty($expected)) {
            return response()->json([
                'success' => false,
                'message' => 'Service token not configured.',
            ], 500);
        }

        $provided = $request->bearerToken();

        if (!$provided || !hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized — invalid service token.',
            ], 401);
        }

        return $next($request);
    }
}
