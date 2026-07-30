<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SecurityThreatController extends Controller
{
    public function index(): Response
    {
        // 1. Fetch Active Sessions
        $sessions = [];
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            $sessions = DB::table('sessions')
                ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
                ->select('sessions.id', 'sessions.ip_address', 'sessions.user_agent', 'sessions.last_activity', 'users.name as user_name')
                ->orderBy('sessions.last_activity', 'desc')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'ip_address' => $s->ip_address,
                    'user_agent' => $s->user_agent,
                    'last_activity' => date('d/m/Y H:i:s', $s->last_activity),
                    'user_name' => $s->user_name ?? 'Khách / Chưa đăng nhập',
                ])
                ->toArray();
        } else {
            // Mock active sessions from recently logged-in users
            $sessions = User::whereNotNull('last_login_at')
                ->latest('last_login_at')
                ->take(5)
                ->get()
                ->map(fn ($u) => [
                    'id' => 'mock_'.$u->id,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'last_activity' => $u->last_login_at->format('d/m/Y H:i:s'),
                    'user_name' => $u->name,
                ])
                ->toArray();
        }

        // 2. Fetch Blocked IPs from WAF
        $blockedList = Cache::get('waf:blocked_list', []);
        $now = time();
        $activeBlocked = [];

        foreach ($blockedList as $ip => $expiry) {
            if ($expiry > $now) {
                $activeBlocked[] = [
                    'ip' => $ip,
                    'blocked_until' => date('d/m/Y H:i:s', $expiry),
                    'remaining_seconds' => $expiry - $now,
                ];
            }
        }

        // 3. Mock threat alerts history for visualization
        $threatHistory = [
            ['label' => 'Brute Force Attempts', 'value' => 14],
            ['label' => 'SQL Injection Blocked', 'value' => 8],
            ['label' => 'XSS Script Injection', 'value' => 3],
            ['label' => 'Rate Limit Violations', 'value' => 29],
        ];

        return Inertia::render('super-admin/firewall/SecurityCenter', [
            'sessions' => $sessions,
            'blockedIps' => $activeBlocked,
            'threatHistory' => $threatHistory,
            'sessionDriver' => config('session.driver'),
        ]);
    }

    /**
     * Terminate an active session.
     */
    public function revokeSession(string $id): JsonResponse
    {
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đã hủy phiên làm việc thành công.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gỡ bỏ phiên (giả lập) thành công.',
        ]);
    }
}
