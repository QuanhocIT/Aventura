<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantChooserController extends Controller
{
    /**
     * Hiển thị trang chọn nhà hàng (multi-tenant).
     */
    public function chooseRestaurantPage(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $activeUsers = User::where('email', $user->email)
            ->where('status', 'active')
            ->with(['restaurant', 'roles'])
            ->get();

        if ($activeUsers->count() <= 1) {
            return redirect()->intended('/dashboard');
        }

        $restaurants = $activeUsers->map(function ($activeUser) {
            return [
                'id' => $activeUser->restaurant->id,
                'name' => $activeUser->restaurant->name,
                'logo_url' => $activeUser->restaurant->logo_url ?? null,
                'role' => $activeUser->roles->pluck('name')->first() ?? 'Nhân viên',
                'job_title' => $activeUser->employee?->job_title ?? 'Nhân viên',
                'user_id' => $activeUser->id,
            ];
        });

        return Inertia::render('auth/ChooseRestaurant', [
            'restaurants' => $restaurants,
        ]);
    }

    /**
     * Thực hiện chuyển đổi session sang nhà hàng được chọn.
     */
    public function chooseRestaurant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $currentUser = $request->user();
        $targetUser = User::where('id', $data['user_id'])
            ->where('email', $currentUser->email)
            ->where('status', 'active')
            ->firstOrFail();

        Auth::login($targetUser);
        $request->session()->regenerate();

        $activeUsers = User::where('email', $targetUser->email)
            ->where('status', 'active')
            ->get();
        session(['multi_tenant_users' => $activeUsers->pluck('id', 'restaurant_id')->toArray()]);

        return redirect()->intended('/dashboard');
    }
}
