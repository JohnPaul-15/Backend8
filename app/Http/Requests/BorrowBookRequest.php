<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Book;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class BorrowBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check(); // Only authenticated users can borrow
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
                function ($attribute, $value, $fail) {
                    $book = Book::find($value);
                    
                    if (!$book) {
                        return $fail('The selected book does not exist');
                    }
                    
                    if ($book->available_copies < 1) {
                        $fail('No available copies of this book');
                    }
                },
                function ($attribute, $value, $fail) {
                    $alreadyBorrowed = Transaction::where('user_id', Auth::id())
                        ->where('book_id', $value)
                        ->where('status', 'borrowed')
                        ->exists();
                    
                    if ($alreadyBorrowed) {
                        $fail('You have already borrowed this book');
                    }
                }
            ]
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'book_id.required' => 'Please select a book to borrow',
            'book_id.integer' => 'Book ID must be a number',
            'book_id.exists' => 'The selected book does not exist',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'book_id' => (int) $this->book_id // Ensure book_id is integer
        ]);
    }
}