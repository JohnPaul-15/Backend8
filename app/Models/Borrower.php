<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Borrower extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'borrowed_books',
        'status',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
        'borrowed_books' => 'integer',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function activeTransactions()
    {
        return $this->transactions()->where('status', 'borrowed');
    }

    public function updateStatus()
    {
        $overdueTransactions = $this->activeTransactions()
            ->where('due_date', '<', now())
            ->exists();

        $this->status = $overdueTransactions ? 'overdue' : 'active';
        $this->save();
    }
} 