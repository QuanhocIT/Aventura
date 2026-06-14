<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    private array $settingKeys = [
        // Chatbot
        'chatbot_similarity_threshold',
        'chatbot_max_suggestions',
        'chatbot_cache_ttl',
        // Email SMTP
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
        // Upload Size
        'upload_menu_image_max',
        'upload_invoice_image_max',
    ];

    public function index(): Response
    {
        $settings = [];
        foreach ($this->settingKeys as $key) {
            $settings[$key] = SystemSetting::get($key);
        }

        // Apply defaults if null to make frontend binding easier
        $settings['chatbot_similarity_threshold'] = (float) ($settings['chatbot_similarity_threshold'] ?? 0.28);
        $settings['chatbot_max_suggestions'] = (int) ($settings['chatbot_max_suggestions'] ?? 5);
        $settings['chatbot_cache_ttl'] = (int) ($settings['chatbot_cache_ttl'] ?? 300);
        
        $settings['mail_driver'] = $settings['mail_driver'] ?? config('mail.default', 'smtp');
        $settings['mail_smtp_host'] = $settings['mail_smtp_host'] ?? config('mail.mailers.smtp.host', '');
        $settings['mail_smtp_port'] = (int) ($settings['mail_smtp_port'] ?? config('mail.mailers.smtp.port', 587));
        $settings['mail_smtp_username'] = $settings['mail_smtp_username'] ?? config('mail.mailers.smtp.username', '');
        $settings['mail_smtp_password'] = $settings['mail_smtp_password'] ?? config('mail.mailers.smtp.password', '');
        $settings['mail_smtp_encryption'] = $settings['mail_smtp_encryption'] ?? config('mail.mailers.smtp.encryption', 'tls');
        
        $settings['mail_ses_key'] = $settings['mail_ses_key'] ?? config('services.ses.key', '');
        $settings['mail_ses_secret'] = $settings['mail_ses_secret'] ?? config('services.ses.secret', '');
        $settings['mail_ses_region'] = $settings['mail_ses_region'] ?? config('services.ses.region', 'us-east-1');
        
        $settings['mail_mailgun_domain'] = $settings['mail_mailgun_domain'] ?? config('services.mailgun.domain', '');
        $settings['mail_mailgun_secret'] = $settings['mail_mailgun_secret'] ?? config('services.mailgun.secret', '');
        $settings['mail_mailgun_endpoint'] = $settings['mail_mailgun_endpoint'] ?? config('services.mailgun.endpoint', 'api.mailgun.net');
        
        $settings['mail_from_address'] = $settings['mail_from_address'] ?? config('mail.from.address', '');
        $settings['mail_from_name'] = $settings['mail_from_name'] ?? config('mail.from.name', '');

        $settings['upload_menu_image_max'] = (int) ($settings['upload_menu_image_max'] ?? 2048);
        $settings['upload_invoice_image_max'] = (int) ($settings['upload_invoice_image_max'] ?? 4096);

        return Inertia::render('super-admin/settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Chatbot
            'chatbot_similarity_threshold' => ['required', 'numeric', 'min:0.0', 'max:1.0'],
            'chatbot_max_suggestions'      => ['required', 'integer', 'min:1', 'max:20'],
            'chatbot_cache_ttl'            => ['required', 'integer', 'min:60', 'max:86400'],
            // Email Driver Switching
            'mail_driver'                  => ['required', 'string', 'in:smtp,ses,mailgun'],
            // SMTP Settings
            'mail_smtp_host'               => ['nullable', 'string', 'max:255'],
            'mail_smtp_port'               => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_smtp_username'           => ['nullable', 'string', 'max:255'],
            'mail_smtp_password'           => ['nullable', 'string', 'max:255'],
            'mail_smtp_encryption'         => ['nullable', 'string', 'in:tls,ssl,none'],
            // SES Settings
            'mail_ses_key'                 => ['nullable', 'string', 'max:255'],
            'mail_ses_secret'              => ['nullable', 'string', 'max:255'],
            'mail_ses_region'              => ['nullable', 'string', 'max:100'],
            // Mailgun Settings
            'mail_mailgun_domain'          => ['nullable', 'string', 'max:255'],
            'mail_mailgun_secret'          => ['nullable', 'string', 'max:255'],
            'mail_mailgun_endpoint'        => ['nullable', 'string', 'max:255'],
            // Mail From Address
            'mail_from_address'            => ['nullable', 'email', 'max:255'],
            'mail_from_name'               => ['nullable', 'string', 'max:255'],
            // Upload Settings
            'upload_menu_image_max'        => ['required', 'integer', 'min:512', 'max:20480'], // 512KB - 20MB
            'upload_invoice_image_max'     => ['required', 'integer', 'min:512', 'max:20480'],
        ]);

        $oldValues = [];
        $newValues = [];

        foreach ($this->settingKeys as $key) {
            if (array_key_exists($key, $data)) {
                $oldValues[$key] = SystemSetting::get($key);
                SystemSetting::set($key, $data[$key]);
                $newValues[$key] = $data[$key];
            }
        }

        // Log setting updates in AuditLog
        AuditLog::create([
            'restaurant_id' => null,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => 'system_settings_update',
            'subject_type'  => SystemSetting::class,
            'subject_id'    => null,
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        // Attempt to reload the Python Chatbot cache if the service is online
        try {
            app(\App\Services\ChatbotService::class)->reloadCache();
        } catch (\Throwable $e) {
            // Chatbot service offline, skip reload
        }

        return back()->with('success', 'Đã lưu cấu hình hệ thống thành công.');
    }
}
