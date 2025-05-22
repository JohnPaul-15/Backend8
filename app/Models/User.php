<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;

class User extends Authenticatable implements CanResetPassword
{
    use HasApiTokens, HasFactory, Notifiable, CanResetPasswordTrait;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_image'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function currentlyBorrowedBooks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'transactions')
            ->wherePivot('status', 'borrowed')
            ->withPivot(['borrowed_date', 'due_date']);
    }

    public function overdueBooks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'transactions')
            ->wherePivot('status', 'borrowed')
            ->wherePivot('due_date', '<', now());
    }
    
    public function canBorrowBook(Book $book): bool
    {
        return !$this->transactions()
            ->where('book_id', $book->id)
            ->where('status', 'borrowed')
            ->exists();
    }
    public function can($ability, $arguments = [])
    {
        if ($this->role === 'admin') {
            return true;
        }
        
        return parent::can($ability, $arguments);
    }
}
