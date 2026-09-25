<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use App\Models\ApiToken;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Auth::viaRequest('application-token', function ($request) {
            if (!$request->header('Authorization')) {
                return $request->hasSession() ? Auth::guard('web')->user() : null;
            }
            $token = ApiToken::resolve($request->bearerToken());
            $user = $token ? $token->user() : null;
            if ($user) {
                $token->forceFill(['last_used_at' => now()])->save();
                $request->attributes->set('api_token', $token);
            }

            return $user;
        });

        Gate::define('manage.users', function($user){
            return $user->isAdmin();
        });

        Gate::define('show.user', function($admin, $user){
            return (($admin->isManager() || $admin->isAdmin()) && $user->company_id == $admin->company_id);
        });
    }
}
