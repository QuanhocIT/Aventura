<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                'logo_url' => $restaurant->logo_url,
                'qr_bank_id' => $restaurant->qr_bank_id,
                'qr_account_number' => $restaurant->qr_account_number,
                'qr_account_name' => $restaurant->qr_account_name,
                'qr_enabled' => (bool) $restaurant->qr_enabled,
                'reservation_deposit_amount' => (float) ($restaurant->reservation_deposit_amount ?? 0),
                'latitude' => $restaurant->latitude ? (float) $restaurant->latitude : null,
                'longitude' => $restaurant->longitude ? (float) $restaurant->longitude : null,
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
            'logo'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'qr_bank_id' => ['nullable', 'string', 'max:50'],
            'qr_account_number' => ['nullable', 'string', 'max:50'],
            'qr_account_name' => ['nullable', 'string', 'max:255'],
            'qr_enabled' => ['nullable', 'boolean'],
            'reservation_deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $data['qr_enabled'] = $request->boolean('qr_enabled');

        if ($request->hasFile('logo')) {
            if ($restaurant->logo_url) {
                Storage::disk('public')->delete(Str::after($restaurant->logo_url, '/storage/'));
            }

            $data['logo_url'] = '/storage/' . $request->file('logo')->store('restaurants', 'public');
        }

        unset($data['logo']);

        $restaurant->update($data);

        return back()->with('status', 'Đã cập nhật thông tin nhà hàng.');
    }
}
