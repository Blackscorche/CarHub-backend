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

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']) && $request->user()) {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => $request->method() . ' ' . $request->path(),
                'entity_type' => $this->guessEntityType($request->path()),
                'entity_id' => $request->route('id') ?? $request->route('vehicle') ?? null,
                'new_value' => $request->except(['password', 'password_confirmation', 'image', 'media', 'cover_image', 'logo', 'evidence', 'photo']),
                'ip_address' => $request->ip(),
            ]);
        }

        return $response;
    }

    private function guessEntityType(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        return $segments[1] ?? $segments[0] ?? 'unknown';
    }
}
