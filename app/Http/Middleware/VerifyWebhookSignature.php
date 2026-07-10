<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Verify the Stripe/GitHub-style HMAC signature on an inbound webhook.
     * The signed value is `{timestamp}.{raw_body}`, keyed with the shared
     * secret. Rejects on missing/invalid signature or excessive clock skew.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('crm.webhook.secret');
        $timestamp = $request->header('X-CRM-Timestamp');
        $signature = $request->header('X-CRM-Signature');

        if ($secret === '' || ! $timestamp || ! $signature) {
            abort(401, 'Missing webhook signature.');
        }

        $tolerance = (int) config('crm.webhook.tolerance', 300);

        if (! is_numeric($timestamp) || abs(time() - (int) $timestamp) > $tolerance) {
            abort(401, 'Webhook timestamp outside of tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
