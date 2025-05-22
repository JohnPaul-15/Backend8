<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'genre',
        'description',
        'total_copies', 
        'available_copies',
    ];

    protected $casts = [
        'total_copies' => 'integer',
        'available_copies' => 'integer'
    ];

    /**
     * Relationship with transactions
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Relationship with current borrowers
     */
    public function currentBorrowers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'transactions')
            ->wherePivot('status', 'borrowed')
            ->withPivot(['borrowed_date', 'due_date', 'returned_date']);
    }

    /**
     * Scope for available books
     */
    public function scopeAvailable($query)
{
    return $query->where('available_copies', '>', 0);
}

    /**
     * Scope for genre filter
     */
    public function scopeGenre(Builder $query, string $genre): Builder
    {
        return $query->where('genre', $genre);
    }

    /**
     * Scope for search functionality
     */
    public function scopeSearch($query, $search)
{
    return $query->where(function($q) use ($search) {
        $q->where('title', 'like', "%{$search}%")
          ->orWhere('author', 'like', "%{$search}%")
          ->orWhere('genre', 'like', "%{$search}%");
    });
}

    /**
     * Check if book is available
     */
    public function isAvailable(): bool
    {
        return $this->available_copies > 0;
    }

    /**
     * Borrow this book
     */
    public function borrow(int $userId): Transaction
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('No available copies of this book');
        }

        if ($this->isBorrowedByUser($userId)) {
            throw new \RuntimeException('You already have this book borrowed');
        }

        return DB::transaction(function () use ($userId) {
            $this->decrement('available_copies');
            
            return $this->transactions()->create([
                'user_id' => $userId,
                'borrowed_date' => now(),
                'due_date' => now()->addWeeks(2),
                'status' => 'borrowed'
            ]);
        });
    }

    /**
     * Check if user has this book borrowed
     */
    public function isBorrowedByUser(int $userId): bool
    {
        return $this->transactions()
            ->where('user_id', $userId)
            ->where('status', 'borrowed')
            ->exists();
    }

    /**
     * Get borrowed copies count
     */
    public function borrowedCopiesCount(): int
    {
        return $this->transactions()
            ->where('status', 'borrowed')
            ->count();
    }

    /**
     * Get overdue copies count
     */
    public function overdueCopiesCount(): int
    {
        return $this->transactions()
            ->where('status', 'borrowed')
            ->where('due_date', '<', now())
            ->count();
    }

    /**
     * Get available copies percentage
     */
    public function availabilityPercentage(): float
    {
        if ($this->total_copies === 0) {
            return 0;
        }

        return round(($this->available_copies / $this->total_copies) * 100, 2);
    }

    /**
     * Get cover image URL
     */
    public function getCoverUrlAttribute(): string
    {
        if ($this->cover_image) {
            return Storage::url($this->cover_image);
        }

        return asset('images/Books.jpg');
    }

    /**
     * Get short description (first 100 chars)
     */
    public function getShortDescriptionAttribute(): string
    {
        return Str::limit($this->description, 100);
    }
}