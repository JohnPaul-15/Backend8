<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Transaction;
use App\Notifications\BookDueSoonNotification;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Notify users about due books
        $schedule->call(function () {
            $transactions = Transaction::where('status', 'borrowed')
                ->where('due_date', '<=', now()->addDays(3))
                ->whereNull('notified_at')
                ->with(['user', 'book'])
                ->get();

            foreach ($transactions as $transaction) {
                $transaction->user->notify(new BookDueSoonNotification(
                    $transaction->book, 
                    $transaction->due_date
                ));
                
                $transaction->update(['notified_at' => now()]);
            }
        })->daily();
    }
}