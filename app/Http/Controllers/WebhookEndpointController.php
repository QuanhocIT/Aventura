<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesRestaurantSettings;
use App\Models\WebhookEndpoint;
use App\Services\Integrations\WebhookDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebhookEndpointController extends Controller
{
    use AuthorizesRestaurantSettings;

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRestaurantSettings($request);
        $data = $request->validate([
            'url' => ['required', 'url:https,http', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(WebhookEndpoint::EVENTS)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $secret = WebhookEndpoint::generateSecret();

        WebhookEndpoint::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'url' => $data['url'],
            'secret' => $secret,
            'events' => array_values($data['events']),
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        // Secret chỉ hiển thị một lần duy nhất sau khi tạo
        return back()->with('success', 'Đã tạo webhook endpoint.')->with('webhook_secret', $secret);
    }

    public function toggle(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorizeRestaurantSettings($request);
        abort_unless($endpoint->restaurant_id === $request->user()->restaurant_id, 403);

        $endpoint->update(['is_active' => ! $endpoint->is_active]);

        return back()->with('success', $endpoint->is_active ? 'Đã bật endpoint.' : 'Đã tạm dừng endpoint.');
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorizeRestaurantSettings($request);
        abort_unless($endpoint->restaurant_id === $request->user()->restaurant_id, 403);

        $endpoint->delete();

        return back()->with('success', 'Đã xóa webhook endpoint.');
    }

    /** Gửi sự kiện ping thử nghiệm tới endpoint. */
    public function test(Request $request, WebhookEndpoint $endpoint, WebhookDispatchService $service): RedirectResponse
    {
        $this->authorizeRestaurantSettings($request);
        abort_unless($endpoint->restaurant_id === $request->user()->restaurant_id, 403);

        $delivery = $service->sendTest($endpoint);

        if ($delivery->status === 'success') {
            return back()->with('success', "Ping thành công — endpoint trả về HTTP {$delivery->response_code}.");
        }

        return back()->withErrors([
            'webhook' => 'Ping thất bại'.($delivery->response_code ? " (HTTP {$delivery->response_code})" : '').': '.($delivery->response_body ?? 'không kết nối được'),
        ]);
    }
}
