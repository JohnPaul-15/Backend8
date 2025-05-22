<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
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
            'genre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_copies' => 'required|integer|min:1',
        ];
    }
}
