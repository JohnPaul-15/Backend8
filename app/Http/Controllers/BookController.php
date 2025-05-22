<?php

namespace App\Http\Controllers;
//
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;

class BookController extends Controller
{
    public function __construct()
    {
        // Optional middleware for authorization
        $this->middleware('auth:sanctum');  // Ensure the user is authenticated when borrowing a book
    }

    /**
     * Display a listing of books with optional filters.
     */
    public function index(Request $request)
    {
        $query = Book::query();
        
        if ($request->has('search')) {
            $query->search($request->search);
        }
        
        if ($request->has('available') && $request->available) {
            $query->available();
        }
        
        $perPage = $request->get('per_page', 15);
        $books = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $books->items(),
            'meta' => [
                'total' => $books->total(),
                'current_page' => $books->currentPage(),
                'per_page' => $books->perPage(),
                'last_page' => $books->lastPage(),
            ]
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = Book::query();
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                  ->orWhere('author', 'like', '%'.$request->search.'%')
                  
                  ->orWhere('genre', 'like', '%'.$request->search.'%');
            });
        }

        // Genre filter
        if ($request->has('genre') && $request->genre !== '') {
            $query->where('genre', $request->genre);
        }
        
        // Availability filter
        if ($request->has('available') && $request->available === 'true') {
            $query->where('available_copies', '>', 0);
        }

        $perPage = $request->get('per_page', 10);
        $books = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $books->items(),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ]
        ]);
    }

    /**
     * Store a newly created book.
     */
    public function store(StoreBookRequest $request)
    {
        try {
            $validated = $request->validated();

            $book = Book::create([
                'title' => $validated['title'],
                'author' => $validated['author'],
                'genre' => $validated['genre'],
                'description' => $validated['description'] ?? null,
                'total_copies' => $validated['total_copies'],
                'available_copies' => $validated['total_copies'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully',
                'data' => $book
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create book: ' . $e->getMessage(),
                'errors' => $e instanceof \Illuminate\Validation\ValidationException ? $e->errors() : null
            ], 500);
        }
    }

    /**
     * Display the specified book.
     */
    public function show(Book $book)
    {
        return response()->json([
            'success' => true,
            'data' => $book
        ]);
    }

    /**
     * Update the specified book.
     */
    public function update(UpdateBookRequest $request, Book $book)
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Calculate new available copies if total copies changed
            if ($validated['total_copies'] != $book->total_copies) {
                $borrowedCount = $book->transactions()
                    ->where('status', 'borrowed')
                    ->count();

                if ($validated['total_copies'] < $borrowedCount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total copies cannot be less than currently borrowed copies ('.$borrowedCount.')'
                    ], 422);
                }

                $validated['available_copies'] = $validated['total_copies'] - $borrowedCount;
            }

            $book->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully',
                'data' => $book
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Book update failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update book: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified book.
     */
    public function destroy(Book $book)
    {
        try {
            $borrowedCount = $book->transactions()
                ->where('status', 'borrowed')
                ->count();

            if ($borrowedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete book with active borrowings ('.$borrowedCount.' copies currently borrowed)'
                ], 422);
            }

            $book->delete();

            return response()->json([
                'success' => true,
                'message' => 'Book deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete book: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Borrow a book
     */
    public function borrow(Book $book): JsonResponse
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        // Check if book is available
        if ($book->available_copies < 1) {
            return $this->errorResponse('No available copies of this book', 400);
        }

        DB::beginTransaction();
        try {
            // Decrement available copies
            $book->decrement('available_copies');
            
            // Create transaction record
            $transaction = $book->transactions()->create([
                'user_id' => Auth::id(),
                'borrowed_at' => now(),  // Explicitly set the borrowed_at field
                'due_date' => now()->addWeeks(2), // 2 weeks borrowing period
                'status' => 'borrowed'
            ]);
            
            DB::commit();
            
            return $this->successResponse([
                'book' => $book->fresh(),
                'transaction' => $transaction
            ], 'Book borrowed successfully');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to borrow book: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Return a borrowed book
     */
    public function returnBook(Book $book): JsonResponse
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        // Find the active transaction for the current user and book
        $transaction = $book->transactions()
                            ->where('user_id', Auth::id())
                            ->where('status', 'borrowed')
                            ->whereNull('returned_at')
                            ->first();

        if (!$transaction) {
            return $this->errorResponse('No active borrowing found for this book', 400);
        }

        DB::beginTransaction();
        try {
            // Mark the book as returned in the transaction
            $transaction->update([
                'returned_at' => now(),
                'status' => 'returned'
            ]);

            // Increment available copies of the book
            $book->increment('available_copies');

            DB::commit();

            return $this->successResponse([
                'book' => $book->fresh(),
                'transaction' => $transaction
            ], 'Book returned successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to return book: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Return a standard success JSON response.
     */
    protected function successResponse($data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a standard error JSON response.
     */
    protected function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
