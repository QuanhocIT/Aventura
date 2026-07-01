<?php

namespace App\Http\Requests\Expenses;

use App\Http\Requests\Concerns\HasFileUploadRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    use HasFileUploadRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['owner', 'manager']);
    }

    public function rules(): array
    {
        return [
            'category_id'  => ['nullable', 'exists:expense_categories,id'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'invoice'      => $this->documentRules(),
        ];
    }
}
