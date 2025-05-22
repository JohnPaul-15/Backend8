<?php

namespace App\Http\Controllers;
//
use App\Models\Transaction;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['book', 'user']);

        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->latest()->paginate(10);
        return response()->json($transactions);
    }

    public function show(Transaction $transaction)
    {
        if ($transaction->user_id !== request()->user()->id && request()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($transaction->load(['book', 'user']));
    }

    public function return(Transaction $transaction)
    {
        if ($transaction->status !== 'borrowed') {
            return response()->json([
                'message' => 'This book has already been returned'
            ], 422);
        }

        if ($transaction->user_id !== request()->user()->id && request()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($transaction) {
            $transaction->update([
                'returned_at' => now(),
                'status' => 'returned'
            ]);
        });

        return response()->json($transaction->fresh(['book', 'user']));
    }

    public function getUserBorrowedBooks(Request $request)
    {
        $transactions = Transaction::with('book')
            ->where('user_id', $request->user()->id)
            ->where('status', 'borrowed')
            ->latest()
            ->get();

        return response()->json($transactions);
    }

    public function getOverdueBooks()
    {
        $transactions = Transaction::with(['book', 'user'])
            ->where('status', 'borrowed')
            ->where('due_date', '<', now())
            ->latest()
            ->get();

        return response()->json($transactions);
    }

    public function returnBook(Request $request, $transactionId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $transaction = Transaction::with(['book', 'user'])
                ->find((int)$transactionId);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction record not found',
                ], 404);
            }

            // Verify transaction status
            if ($transaction->status === 'returned') {
                return response()->json([
                    'success' => false,
                    'message' => 'This book was already returned',
                    'returned_at' => $transaction->returned_at->toDateTimeString()
                ], 400);
            }

            // Verify ownership
            if ($transaction->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to return this book',
                ], 403);
            }

            // Process return
            $transaction->update([
                'status' => 'returned',
                'returned_at' => now(),
            ]);

            // Update book availability
            Book::where('id', $transaction->book_id)
                ->increment('available_copies');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book returned successfully',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Return failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Return processing failed',
            ], 500);
        }
    }
}