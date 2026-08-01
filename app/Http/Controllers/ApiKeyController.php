<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        [$plainKey] = ApiKey::generate($request->user()->restaurant_id, $data['name']);

        // Key đầy đủ chỉ hiển thị một lần — dùng chung cơ chế flash với webhook secret
        return back()->with('success', 'Đã tạo API key.')->with('webhook_secret', $plainKey);
    }

    public function toggle(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->restaurant_id === $request->user()->restaurant_id, 403);

        $apiKey->update(['is_active' => ! $apiKey->is_active]);

        return back()->with('success', $apiKey->is_active ? 'Đã kích hoạt lại API key.' : 'Đã thu hồi API key.');
    }

    public function rotate(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->restaurant_id === $request->user()->restaurant_id, 403);

        $plainKey = ApiKey::rotate($apiKey);

        return back()
            ->with('success', 'Đã rotation API key. Key cũ không còn sử dụng được.')
            ->with('webhook_secret', $plainKey);
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->restaurant_id === $request->user()->restaurant_id, 403);

        $apiKey->delete();

        return back()->with('success', 'Đã xóa API key.');
    }
}
