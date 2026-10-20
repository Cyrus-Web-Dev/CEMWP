<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data yang dikirim ke SETIAP halaman Vue ($page.props.*).
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            // Hanya diisi di halaman etalase (rute shop.*)
            'shop' => fn () => $request->routeIs('shop.*') ? [
                'name' => config('cemwp.sales.store_name'),
                'customer' => ($c = auth('customer')->user()) ? ['id' => $c->id, 'name' => $c->name, 'email' => $c->email] : null,
                'cartCount' => (int) array_sum($request->session()->get('shop.cart', [])),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'note' => fn () => $request->session()->get('note'),
            ],
        ];
    }
}
