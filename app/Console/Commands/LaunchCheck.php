<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tự kiểm tra cấu hình TRƯỚC KHI go-live production.
 *
 *   php artisan launch:check
 *
 * Báo PASS / CẢNH BÁO / LỖI cho từng hạng mục quan trọng (debug, secret, DB, mail,
 * thanh toán, realtime, backup...). Không sửa gì — chỉ soi và chỉ ra chỗ chưa sẵn sàng.
 */
class LaunchCheck extends Command
{
    protected $signature = 'launch:check';

    protected $description = 'Kiểm tra cấu hình production trước khi mở bán (không sửa gì).';

    private int $fail = 0;
    private int $warn = 0;
    private int $pass = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>KIỂM TRA SẴN SÀNG PRODUCTION — Aventura</>');
        $this->line('  ' . str_repeat('─', 58));

        // ── Cốt lõi & bảo mật ────────────────────────────────────────────────
        $this->section('Cốt lõi & bảo mật');
        $this->assert('APP_ENV = production', app()->environment('production'),
            "đang là '" . app()->environment() . "'", 'warn');
        $this->assert('APP_DEBUG = false', config('app.debug') === false,
            'APP_DEBUG=true sẽ LỘ thông tin nhạy cảm ra người dùng', 'fail');
        $this->assert('APP_KEY đã đặt', ! empty(config('app.key')),
            'chạy: php artisan key:generate', 'fail');
        $appUrl = (string) config('app.url');
        $this->assert('APP_URL là domain thật', $appUrl !== '' && ! preg_match('/localhost|127\.0\.0\.1|ngrok|\.test/i', $appUrl),
            "đang là '{$appUrl}' — đổi sang https://ten-mien-that", 'fail');
        $this->assert('HTTPS: SESSION_SECURE_COOKIE = true', config('session.secure') === true,
            'cần true khi chạy HTTPS để cookie an toàn', 'warn');

        // ── Cơ sở dữ liệu ────────────────────────────────────────────────────
        $this->section('Cơ sở dữ liệu');
        $dbOk = false;
        try { DB::connection()->getPdo(); $dbOk = true; } catch (\Throwable $e) {}
        $this->assert('Kết nối DB', $dbOk, 'không kết nối được MySQL', 'fail');
        if ($dbOk) {
            $pending = $this->pendingMigrations();
            $this->assert('Migration đã chạy hết', $pending === 0,
                "{$pending} migration chưa chạy — chạy: php artisan migrate --force", 'warn');
        }

        // ── Hạ tầng runtime ──────────────────────────────────────────────────
        $this->section('Cache / Queue / Realtime');
        $cacheOk = false;
        $token = bin2hex(random_bytes(8));
        try { Cache::put('__launch_check', $token, 5); $cacheOk = Cache::get('__launch_check') === $token; } catch (\Throwable $e) {}
        $this->assert('Cache hoạt động', $cacheOk, 'không ghi/đọc được cache', 'fail');
        $this->assert('QUEUE dùng redis (không sync)', config('queue.default') === 'redis',
            "đang '" . config('queue.default') . "' — production nên dùng redis + queue worker", 'warn');
        $this->assert('Realtime: broadcast = reverb', config('broadcasting.default') === 'reverb',
            'KDS/thông báo realtime cần Reverb', 'warn');
        $reverbKey = config('reverb.apps.apps.0.key') ?? env('REVERB_APP_KEY');
        $this->assert('Reverb đã có key', ! empty($reverbKey),
            'điền REVERB_APP_ID/KEY/SECRET (sinh bộ MỚI, không tái dùng bộ dev)', 'warn');

        // ── Email & Thanh toán ───────────────────────────────────────────────
        $this->section('Email & Thu tiền');
        $mailer = config('mail.default');
        $this->assert('Mail không dùng driver log', ! in_array($mailer, ['log', 'array'], true),
            "đang '{$mailer}' — email sẽ KHÔNG gửi thật; cấu hình SMTP/Brevo", 'warn');
        $this->assert('SePay: số tài khoản đã đặt', ! empty(config('services.sepay.account_number')),
            'cần để thu tiền gói của tenant (SEPAY_ACCOUNT_NUMBER)', 'warn');
        $this->assert('SePay: webhook secret đã đặt', ! empty(config('billing.webhook_secret')),
            'cần để xác thực webhook thanh toán (BILLING_WEBHOOK_SECRET)', 'warn');

        // ── Giám sát & Backup ────────────────────────────────────────────────
        $this->section('Giám sát & Backup');
        $this->assert('Sentry DSN (error tracking)', ! empty(config('sentry.dsn') ?? env('SENTRY_LARAVEL_DSN')),
            'nên bật để nhận cảnh báo lỗi production', 'warn');
        $this->assert('S3 backup đã cấu hình', ! empty(config('filesystems.disks.s3.bucket')),
            'nên đẩy backup DB ra ngoài VPS (AWS_* / Backblaze B2)', 'warn');

        // ── Lưu trữ ──────────────────────────────────────────────────────────
        $this->section('Lưu trữ');
        $this->assert('storage/ ghi được', is_writable(storage_path()), 'chmod/chown storage', 'fail');
        $this->assert('bootstrap/cache ghi được', is_writable(base_path('bootstrap/cache')), 'chmod/chown bootstrap/cache', 'fail');

        // ── Secret đã lộ (nhắc xoay) ─────────────────────────────────────────
        $this->section('Secret đã lộ ở môi trường dev (nhắc XOAY VÒNG)');
        $leaked = str_contains($appUrl, 'ngrok')
            || str_contains((string) config('mail.from.address'), 'duongndph53424@gmail.com');
        $this->assert('Đã rời cấu hình dev (secret dev)', ! $leaked,
            'các secret Google/Gmail/Brevo/webhook đã lộ trong dev — SINH MỚI trước khi mở bán', 'warn');

        // ── Tổng kết ─────────────────────────────────────────────────────────
        $this->newLine();
        $this->line('  ' . str_repeat('─', 58));
        $this->line(sprintf('  <fg=green>PASS %d</>   <fg=yellow>CẢNH BÁO %d</>   <fg=red>LỖI %d</>',
            $this->pass, $this->warn, $this->fail));

        if ($this->fail > 0) {
            $this->error('  ✗ Có LỖI chặn — chưa nên mở bán. Sửa các mục LỖI ở trên.');
            return self::FAILURE;
        }
        if ($this->warn > 0) {
            $this->warn('  ! Chạy được nhưng còn cảnh báo — nên xử lý trước khi phục vụ khách thật.');
            return self::SUCCESS;
        }
        $this->info('  ✓ Sẵn sàng go-live!');
        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("  <options=bold;fg=cyan>{$title}</>");
    }

    private function assert(string $label, bool $ok, string $hint, string $severity = 'fail'): void
    {
        if ($ok) {
            $this->pass++;
            $this->line("    <fg=green>✓</> {$label}");

            return;
        }

        if ($severity === 'warn') {
            $this->warn++;
            $this->line("    <fg=yellow>!</> {$label} <fg=gray>— {$hint}</>");
        } else {
            $this->fail++;
            $this->line("    <fg=red>✗</> {$label} <fg=gray>— {$hint}</>");
        }
    }

    private function pendingMigrations(): int
    {
        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = glob(database_path('migrations/**/*.php'));
            $files = array_merge($files, glob(database_path('migrations/*.php')));
            $all = array_map(fn ($f) => basename($f, '.php'), $files);

            return count(array_diff($all, $ran));
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
