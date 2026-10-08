<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApproved
{
    /**
     * Block pending/rejected merchants and drivers until an admin approves them.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && in_array($user->role->value, [UserRole::Merchant->value, UserRole::Driver->value], true)
            && $user->status !== UserStatus::Active
        ) {
            return response()->view('auth.pending', [], 403);
        }

        return $next($request);
    }
}
