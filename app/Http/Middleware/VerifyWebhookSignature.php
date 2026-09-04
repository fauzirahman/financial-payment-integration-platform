<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.mock_webhook.secret');

        // Keeping the secret empty disables verification for local demos.
        if ($secret === '') {
            return $next($request);
        }

        $signature = (string) $request->header('X-Webhook-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || !hash_equals($expected, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        return $next($request);
    }
}
