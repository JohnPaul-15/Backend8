<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'borrowed_at',
        'due_date',
        'returned_at',
        'status'
    ];

    protected $dates = [
        'borrowed_at',
        'due_date',
        'returned_at'
    ];

    /**
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Scopes
     */

    /**
     * Scope a query to only include currently borrowed books
     */
    public function scopeBorrowed(Builder $query): Builder
    {
        return $query->where('status', 'borrowed')
                     ->whereNull('returned_at');
    }

    /**
     * Scope a query to only include overdue transactions
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->borrowed()
                     ->whereDate('due_date', '<', now());
    }

    /**
     * Scope a query to only include returned books
     */
    public function scopeReturned(Builder $query): Builder
    {
        return $query->whereNotNull('returned_at');
    }

    /**
     * Helpers
     */

    /**
     * Check if this transaction is currently overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'borrowed'
            && $this->due_date instanceof Carbon
            && $this->due_date->isPast()
            && is_null($this->returned_at);
    }
}
