<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\LoginEvent;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Services\SuperAdminAuditStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SecurityThreatController extends Controller
{
    public function index(): Response
    {
        $sessions = $this->activeSessions();
        $blockedIps = $this->blockedIps();
        $loginHistory = [];
        $anomalousIps = [];
        $alerts = [];

        if (Schema::hasTable('login_events')) {
            $loginHistory = LoginEvent::with('user:id,name,email')
                ->latest('occurred_at')
                ->limit(80)
                ->get()
                ->map(fn (LoginEvent $event) => [
                    'id' => $event->id,
                    'user_id' => $event->user_id,
                    'user_name' => $event->user?->name,
                    'email' => $event->email,
                    'status' => $event->status,
                    'ip_address' => $event->ip_address,
                    'user_agent' => $event->user_agent,
                    'failure_reason' => $event->failure_reason,
                    'occurred_at' => $event->occurred_at?->format('d/m/Y H:i:s'),
                ])->values()->all();

            $anomalousIps = $this->anomalousIps();
        }

        $alerts = $this->syncSecurityAlerts($blockedIps, $anomalousIps);

        $apiKeys = Schema::hasTable('api_keys')
            ? ApiKey::withoutGlobalScopes()
                ->with('restaurant:id,name')
                ->latest('updated_at')
                ->limit(100)
                ->get()
                ->map(fn (ApiKey $key) => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key_prefix' => $key->key_prefix,
                    'restaurant_id' => $key->restaurant_id,
                    'restaurant_name' => $key->restaurant?->name,
                    'is_active' => (bool) $key->is_active,
                    'last_used_at' => $key->last_used_at?->format('d/m/Y H:i:s'),
                    'rotated_at' => $key->rotated_at?->format('d/m/Y H:i:s'),
                ])->values()->all()
            : [];

        return Inertia::render('super-admin/firewall/SecurityCenter', [
            'sessions' => $sessions,
            'blockedIps' => $blockedIps,
            'threatHistory' => $this->threatHistory(),
            'sessionDriver' => config('session.driver'),
            'loginHistory' => $loginHistory,
            'anomalousIps' => $anomalousIps,
            'alerts' => $alerts,
            'apiKeys' => $apiKeys,
            'users' => User::query()
                ->select(['id', 'name', 'email', 'restaurant_id', 'status'])
                ->with('restaurant:id,name')
                ->latest('last_login_at')
                ->limit(100)
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'restaurant_name' => $user->restaurant?->name,
                    'status' => $user->status,
                ])->values()->all(),
        ]);
    }

    /** Terminate one session or revoke all sessions belonging to its user. */
    public function revokeSession(string $id): JsonResponse
    {
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            $session = DB::table('sessions')->where('id', $id)->first();

            if ($session) {
                DB::table('sessions')->where('id', $id)->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Đã hủy phiên làm việc thành công.',
                ]);
            }
        }

        if (str_starts_with($id, 'mock_')) {
            $user = User::find((int) str_replace('mock_', '', $id));
            if ($user) {
                $this->revokeUserSessions($user);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Phiên không còn tồn tại hoặc đã được hủy.',
        ]);
    }

    public function forceLogoutUser(Request $request, User $user): JsonResponse
    {
        $before = [
            'security_session_version' => (int) ($user->security_session_version ?? 0),
            'force_logout_at' => $user->force_logout_at?->toIso8601String(),
        ];

        $this->revokeUserSessions($user);
        $user->refresh();

        $after = [
            'security_session_version' => (int) ($user->security_session_version ?? 0),
            'force_logout_at' => $user->force_logout_at?->toIso8601String(),
        ];

        SuperAdminAuditStream::record('force_logout_user', [
            'target_user' => $user->email,
            'before' => $before,
            'after' => $after,
        ], User::class, $user->id, 'critical');

        return response()->json([
            'success' => true,
            'message' => "Đã force logout {$user->email} và thu hồi các phiên hiện tại.",
        ]);
    }

    public function forceLogoutAll(Request $request): JsonResponse
    {
        $count = User::query()->count();
        $now = now();

        User::query()->update([
            'security_session_version' => DB::raw('security_session_version + 1'),
            'force_logout_at' => $now,
        ]);

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->delete();
        }

        SuperAdminAuditStream::record('force_logout_all', [
            'user_count' => $count,
            'before' => ['all_sessions' => 'active'],
            'after' => ['all_sessions' => 'revoked', 'force_logout_at' => $now->toIso8601String()],
        ], User::class, null, 'critical');

        return response()->json([
            'success' => true,
            'message' => "Đã thu hồi toàn bộ phiên đăng nhập ({$count} tài khoản).",
        ]);
    }

    public function rotateApiKey(Request $request, int $apiKey): JsonResponse
    {
        $key = ApiKey::withoutGlobalScopes()->findOrFail($apiKey);
        $before = ['key_prefix' => $key->key_prefix, 'is_active' => (bool) $key->is_active];
        $plainKey = ApiKey::rotate($key);
        $key->refresh();

        SuperAdminAuditStream::record('api_key_rotate', [
            'restaurant_id' => $key->restaurant_id,
            'key_name' => $key->name,
            'before' => $before,
            'after' => ['key_prefix' => $key->key_prefix, 'is_active' => (bool) $key->is_active],
        ], ApiKey::class, $key->id, 'critical');

        return response()->json([
            'success' => true,
            'plain_key' => $plainKey,
            'message' => 'Đã rotation API key. Hãy sao chép key mới ngay, vì key chỉ hiển thị một lần.',
        ]);
    }

    public function revokeApiKey(Request $request, int $apiKey): JsonResponse
    {
        $key = ApiKey::withoutGlobalScopes()->findOrFail($apiKey);
        $before = ['is_active' => (bool) $key->is_active];
        $key->update(['is_active' => false]);

        SuperAdminAuditStream::record('api_key_revoke', [
            'restaurant_id' => $key->restaurant_id,
            'key_name' => $key->name,
            'before' => $before,
            'after' => ['is_active' => false],
        ], ApiKey::class, $key->id, 'critical');

        return response()->json(['success' => true, 'message' => 'Đã thu hồi API key.']);
    }

    public function acknowledgeAlert(SecurityAlert $alert): JsonResponse
    {
        $alert->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        SuperAdminAuditStream::record('security_alert_acknowledge', ['alert_id' => $alert->id], SecurityAlert::class, $alert->id, 'warning');

        return response()->json(['success' => true]);
    }

    public function resolveAlert(Request $request, SecurityAlert $alert): JsonResponse
    {
        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);
        SuperAdminAuditStream::record('security_alert_resolve', ['alert_id' => $alert->id], SecurityAlert::class, $alert->id, 'warning');

        return response()->json(['success' => true]);
    }

    private function activeSessions(): array
    {
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            return DB::table('sessions')
                ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
                ->select('sessions.id', 'sessions.user_id', 'sessions.ip_address', 'sessions.user_agent', 'sessions.last_activity', 'users.name as user_name', 'users.email as user_email')
                ->orderBy('sessions.last_activity', 'desc')
                ->limit(100)
                ->get()
                ->map(fn ($session) => [
                    'id' => $session->id,
                    'user_id' => $session->user_id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => date('d/m/Y H:i:s', $session->last_activity),
                    'user_name' => $session->user_name ?? 'Khách / Chưa đăng nhập',
                    'user_email' => $session->user_email,
                ])->values()->all();
        }

        return User::whereNotNull('last_login_at')
            ->latest('last_login_at')
            ->limit(10)
            ->get()
            ->map(fn (User $user) => [
                'id' => 'mock_'.$user->id,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Không có session driver database',
                'last_activity' => $user->last_login_at?->format('d/m/Y H:i:s'),
                'user_name' => $user->name,
                'user_email' => $user->email,
            ])->values()->all();
    }

    private function revokeUserSessions(User $user): void
    {
        $user->forceFill([
            'security_session_version' => (int) ($user->security_session_version ?? 0) + 1,
            'force_logout_at' => now(),
        ])->save();

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    private function blockedIps(): array
    {
        $blockedList = Cache::get('waf:blocked_list', []);
        $now = time();

        return collect($blockedList)
            ->filter(fn ($expiry) => $expiry > $now)
            ->map(fn ($expiry, $ip) => [
                'ip' => $ip,
                'blocked_until' => date('d/m/Y H:i:s', $expiry),
                'remaining_seconds' => $expiry - $now,
            ])->values()->all();
    }

    private function threatHistory(): array
    {
        if (! Schema::hasTable('login_events')) {
            return [];
        }

        $events = LoginEvent::where('occurred_at', '>=', now()->subDay())->get();

        return [
            ['label' => 'Đăng nhập thất bại', 'value' => $events->where('status', 'failed')->count()],
            ['label' => 'IP bị WAF khóa', 'value' => count($this->blockedIps())],
            ['label' => 'IP bất thường', 'value' => count($this->anomalousIps())],
            ['label' => 'Đăng nhập thành công', 'value' => $events->where('status', 'success')->count()],
        ];
    }

    private function anomalousIps(): array
    {
        $cutoff = now()->subDays(7);
        $historyCutoff = now()->subDays(30);
        $events = LoginEvent::where('status', 'success')
            ->where('occurred_at', '>=', $historyCutoff)
            ->latest('occurred_at')
            ->get();

        $oldEvents = $events->filter(fn (LoginEvent $event) => $event->occurred_at < $cutoff);
        $recentEvents = $events->filter(fn (LoginEvent $event) => $event->occurred_at >= $cutoff);
        $anomalies = [];

        foreach ($recentEvents as $event) {
            if (! $event->ip_address) {
                continue;
            }

            $sameUser = fn (LoginEvent $candidate) => $candidate->user_id === $event->user_id
                && ($candidate->user_id !== null || $candidate->email === $event->email);
            $hadHistory = $oldEvents->contains($sameUser);
            $hadSameIp = $oldEvents->contains(fn (LoginEvent $candidate) => $sameUser($candidate) && $candidate->ip_address === $event->ip_address);

            if ($hadHistory && ! $hadSameIp) {
                $key = ($event->user_id ?: $event->email).':'.$event->ip_address;
                if (! isset($anomalies[$key])) {
                    $anomalies[$key] = [
                        'user_id' => $event->user_id,
                        'email' => $event->email,
                        'user_name' => $event->user?->name,
                        'ip_address' => $event->ip_address,
                        'first_seen_at' => $event->occurred_at?->format('d/m/Y H:i:s'),
                        'reason' => 'IP mới trong 7 ngày gần nhất',
                    ];
                }
            }
        }

        return array_values($anomalies);
    }

    private function syncSecurityAlerts(array $blockedIps, array $anomalousIps): array
    {
        if (! Schema::hasTable('security_alerts')) {
            return [];
        }

        foreach ($blockedIps as $blocked) {
            $this->upsertAlert(
                'waf-blocked:'.$blocked['ip'],
                'waf_blocked_ip',
                'critical',
                'IP đang bị WAF khóa',
                "IP {$blocked['ip']} đang bị chặn đến {$blocked['blocked_until']}.",
                ['ip' => $blocked['ip'], 'blocked_until' => $blocked['blocked_until']],
            );
        }

        foreach ($anomalousIps as $anomaly) {
            $this->upsertAlert(
                'new-login-ip:'.($anomaly['user_id'] ?: $anomaly['email']).':'.$anomaly['ip_address'],
                'new_login_ip',
                'warning',
                'Phát hiện IP đăng nhập mới',
                "Tài khoản {$anomaly['email']} vừa đăng nhập từ IP {$anomaly['ip_address']} chưa từng thấy trong 30 ngày trước.",
                $anomaly,
            );
        }

        return SecurityAlert::latest('last_seen_at')
            ->limit(30)
            ->get()
            ->map(fn (SecurityAlert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'title' => $alert->title,
                'message' => $alert->message,
                'context' => $alert->context,
                'status' => $alert->status,
                'occurrences' => $alert->occurrences,
                'last_seen_at' => $alert->last_seen_at?->format('d/m/Y H:i:s'),
            ])->values()->all();
    }

    private function upsertAlert(string $fingerprint, string $type, string $severity, string $title, string $message, array $context): void
    {
        $alert = SecurityAlert::where('fingerprint', $fingerprint)->first();

        if (! $alert) {
            SecurityAlert::create([
                'fingerprint' => $fingerprint,
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'context' => $context,
                'status' => 'open',
                'occurrences' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            return;
        }

        $alert->update([
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'status' => 'open',
            'last_seen_at' => now(),
            'occurrences' => DB::raw('occurrences + 1'),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }
}
