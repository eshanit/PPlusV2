<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'firstname' => $request->user()->firstname,
                    'lastname' => $request->user()->lastname,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role?->name,
                    'district_id' => $request->user()->district_id,
                    'is_admin' => $request->user()->isAdmin(),
                    'is_district_admin' => $request->user()->isDistrictAdmin(),
                ] : null,
            ],
        ];
    }
}
