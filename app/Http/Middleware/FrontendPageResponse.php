<?php

namespace App\Http\Middleware;

use App\Support\FrontendPagePayload;
use Closure;
use Illuminate\Http\Request;

class FrontendPageResponse
{
    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route()?->getName();
        if (!$request->isMethod('GET') || !isset(FrontendPagePayload::ROUTES[$routeName])) {
            return $next($request);
        }

        $capture = new FrontendPagePayload($routeName);
        $request->attributes->set(FrontendPagePayload::class, $capture);
        // The normal controller and every authorization middleware run before a DTO exists.
        $response = $next($request);

        if ($response->isSuccessful() && $request->header('X-Obsidian-Page') === '1' && $request->wantsJson()) {
            $payload = $capture->envelope($request->getRequestUri());
            $response = FrontendPagePayload::workspaceEnabled() && FrontendPagePayload::supportsPath($request->getPathInfo()) && $payload !== null
                ? response()->json($payload)
                : response()->json(['message' => 'This page requires document navigation.'], 409);
        }

        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary(['Accept', 'X-Obsidian-Page'], false);

        return $response;
    }
}
