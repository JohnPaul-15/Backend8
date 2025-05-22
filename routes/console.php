<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('create:admin', function () {
    $name = $this->ask('Admin name');
    $email = $this->ask('Admin email');
    $password = $this->secret('Admin password');
    $confirmPassword = $this->secret('Confirm admin password');

    // Validate input
    $validator = Validator::make([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $confirmPassword,
    ], [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|min:8|confirmed',
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }
        return 1;
    }

    // Create admin user
    try {
        User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => 'admin'
        ]);

        $this->info('Admin user created successfully!');
    } catch (\Exception $e) {
        $this->error('Failed to create admin user: '.$e->getMessage());
        return 1;
    }

    return 0;
})->purpose('Create a new admin user');