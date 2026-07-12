<?php

namespace App\Http\Controllers;

use App\Models\PosDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PosDeviceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:pos,printer'],
            'ip' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'paper_width' => ['nullable', 'in:58,80'],
        ]);

        $meta = [];
        if ($data['type'] === PosDevice::TYPE_PRINTER) {
            $meta = [
                'ip' => $data['ip'] ?? null,
                'port' => (int) ($data['port'] ?? 9100),
                'paper_width' => (int) ($data['paper_width'] ?? 58),
            ];
        }

        PosDevice::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'name' => $data['name'],
            'type' => $data['type'],
            // Máy in nhiệt LAN không cần pairing — kích hoạt luôn; máy POS chờ ghép nối
            'status' => $data['type'] === PosDevice::TYPE_PRINTER ? 'active' : 'pending',
            'pairing_code' => $data['type'] === PosDevice::TYPE_POS ? PosDevice::generatePairingCode() : null,
            'meta' => $meta,
        ]);

        return back()->with('success', $data['type'] === PosDevice::TYPE_PRINTER
            ? 'Đã thêm máy in nhiệt.'
            : 'Đã tạo thiết bị POS — dùng mã ghép nối hiển thị trên thẻ để kết nối.');
    }

    public function destroy(Request $request, PosDevice $device): RedirectResponse
    {
        abort_unless($device->restaurant_id === $request->user()->restaurant_id, 403);

        $device->delete();

        return back()->with('success', 'Đã xóa thiết bị.');
    }

    /**
     * API công khai cho app POS: đổi mã ghép nối lấy device token.
     * POST /api/pos/pair { pairing_code }
     */
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pairing_code' => ['required', 'string', 'size:6'],
        ]);

        $device = PosDevice::withoutGlobalScopes()
            ->where('pairing_code', strtoupper($data['pairing_code']))
            ->where('status', 'pending')
            ->first();

        if (! $device) {
            return response()->json(['message' => 'Mã ghép nối không hợp lệ hoặc đã dùng.'], 422);
        }

        $token = PosDevice::generateDeviceToken();

        $device->update([
            'status' => 'active',
            'device_token' => $token,
            'pairing_code' => null,
            'paired_at' => now(),
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'device_token' => $token,
            'device' => ['id' => $device->id, 'name' => $device->name],
        ]);
    }

    /**
     * API công khai cho app POS: heartbeat báo thiết bị còn online.
     * POST /api/pos/heartbeat — header X-Device-Token
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $token = $request->header('X-Device-Token', '');

        if ($token === '') {
            return response()->json(['message' => 'Thiếu X-Device-Token.'], 401);
        }

        $device = PosDevice::withoutGlobalScopes()
            ->where('device_token', $token)
            ->where('status', 'active')
            ->first();

        if (! $device) {
            return response()->json(['message' => 'Thiết bị không tồn tại hoặc đã bị vô hiệu.'], 401);
        }

        $device->update([
            'last_seen_at' => now(),
            'meta' => array_merge($device->meta ?? [], $request->only(['app_version', 'platform'])),
        ]);

        return response()->json(['success' => true, 'server_time' => now()->toIso8601String()]);
    }
}
