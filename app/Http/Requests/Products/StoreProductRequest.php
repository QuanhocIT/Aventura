<?php

namespace App\Http\Requests\Products;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['owner', 'manager']);
    }

    public function rules(): array
    {
        $maxSize = SystemSetting::get('upload_menu_image_max', 2048);

        return [
            'category_id' => ['required', 'exists:product_categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'price'       => ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'min:5'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxSize],
        ];
    }
}
