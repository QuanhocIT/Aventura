<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\Concerns\HasFileUploadRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    use HasFileUploadRules;

    public function authorize(): bool
    {
        $employee = $this->route('employee');

        // Previously missing (see StoreEmployeeRequest) — any authenticated tenant user
        // could update any employee's record. Now matches toggleEmployeeStatus()'s
        // 'manage_employees' check, confirmed with the user as an oversight to fix.
        return (bool) $this->user()?->can('manage_employees')
            && $employee
            && $this->user()->restaurant_id === $employee->restaurant_id;
    }

    public function rules(): array
    {
        return [
            'status'            => ['sometimes', 'in:active,inactive'],
            'full_name'         => ['sometimes', 'string', 'max:255'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:20'],
            'job_title'         => ['sometimes', 'string', 'max:100'],
            'role'              => ['sometimes', 'string', 'in:cashier,kitchen,manager,waiter'],
            'date_of_birth'     => ['sometimes', 'nullable', 'date', 'before:today'],
            'address'           => ['sometimes', 'nullable', 'string', 'max:500'],
            'citizen_id_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'citizen_id_front'  => $this->imageRules(maxSizeKb: 2048),
            'citizen_id_back'   => $this->imageRules(maxSizeKb: 2048),
        ];
    }
}
