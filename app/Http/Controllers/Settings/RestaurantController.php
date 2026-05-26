<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    public function edit(Request $request): Response
    {
        $restaurant = $request->user()?->restaurant;

        return Inertia::render('settings/Restaurant', [
            'restaurant' => $restaurant ? [
                'name'     => $restaurant->name,
                'phone'    => $restaurant->phone,
                'email'    => $restaurant->email,
                'address'  => $restaurant->address,
                'tax_code' => $restaurant->tax_code,
                'timezone' => $restaurant->timezone,
                'currency' => $restaurant->currency,
            ] : null,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            return back()->with('error', 'Không tìm thấy thông tin nhà hàng.');
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'email'    => ['nullable', 'email', 'max:255'],
            'address'  => ['nullable', 'string', 'max:500'],
            'tax_code' => ['nullable', 'string', 'max:50'],
        ]);

        $restaurant->update($data);

        return back()->with('status', 'Đã cập nhật thông tin nhà hàng.');
    }
}
