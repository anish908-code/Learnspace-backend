<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseSanctumTokenFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('sanctum.token_cookie', 'learnspace_token');

        $token = $request->cookie($cookieName);

        if ($request->headers->missing('Authorization') && is_string($token) && $token !== '') {
            $token = urldecode($token);

            $request->headers->set(
                'Authorization',
                'Bearer ' . $token
            );
        }

        return $next($request);
    }
}
