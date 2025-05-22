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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    public function __construct()
    {
        // Optional middleware for authorization
        $this->middleware('auth:sanctum');  // Ensure the user is authenticated when borrowing a book
        $this->middleware('admin')->except(['index', 'show']);
    }

    /**
     * Display a listing of books with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::query();

        // Search functionality
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Genre filter
        if ($request->has('genre')) {
            $query->genre($request->genre);
        }

        // Availability filter
        if ($request->boolean('available')) {
            $query->available();
        }

        // Active books only
        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        // Sort by
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

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
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'isbn' => 'required|string|unique:books,isbn',
            'genre' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'total_copies' => 'required|integer|min:1',
            'publisher' => 'nullable|string|max:255',
            'publication_year' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'language' => 'nullable|string|max:50',
            'cover_image' => 'nullable|image|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $data['available_copies'] = $data['total_copies'];

            // Handle cover image upload
            if ($request->hasFile('cover_image')) {
                $path = $request->file('cover_image')->store('book-covers', 'public');
                $data['cover_image'] = $path;
            }

            $book = Book::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully',
                'data' => $book
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create book: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified book.
     */
    public function show(Book $book): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $book->load('transactions')
        ]);
    }

    /**
     * Update the specified book.
     */
    public function update(Request $request, Book $book): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'author' => 'sometimes|required|string|max:255',
            'isbn' => ['sometimes', 'required', 'string', Rule::unique('books')->ignore($book->id)],
            'genre' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'total_copies' => 'sometimes|required|integer|min:1',
            'publisher' => 'nullable|string|max:255',
            'publication_year' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'language' => 'nullable|string|max:50',
            'cover_image' => 'nullable|image|max:2048',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();

            // Handle total copies update
            if (isset($data['total_copies'])) {
                $diff = $data['total_copies'] - $book->total_copies;
                $data['available_copies'] = $book->available_copies + $diff;
            }

            // Handle cover image upload
            if ($request->hasFile('cover_image')) {
                // Delete old cover image if exists
                if ($book->cover_image) {
                    Storage::disk('public')->delete($book->cover_image);
                }
                $path = $request->file('cover_image')->store('book-covers', 'public');
                $data['cover_image'] = $path;
            }

            $book->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully',
                'data' => $book->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update book: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified book (soft delete).
     */
    public function destroy(Book $book): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Check if book has active borrowings
            if ($book->transactions()->where('status', 'borrowed')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete book with active borrowings'
                ], 422);
            }

            // Delete cover image if exists
            if ($book->cover_image) {
                Storage::disk('public')->delete($book->cover_image);
            }

            $book->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete book: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted book.
     */
    public function restore($id): JsonResponse
    {
        try {
            $book = Book::withTrashed()->findOrFail($id);
            $book->restore();

            return response()->json([
                'success' => true,
                'message' => 'Book restored successfully',
                'data' => $book
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore book: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Borrow a book
     */
    public function borrow(Request $request, Book $book)
    {
        if ($book->available <= 0) {
            return response()->json([
                'message' => 'Book is not available for borrowing'
            ], 422);
        }

        $user = $request->user();
        
        // Check if user has already borrowed this book
        if ($book->transactions()
            ->where('user_id', $user->id)
            ->where('status', 'borrowed')
            ->exists()) {
            return response()->json([
                'message' => 'You have already borrowed this book'
            ], 422);
        }

        // Create transaction
        $transaction = $book->transactions()->create([
            'user_id' => $user->id,
            'borrowed_at' => now(),
            'due_date' => now()->addDays(14), // 2 weeks borrowing period
            'status' => 'borrowed'
        ]);

        return response()->json($transaction, 201);
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

    public function available()
    {
        $books = Book::where('available_copies', '>', 0)
            ->where('status', 'active')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Available books retrieved successfully',
            'data' => $books
        ]);
    }
}
