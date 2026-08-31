<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class FirewallController extends Controller
{
    private const MASKED_SECRET = '********';

    /**
     * Display the firewall settings, blocked IPs, and whitelist.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        // Super Admin access authorization is handled by middleware, but we double check
        abort_unless($user->isPlatformAdmin(), 403);

        // 1. Process Blocked IPs
        $blockedList = Cache::get('waf:blocked_list', []);
        $now = time();

        // Filter out expired ones
        $activeBlocked = [];
        $hasChanges = false;

        foreach ($blockedList as $ip => $expiry) {
            if ($expiry > $now) {
                $activeBlocked[] = [
                    'ip' => $ip,
                    'blocked_until' => date('Y-m-d H:i:s', $expiry),
                    'remaining_seconds' => $expiry - $now,
                ];
            } else {
                $hasChanges = true;
                unset($blockedList[$ip]);
            }
        }

        if ($hasChanges) {
            Cache::put('waf:blocked_list', $blockedList, 86400 * 30);
        }

        // 2. Fetch Whitelisted IPs
        $whitelist = json_decode(SystemSetting::get('firewall_whitelist', '[]'), true);

        // 3. Fetch configurations
        $settings = [
            'waf_login_max_attempts' => (int) (SystemSetting::get('waf_login_max_attempts') ?? config('firewall.waf.login.max_attempts', 5)),
            'waf_login_decay_seconds' => (int) (SystemSetting::get('waf_login_decay_seconds') ?? config('firewall.waf.login.decay_seconds', 10)),
            'waf_login_block_minutes' => (int) (SystemSetting::get('waf_login_block_minutes') ?? config('firewall.waf.login.block_minutes', 30)),
            'rate_limit_global_max' => (int) (SystemSetting::get('rate_limit_global_max') ?? config('firewall.rate_limit.global.max_attempts', 60)),
            'rate_limit_global_decay' => (int) (SystemSetting::get('rate_limit_global_decay') ?? config('firewall.rate_limit.global.decay_seconds', 60)),
            'superadmin_allowed_ips' => SystemSetting::get('superadmin_allowed_ips', config('auth.superadmin_allowed_ips', '')),
            'telegram_bot_token' => $this->maskedSetting('telegram_bot_token'),
            'telegram_chat_id' => $this->maskedSetting('telegram_chat_id'),
            'turnstile_site_key' => SystemSetting::get('turnstile_site_key', ''),
            'turnstile_secret_key' => $this->maskedSetting('turnstile_secret_key'),
        ];

        return Inertia::render('super-admin/firewall/Index', [
            'blockedIps' => $activeBlocked,
            'whitelist' => $whitelist,
            'settings' => $settings,
        ]);
    }

    /**
     * Unblock a blocked IP address.
     */
    public function unblock(Request $request, string $ip): RedirectResponse
    {
        Cache::forget("waf:blocked:{$ip}");

        $blockedList = Cache::get('waf:blocked_list', []);
        if (isset($blockedList[$ip])) {
            unset($blockedList[$ip]);
            Cache::put('waf:blocked_list', $blockedList, 86400 * 30);
        }

        $this->audit($request, 'firewall_ip_unblock', ['ip' => $ip]);

        return back()->with('success', "Đã mở khóa thành công cho IP {$ip}.");
    }

    /**
     * Add an IP address to the whitelist.
     */
    public function whitelist(Request $request): RedirectResponse
    {
        $request->validate([
            'ip' => 'required|ip',
        ], [
            'ip.required' => 'Vui lòng nhập địa chỉ IP.',
            'ip.ip' => 'Địa chỉ IP không đúng định dạng.',
        ]);

        $ip = $request->input('ip');
        $whitelist = json_decode(SystemSetting::get('firewall_whitelist', '[]'), true);

        if (in_array($ip, $whitelist, true)) {
            return back()->with('error', "IP {$ip} đã tồn tại trong danh sách trắng.");
        }

        $whitelist[] = $ip;
        SystemSetting::set('firewall_whitelist', json_encode($whitelist));

        // Also make sure it's unblocked if it was blocked
        Cache::forget("waf:blocked:{$ip}");
        $blockedList = Cache::get('waf:blocked_list', []);
        if (isset($blockedList[$ip])) {
            unset($blockedList[$ip]);
            Cache::put('waf:blocked_list', $blockedList, 86400 * 30);
        }

        $this->audit($request, 'firewall_whitelist_add', ['ip' => $ip]);

        return back()->with('success', "Đã thêm IP {$ip} vào danh sách trắng.");
    }

    /**
     * Remove an IP address from the whitelist.
     */
    public function removeWhitelist(Request $request, string $ip): RedirectResponse
    {
        $whitelist = json_decode(SystemSetting::get('firewall_whitelist', '[]'), true);
        $whitelist = array_values(array_filter($whitelist, fn ($item) => $item !== $ip));
        SystemSetting::set('firewall_whitelist', json_encode($whitelist));

        $this->audit($request, 'firewall_whitelist_remove', ['ip' => $ip]);

        return back()->with('success', "Đã xóa IP {$ip} khỏi danh sách trắng.");
    }

    /**
     * Update Firewall and security settings.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'waf_login_max_attempts' => 'required|integer|min:1|max:100',
            'waf_login_decay_seconds' => 'required|integer|min:5|max:3600',
            'waf_login_block_minutes' => 'required|integer|min:1|max:43200',
            'rate_limit_global_max' => 'required|integer|min:1|max:10000',
            'rate_limit_global_decay' => 'required|integer|min:5|max:3600',
            'superadmin_allowed_ips' => 'nullable|string|max:2000',
            'telegram_bot_token' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'turnstile_site_key' => 'nullable|string',
            'turnstile_secret_key' => 'nullable|string',
        ]);

        SystemSetting::set('waf_login_max_attempts', $request->input('waf_login_max_attempts'));
        SystemSetting::set('waf_login_decay_seconds', $request->input('waf_login_decay_seconds'));
        SystemSetting::set('waf_login_block_minutes', $request->input('waf_login_block_minutes'));
        SystemSetting::set('rate_limit_global_max', $request->input('rate_limit_global_max'));
        SystemSetting::set('rate_limit_global_decay', $request->input('rate_limit_global_decay'));

        $allowedIps = preg_split('/[\s,]+/', trim((string) $request->input('superadmin_allowed_ips', ''))) ?: [];
        if ($request->has('superadmin_allowed_ips')) {
            foreach (array_filter($allowedIps) as $allowedIp) {
                if (filter_var($allowedIp, FILTER_VALIDATE_IP) === false) {
                    return back()->withErrors([
                        'superadmin_allowed_ips' => "Địa chỉ IP không hợp lệ: {$allowedIp}.",
                    ]);
                }
            }

            SystemSetting::set('superadmin_allowed_ips', implode(',', array_values(array_filter($allowedIps))));
        } else {
            $allowedIps = preg_split(
                '/[\s,]+/',
                trim((string) SystemSetting::get('superadmin_allowed_ips', config('auth.superadmin_allowed_ips', ''))),
            ) ?: [];
        }

        foreach (['telegram_bot_token', 'telegram_chat_id', 'turnstile_secret_key'] as $secretKey) {
            $value = $request->input($secretKey);
            if (is_string($value) && trim($value) !== '' && $value !== self::MASKED_SECRET) {
                SystemSetting::set($secretKey, trim($value));
            }
        }

        SystemSetting::set('turnstile_site_key', $request->input('turnstile_site_key') ?? '');
        $this->audit($request, 'firewall_settings_update', [
            'waf_login_max_attempts' => $request->input('waf_login_max_attempts'),
            'waf_login_decay_seconds' => $request->input('waf_login_decay_seconds'),
            'waf_login_block_minutes' => $request->input('waf_login_block_minutes'),
            'rate_limit_global_max' => $request->input('rate_limit_global_max'),
            'rate_limit_global_decay' => $request->input('rate_limit_global_decay'),
            'superadmin_allowed_ips' => implode(',', array_values(array_filter($allowedIps))),
            'secrets_updated' => array_values(array_filter(
                ['telegram_bot_token', 'telegram_chat_id', 'turnstile_secret_key'],
                fn (string $key): bool => is_string($request->input($key))
                    && trim($request->input($key)) !== ''
                    && $request->input($key) !== self::MASKED_SECRET,
            )),
        ]);

        return back()->with('success', 'Cập nhật cấu hình bảo mật thành công.');
    }

    private function maskedSetting(string $key): string
    {
        return SystemSetting::get($key, '') !== '' ? self::MASKED_SECRET : '';
    }

    private function audit(Request $request, string $action, array $values): void
    {
        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()?->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => $action,
            'subject_type' => self::class,
            'subject_id' => null,
            'old_values' => null,
            'new_values' => $values,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
