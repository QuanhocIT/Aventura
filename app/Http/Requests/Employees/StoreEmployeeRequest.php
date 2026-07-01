<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\Concerns\HasFileUploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    use HasFileUploadRules;

    public function authorize(): bool
    {
        // Previously missing entirely (any authenticated tenant user, including
        // waiter/kitchen roles, could create employee records) — found during the
        // validation-extraction refactor and confirmed with the user as an oversight to
        // fix, matching the permission toggleEmployeeStatus() already enforces.
        return (bool) $this->user()?->can('manage_employees');
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', Rule::unique('users')->where('restaurant_id', $this->user()->restaurant_id)],
            'phone'             => ['required', 'string', 'max:20'],
            'citizen_id_number' => ['required', 'string', 'max:20'],
            'address'           => ['required', 'string', 'max:500'],
            'date_of_birth'     => ['required', 'date', 'before:today'],
            'citizen_id_front'  => $this->imageRules(required: true, maxSizeKb: 2048),
            'citizen_id_back'   => $this->imageRules(required: true, maxSizeKb: 2048),
            'hire_date'         => ['required', 'date'],
            'base_salary'       => ['required', 'numeric', 'min:0'],
            'role'              => ['required', 'string', 'in:cashier,kitchen,manager,waiter'],
            'job_title'         => ['required', 'string', 'max:100'],
        ];
    }
}
