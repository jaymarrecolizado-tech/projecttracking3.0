<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /** Keys that are never persisted, matched case-insensitively anywhere in the payload. */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        '_token', 'token', 'remember_token',
        'secret', 'authorization', 'cookie', 'csrf',
    ];

    /** Hard ceiling so bulk imports / huge forms can't bloat the audit table. */
    private const MAX_PAYLOAD_KEYS = 40;

    private const MAX_VALUE_LENGTH = 500;

    public function handle(Request $request, Closure $next): Response
    {
        // One id per HTTP write, stamped before the controller runs so model
        // observers fired mid-request can share it (see SiteObserver).
        $requestId = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            ? (string) Str::uuid()
            : null;
        if ($requestId !== null) {
            Context::add('audit_request_id', $requestId);
        }

        $response = $next($request);

        if ($requestId !== null) {
            // Only log if user is authenticated AND it's not an auth route (login, register, etc.)
            $isAuthRoute = in_array($request->path(), ['login', 'logout', 'register', 'forgot-password', 'reset-password']);
            if ($request->user() && ! str_starts_with($request->path(), '_') && ! $isAuthRoute) {
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => $request->method().' '.$request->path(),
                    'auditable_type' => 'general', // Fallback for generic HTTP actions
                    'auditable_id' => null,
                    'old_values' => null,
                    'new_values' => $this->sanitize($request->except(['password', 'password_confirmation', '_token'])) + ['request_id' => $requestId],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        return $response;
    }

    /**
     * Whitelist-minded scrubbing: drop credential-shaped keys entirely, truncate
     * long values, and cap total keys so a runaway form can't flood the table.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    private function sanitize(array $payload): array
    {
        $cleaned = [];
        foreach (array_slice($payload, 0, self::MAX_PAYLOAD_KEYS, true) as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $cleaned[$key] = '[REDACTED]';

                continue;
            }
            $cleaned[$key] = is_array($value)
                ? $this->sanitize($value)
                : mb_substr((string) $value, 0, self::MAX_VALUE_LENGTH);
        }

        return $cleaned;
    }

    private function isSensitive(string $key): bool
    {
        foreach (self::REDACTED_KEYS as $needle) {
            if (str_contains(strtolower($key), $needle)) {
                return true;
            }
        }

        return false;
    }
}
