<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BorrowerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('/status', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Profile management
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::get('/profile', [UserController::class, 'showProfile']);

    // Book routes
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{book}', [BookController::class, 'show']);
    Route::post('/books/{book}/borrow', [BookController::class, 'borrow']);

    // Transaction routes
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions/{transaction}/return', [TransactionController::class, 'return']);
    Route::get('/transactions/borrowed', [TransactionController::class, 'getUserBorrowedBooks']);

    // Admin routes
    Route::middleware('admin')->group(function () {
        // Dashboard stats
        Route::get('/admin/dashboard-stats', [UserController::class, 'getDashboardStats']);

        // Book management
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{book}', [BookController::class, 'update']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);
        Route::post('/books/{id}/restore', [BookController::class, 'restore']);

        // User management
        Route::get('/admin/users', [UserController::class, 'index']);
        Route::get('/admin/users/{user}', [UserController::class, 'show']);
        Route::put('/admin/users/{user}', [UserController::class, 'update']);
        Route::delete('/admin/users/{user}', [UserController::class, 'destroy']);

        // Transaction management
        Route::get('/admin/transactions/overdue', [TransactionController::class, 'getOverdueBooks']);
    });

    // Borrower routes
    Route::apiResource('borrowers', BorrowerController::class);
    Route::get('/books/available', [BookController::class, 'available']);
});
