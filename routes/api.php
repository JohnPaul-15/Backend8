<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/status', function () {
    return response()->json([
        'status' => 'operational',
        'version' => '1.0.0',
        'timestamp' => now()->toDateTimeString()
    ]);
});

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/change-password', [UserController::class, 'changePassword'])->middleware('auth:sanctum');
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // User profile
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile/update', [UserController::class, 'updateProfile']);
    Route::get('/user/borrowed-books', [UserController::class, 'borrowedBooks']);

    // Books
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::post('/books/{id}/borrow', [BookController::class, 'borrow']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::post('/transactions/{id}/return', [TransactionController::class, 'returnBook']);
    Route::get('/transactions/stats', [TransactionController::class, 'stats']);

    // Admin routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard-stats', [AdminController::class, 'dashboardStats']);

        // Books management
        Route::get('/books', [BookController::class, 'adminIndex']);
        Route::post('/books', [BookController::class, 'store']);
        Route::get('/books/{id}', [BookController::class, 'adminShow']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);

        // Users management
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // Transactions management
        Route::get('/transactions', [TransactionController::class, 'adminIndex']);
        Route::post('/transactions/{id}/mark-returned', [TransactionController::class, 'markAsReturned']);
    });
});
