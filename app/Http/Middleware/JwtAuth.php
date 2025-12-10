<?php

namespace App\Http\Middleware;

use App\Services\Auth\JwtAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuth
{
    protected JwtAuthService $jwtService;

    public function __construct(JwtAuthService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->jwtService->getUserFromRequest();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        auth()->setUser($user);

        return $next($request);
    }
}

