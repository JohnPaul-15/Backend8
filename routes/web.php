<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
// Add the Sanctum route before your other routes
Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
});

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to the Library Management API',
        'documentation' => [
            'version' => '1.0.0',
            'endpoints' => [
                'auth' => [
                    'POST /api/auth/register' => 'Register a new user',
                    'POST /api/auth/login' => 'Login with credentials',
                    'POST /api/auth/logout' => 'Logout (authenticated)',
                    'GET /api/auth/me' => 'Get current user info',
                ],
                'books' => [
                    'GET /api/books' => 'List all books',
                    'POST /api/books' => 'Create new book (admin)',
                    'GET /api/admin/books/{id}' => 'Get book details',
                    'PUT /api/admin/books/{id}' => 'Update book (admin)',
                    'DELETE /api/admin/books/{id}' => 'Delete book (admin)',
                    'POST /api/books/{id}/borrow' => 'Borrow a book',
                ],
                'transactions' => [
                    'GET /api/transactions' => 'List user transactions',
                    'GET /api/transactions/{id}' => 'Get transaction details',
                    'POST /api/transactions/{id}/return' => 'Return a book',
                    
                ],
                'users' => [
                    'GET /api/admin/users' => 'List users (admin)',
                    'GET /api/admin/users/{id}' => 'Get user details (admin)',
                    'PUT /api/admin/users/{id}' => 'Update user (admin)',
                    'DELETE /api/admin/users/{id}' => 'Delete user (admin)',
                ]
            ]
        ]
    ]);
});
// Health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'healthy']);
});