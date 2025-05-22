<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

class ReturnBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $transaction = $this->route('transaction');
        
        if (!$transaction instanceof Transaction) {
            abort(404, 'Transaction not found');
        }

        // Safe admin check using role comparison
        return $this->user()->role === 'admin' || 
               $transaction->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // No additional fields needed for return
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $transaction = $this->route('transaction');
            
            if (!$transaction) {
                $validator->errors()->add('transaction', 'Invalid transaction');
                return;
            }

            if ($transaction->status === 'returned') {
                $validator->errors()->add(
                    'transaction', 
                    'This book has already been returned'
                );
            }
        });
    }
}