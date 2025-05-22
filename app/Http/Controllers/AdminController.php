<?php

namespace App\Http\Controllers;


use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function dashboardStats()
{
    try {
        $stats = [
            'books_count' => Book::count(),
            'users_count' => User::where('role', '!=', 'admin')->count(),
            'transactions_count' => Transaction::where('status', 'borrowed')->count(),
            'overdue_count' => Transaction::where('due_date', '<', now())
                ->where('status', 'borrowed')
                ->count(),
            'recent_transactions' => Transaction::with(['user', 'book'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'user_name' => $transaction->user->name ?? 'Deleted User',
                        'book_title' => $transaction->book->title ?? 'Deleted Book',
                        'borrowed_date' => $transaction->borrowed_date,
                        'due_date' => $transaction->due_date,
                        'status' => $transaction->due_date < now() && $transaction->status === 'borrowed' 
                            ? 'overdue' 
                            : $transaction->status,
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to load dashboard statistics',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get paginated list of books
     */
    public function getBooks(Request $request)
    {
        try {
            $query = Book::query();

            // Search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('author', 'like', "%{$search}%");
                      
                });
            }

            // Sorting
            if ($request->get('sort') === 'latest') {
                $query->latest();
            }

            // Pagination
            $perPage = $request->get('per_page', 5); // Changed default to 5
            $books = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $books->items()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch books',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get paginated list of users
     */
    public function getUsers(Request $request)
{
    try {
         $query = User::where('role', '!=', 'admin'); // Exclude admin users
        //$query = User::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting
        if ($request->get('sort') === 'latest') {
            $query->latest();
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch users',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get paginated list of transactions
     */
    public function getTransactions(Request $request)
{
    $status = $request->input('status');
    $search = $request->input('search');
    
    $query = Transaction::with(['user', 'book'])
        ->when($status, function($query) use ($status) {
            return $query->where('status', $status);
        })
        ->when($search, function($query) use ($search) {
            return $query->where(function($q) use ($search) {
                $q->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhereHas('book', function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                });
            });
        })
        ->orderBy('created_at', 'desc');
    
    $transactions = $query->paginate(10);
    
    return response()->json([
        'data' => $transactions->items(),
        'meta' => [
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
        ]
    ]);
}

    /**
     * Delete a user (admin only)
     */
    public function deleteUser(User $user)
    {
        try {
            DB::beginTransaction();
            
            // Prevent deleting admin users
            if ($user->role === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete admin users'
                ], 403);
            }

            $user->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}