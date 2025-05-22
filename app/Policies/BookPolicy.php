<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Book;

class BookPolicy
{
    /**
     * Determine if the user can create a book.
     */
    public function create(User $user)
    {
        return $user->isAdmin(); // Check if the user is an admin
    }

    /**
     * Determine if the user can update the book.
     */
    public function update(User $user, Book $book)
    {
        return $user->isAdmin(); // Only admins can update books
    }

    /**
     * Determine if the user can delete the book.
     */
    public function delete(User $user, Book $book)
    {
        return $user->isAdmin(); // Only admins can delete books
    }

    /**
     * Custom method to manage books.
     * This can be used as a custom permission check.
     */
    public function manage(User $user)
    {
        return $user->isAdmin(); // This will check if the user is an admin
    }
}
