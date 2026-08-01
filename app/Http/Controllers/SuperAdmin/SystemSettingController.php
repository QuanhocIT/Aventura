<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Services\ChatbotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    private array $settingKeys = [
        'chatbot_similarity_threshold',
        'chatbot_max_suggestions',
        'chatbot_cache_ttl',
        'mail_driver',
        'mail_smtp_host',
        'mail_smtp_port',
        'mail_smtp_username',
        'mail_smtp_password',
        'mail_smtp_encryption',
        'mail_ses_key',
        'mail_ses_secret',
        'mail_ses_region',
        'mail_mailgun_domain',
        'mail_mailgun_secret',
        'mail_mailgun_endpoint',
        'mail_from_address',
        'mail_from_name',
        'upload_menu_image_max',
        'upload_invoice_image_max',
    ];

    private array $passwordKeys = [
        'mail_smtp_password',
        'mail_ses_secret',
        'mail_mailgun_secret',
    ];

    public function index(): Response
    {
        $settings = [];
        foreach ($this->settingKeys as $key) {
            $settings[$key] = SystemSetting::get($key);
        }

        $settings['chatbot_similarity_threshold'] = (float) ($settings['chatbot_similarity_threshold'] ?? 0.28);
        $settings['chatbot_max_suggestions'] = (int) ($settings['chatbot_max_suggestions'] ?? 5);
        $settings['chatbot_cache_ttl'] = (int) ($settings['chatbot_cache_ttl'] ?? 300);

        $settings['mail_driver'] = $settings['mail_driver'] ?? config('mail.default', 'smtp');
        $settings['mail_smtp_host'] = $settings['mail_smtp_host'] ?? config('mail.mailers.smtp.host', '');
        $settings['mail_smtp_port'] = (int) ($settings['mail_smtp_port'] ?? config('mail.mailers.smtp.port', 587));
        $settings['mail_smtp_username'] = $settings['mail_smtp_username'] ?? config('mail.mailers.smtp.username', '');
        $settings['mail_smtp_encryption'] = $settings['mail_smtp_encryption'] ?? config('mail.mailers.smtp.encryption', 'tls');

        $settings['mail_ses_key'] = $settings['mail_ses_key'] ?? config('services.ses.key', '');
        $settings['mail_ses_region'] = $settings['mail_ses_region'] ?? config('services.ses.region', 'us-east-1');

        $settings['mail_mailgun_domain'] = $settings['mail_mailgun_domain'] ?? config('services.mailgun.domain', '');
        $settings['mail_mailgun_endpoint'] = $settings['mail_mailgun_endpoint'] ?? config('services.mailgun.endpoint', 'api.mailgun.net');

        $settings['mail_from_address'] = $settings['mail_from_address'] ?? config('mail.from.address', '');
        $settings['mail_from_name'] = $settings['mail_from_name'] ?? config('mail.from.name', '');

        $settings['upload_menu_image_max'] = (int) ($settings['upload_menu_image_max'] ?? 2048);
        $settings['upload_invoice_image_max'] = (int) ($settings['upload_invoice_image_max'] ?? 4096);

        // Password fields: send has_* flags instead of actual values
        $settings['has_smtp_password'] = ! empty(SystemSetting::get('mail_smtp_password'));
        $settings['has_ses_secret'] = ! empty(SystemSetting::get('mail_ses_secret'));
        $settings['has_mailgun_secret'] = ! empty(SystemSetting::get('mail_mailgun_secret'));

        // Never send passwords to frontend
        $settings['mail_smtp_password'] = '';
        $settings['mail_ses_secret'] = '';
        $settings['mail_mailgun_secret'] = '';

        return Inertia::render('super-admin/settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chatbot_similarity_threshold' => ['required', 'numeric', 'min:0.0', 'max:1.0'],
            'chatbot_max_suggestions' => ['required', 'integer', 'min:1', 'max:20'],
            'chatbot_cache_ttl' => ['required', 'integer', 'min:60', 'max:86400'],
            'mail_driver' => ['required', 'string', 'in:smtp,ses,mailgun'],
            'mail_smtp_host' => ['nullable', 'string', 'max:255'],
            'mail_smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_smtp_username' => ['nullable', 'string', 'max:255'],
            'mail_smtp_password' => ['nullable', 'string', 'max:255'],
            'mail_smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'mail_ses_key' => ['nullable', 'string', 'max:255'],
            'mail_ses_secret' => ['nullable', 'string', 'max:255'],
            'mail_ses_region' => ['nullable', 'string', 'max:100'],
            'mail_mailgun_domain' => ['nullable', 'string', 'max:255'],
            'mail_mailgun_secret' => ['nullable', 'string', 'max:255'],
            'mail_mailgun_endpoint' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'upload_menu_image_max' => ['required', 'integer', 'min:512', 'max:20480'],
            'upload_invoice_image_max' => ['required', 'integer', 'min:512', 'max:20480'],
        ]);

        $oldValues = [];
        $newValues = [];

        foreach ($this->settingKeys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            // Skip empty password fields to preserve existing values
            if (in_array($key, $this->passwordKeys, true) && ($data[$key] === null || $data[$key] === '')) {
                continue;
            }

            $oldValues[$key] = SystemSetting::get($key);
            SystemSetting::set($key, $data[$key]);
            $newValues[$key] = in_array($key, $this->passwordKeys, true) ? '******' : $data[$key];
        }

        // Mask old password values in audit log
        foreach ($this->passwordKeys as $pwKey) {
            if (isset($oldValues[$pwKey]) && $oldValues[$pwKey]) {
                $oldValues[$pwKey] = '******';
            }
        }

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'system_settings_update',
            'subject_type' => SystemSetting::class,
            'subject_id' => null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            app(ChatbotService::class)->reloadCache();
        } catch (\Throwable) {
        }

        return back()->with('success', 'Đã lưu cấu hình hệ thống thành công.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $throttleKey = 'test-email:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->with('error', "Vui lòng chờ {$seconds} giây trước khi gửi lại email thử.");
        }

        $data = $request->validate([
            'mail_driver' => ['required', 'string', 'in:smtp,ses,mailgun'],
            'mail_smtp_host' => ['nullable', 'string'],
            'mail_smtp_port' => ['nullable', 'integer'],
            'mail_smtp_username' => ['nullable', 'string'],
            'mail_smtp_password' => ['nullable', 'string'],
            'mail_smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'mail_ses_key' => ['nullable', 'string'],
            'mail_ses_secret' => ['nullable', 'string'],
            'mail_ses_region' => ['nullable', 'string'],
            'mail_mailgun_domain' => ['nullable', 'string'],
            'mail_mailgun_secret' => ['nullable', 'string'],
            'mail_mailgun_endpoint' => ['nullable', 'string'],
            'mail_from_address' => ['nullable', 'email'],
            'mail_from_name' => ['nullable', 'string'],
        ]);

        $driver = $data['mail_driver'];

        // Build temporary mailer config from form data (not yet saved)
        if ($driver === 'smtp') {
            config([
                'mail.mailers.test_smtp' => [
                    'transport' => 'smtp',
                    'host' => ($data['mail_smtp_host'] ?? null) ?: SystemSetting::get('mail_smtp_host', config('mail.mailers.smtp.host')),
                    'port' => (int) (($data['mail_smtp_port'] ?? null) ?: SystemSetting::get('mail_smtp_port', config('mail.mailers.smtp.port'))),
                    'username' => ($data['mail_smtp_username'] ?? null) ?: SystemSetting::get('mail_smtp_username', config('mail.mailers.smtp.username')),
                    'password' => ($data['mail_smtp_password'] ?? null) ?: SystemSetting::get('mail_smtp_password', config('mail.mailers.smtp.password')),
                    'encryption' => ($data['mail_smtp_encryption'] ?? null) === 'none' ? null : (($data['mail_smtp_encryption'] ?? null) ?: 'tls'),
                ],
            ]);
        }

        $fromAddress = ($data['mail_from_address'] ?? null) ?: SystemSetting::get('mail_from_address', config('mail.from.address'));
        $fromName = ($data['mail_from_name'] ?? null) ?: SystemSetting::get('mail_from_name', config('mail.from.name'));

        try {
            $mailerName = $driver === 'smtp' ? 'test_smtp' : $driver;

            Mail::mailer($mailerName)->raw(
                "Đây là email thử nghiệm từ Aventura.\nThời gian: ".now()->format('d/m/Y H:i:s')."\nDriver: {$driver}",
                function ($message) use ($request, $fromAddress, $fromName) {
                    $message->to($request->user()->email)
                        ->from($fromAddress, $fromName)
                        ->subject('Aventura — Email thử nghiệm cấu hình');
                }
            );

            RateLimiter::hit($throttleKey, 30);

            return back()->with('success', "Email thử nghiệm đã được gửi đến {$request->user()->email}. Kiểm tra hộp thư của bạn.");
        } catch (\Throwable $e) {
            RateLimiter::hit($throttleKey, 30);

            return back()->with('error', 'Gửi email thử thất bại: '.$e->getMessage());
        }
    }
}
