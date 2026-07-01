<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create_orders');
    }

    public function rules(): array
    {
        $rid = $this->user()->restaurant_id;

        return [
            'channel'            => ['nullable', 'in:dine_in,takeaway,delivery'],
            'table_id'           => ['nullable', "exists:restaurant_tables,id,restaurant_id,{$rid}"],
            'customer_id'        => ['nullable', "exists:customers,id,restaurant_id,{$rid}"],
            'note'               => ['nullable', 'string', 'max:500'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', "exists:products,id,restaurant_id,{$rid}"],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.01'],
            'items.*.notes'      => ['nullable', 'string', 'max:255'],
            'guests_count'       => ['nullable', 'integer', 'min:1'],
            // Delivery-specific fields
            'delivery_customer_name' => ['required_if:channel,delivery', 'nullable', 'string', 'max:255'],
            'delivery_phone'         => ['required_if:channel,delivery', 'nullable', 'string', 'max:20'],
            'delivery_address'       => ['required_if:channel,delivery', 'nullable', 'string', 'max:500'],
            'delivery_lat'           => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng'           => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_fee'           => ['nullable', 'numeric', 'min:0'],
            'cod_amount'             => ['nullable', 'numeric', 'min:0'],
            'delivery_notes'         => ['nullable', 'string', 'max:500'],
        ];
    }
}
