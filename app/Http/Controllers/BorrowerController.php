<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\Book;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BorrowerController extends Controller
{
    public function index()
    {
        $borrowers = Borrower::with(['transactions' => function ($query) {
            $query->where('status', 'borrowed');
        }])->get();

        return response()->json([
            'success' => true,
            'message' => 'Borrowers retrieved successfully',
            'data' => $borrowers
        ]);
    }

    public function store(Request $request)
    {
        Log::info('Creating new borrower', ['request_data' => $request->all()]);

        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:borrowers,email',
                'borrowed_book_id' => 'nullable|exists:books,id'
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create the borrower first
            $borrower = new Borrower();
            $borrower->name = $request->name;
            $borrower->email = $request->email;
            $borrower->borrowed_books = 0;
            $borrower->status = 'active';
            $borrower->save();

            Log::info('Borrower created successfully', ['borrower_id' => $borrower->id]);

            // If book borrowing is requested, handle it separately
            if ($request->borrowed_book_id) {
                try {
                    DB::beginTransaction();

                    $book = Book::findOrFail($request->borrowed_book_id);
                    
                    if ($book->available_copies <= 0) {
                        throw new \Exception('Book is not available for borrowing');
                    }

                    // Create transaction
                    $transaction = new Transaction();
                    $transaction->user_id = auth()->id();
                    $transaction->book_id = $book->id;
                    $transaction->borrower_id = $borrower->id;
                    $transaction->borrowed_at = now();
                    $transaction->due_date = now()->addDays(14);
                    $transaction->status = 'borrowed';
                    $transaction->save();

                    // Update book availability
                    $book->available_copies--;
                    $book->save();
                    
                    // Update borrower's borrowed books count
                    $borrower->borrowed_books++;
                    $borrower->save();

                    DB::commit();
                    Log::info('Book borrowing processed successfully', [
                        'borrower_id' => $borrower->id,
                        'book_id' => $book->id,
                        'transaction_id' => $transaction->id
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error processing book borrowing', [
                        'error' => $e->getMessage(),
                        'borrower_id' => $borrower->id,
                        'book_id' => $request->borrowed_book_id
                    ]);
                    
                    // Return success for borrower creation but with a warning about the book
                    return response()->json([
                        'success' => true,
                        'message' => 'Borrower created but failed to process book borrowing: ' . $e->getMessage(),
                        'data' => $borrower
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Borrower created successfully',
                'data' => $borrower->load('transactions')
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating borrower', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create borrower: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Borrower $borrower)
    {
        return response()->json([
            'success' => true,
            'message' => 'Borrower retrieved successfully',
            'data' => $borrower->load('transactions.book')
        ]);
    }

    public function update(Request $request, Borrower $borrower)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:borrowers,email,' . $borrower->id,
            'status' => 'sometimes|required|in:active,overdue'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $borrower->update($request->only(['name', 'email', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'Borrower updated successfully',
            'data' => $borrower
        ]);
    }

    public function destroy(Borrower $borrower)
    {
        if ($borrower->borrowed_books > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete borrower with active borrowings'
            ], 400);
        }

        $borrower->delete();

        return response()->json([
            'success' => true,
            'message' => 'Borrower deleted successfully'
        ]);
    }
} 