<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        if ($request->is('/') || $request->is('explore*') || $request->is('platform*') || $request->is('login') || $request->is('register') || $request->is('security*')) {
            $this->rootView = 'platform';
        }
        return [
            ...parent::share($request),
            'csrfToken' => csrf_token(),
            'auth.user' => $request->user() ? [
                ...$request->user()->only(['id', 'name', 'email']),
                'roles'       => $request->user()->getRoleNames()->values()->toArray(),
                'permissions' => $request->user()->getAllPermissions()->pluck('name')->values()->toArray(),
            ] : null,
            'aiChatEnabled' => (bool) config('services.literouter.enabled')
                && filled(config('services.literouter.key')),
            'feedbackNewCount' => fn () => auth()->check() && auth()->user()->hasRole('admin')
                ? \App\Models\Feedback::new()->count()
                : 0,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ];
    }
}
