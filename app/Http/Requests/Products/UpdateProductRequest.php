<?php

namespace App\Http\Requests\Products;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product && $this->user()?->restaurant_id === $product->restaurant_id;
    }

    public function rules(): array
    {
        $maxSize = SystemSetting::get('upload_menu_image_max', 2048);

        return [
            'name'         => ['sometimes', 'string', 'max:255'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'category_id'  => ['nullable', 'exists:product_categories,id'],
            'description'  => ['sometimes', 'required', 'string', 'min:5'],
            'is_available' => ['sometimes', 'boolean'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxSize],
        ];
    }
}
