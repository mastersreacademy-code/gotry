<?php
namespace App\Http\Middleware;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! User::whereKey($request->user()->id)->where('is_active',true)->exists()) {
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email'=>'Your account is unavailable. Contact an administrator.']);
        }
        return $next($request);
    }
}
