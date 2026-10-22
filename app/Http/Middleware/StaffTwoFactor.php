<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class StaffTwoFactor {
    public function handle(Request $request, Closure $next) {
        $u=$request->user();
        $protected=$request->is('admin*') || $request->is('platform/workspace') ||
            ($request->is('platform/*') && !$request->isMethod('GET') && !$request->is('platform/places/*/reports'));
        if($u && $protected && $u->hasAnyRole(['admin','moderator','curator','editor']) && !$request->session()->get('staff_2fa')) {
            if($request->expectsJson()) return response()->json(['message'=>'Подтвердите вход в разделе «Защита аккаунта».'],403);
            return redirect('/security');
        }
        if($request->is('admin*') && $u && !$u->hasRole('admin')) abort(403);
        return $next($request);
    }
}
