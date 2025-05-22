<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
{
    return [
        'title' => 'required|string|max:255',
        'author' => 'required|string|max:255',
        
        'genre' => 'required|string|max:100',
        'description' => 'nullable|string',
        'total_copies' => 'required|integer|min:1',
    ];
}

public function messages()
{
    return [
        'isbn.unique' => 'This ISBN already exists',
        'total_copies.min' => 'There must be at least 1 copy',
    ];
}
}