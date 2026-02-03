<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UserActivityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            Log::info("User {$user->id} is active at " . now());

            if (!$user->last_seen_at || Carbon::parse($user->last_seen_at)->diffInMinutes(now()) > 0) {
                User::where('id', $user->id)->update(['last_seen_at' => now()]);
            }
        }
        return $next($request);
    }
}
