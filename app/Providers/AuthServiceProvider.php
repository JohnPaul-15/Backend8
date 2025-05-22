<?php
use Illuminate\Support\Facades\Gate;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    
   

public function boot()
{
    $this->registerPolicies();

    // In AuthServiceProvider.php
Gate::define('manage-books', function ($user) {
    return true; // Temporarily allow all authenticated users
});

    Gate::define('manage-dashboard', function ($user) {
        return $user->role === 'admin';
    });

    Gate::define('manage-users', function ($user) {
        return $user->role === 'admin';
    });

    Gate::define('borrow-books', function ($user) {
        return $user->role === 'user';
    });
}
}
