<?php

namespace App\Http\Controllers;

use App\Models\PlatformFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformFeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'category' => 'required|string|in:service_plan,pos_system,customer_support,feature_request,general',
            'content' => 'required|string|max:2000',
        ]);

        $user = $request->user();
        $restaurant = $user->restaurant;
        $planId = $restaurant?->plan_id;

        PlatformFeedback::create([
            'user_id' => $user->id,
            'restaurant_id' => $restaurant?->id,
            'plan_id' => $planId,
            'rating' => $validated['rating'],
            'category' => $validated['category'],
            'content' => $validated['content'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Cảm ơn bạn đã gửi đánh giá dịch vụ! Phản hồi của bạn đã được chuyển tới ban quản trị hệ thống Aventura.');
    }
}
