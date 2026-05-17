<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch') || $request->isMethod('delete')) {
            if ($request->user() && !str_starts_with($request->path(), '_')) {
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'action' => $request->method() . ' ' . $request->path(),
                    'auditable_type' => null,
                    'auditable_id' => null,
                    'old_values' => null,
                    'new_values' => $request->except(['password', 'password_confirmation', '_token']),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        return $response;
    }
}
