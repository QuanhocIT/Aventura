<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Domain suffixes/names reserved for testing (RFC 2606) that can never receive real mail.
     */
    private const FAKE_EMAIL_DOMAINS_SUFFIXES = ['.test', '.example', '.invalid', '.localhost'];
    private const FAKE_EMAIL_DOMAINS_EXACT = ['example.com', 'example.org', 'example.net'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_code')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_code_expires_at')->nullable()->after('email_verification_code');
        });

        // Bắt buộc xác thực lại với MỌI tài khoản dùng email thật; các tài khoản
        // dùng domain test (@*.test, @example.com...) không thể nhận email nên
        // được giữ nguyên trạng thái đã xác thực để tránh bị khóa vĩnh viễn.
        DB::table('users')->whereNotNull('email_verified_at')->orderBy('id')->each(function (object $user) {
            $domain = Str::lower(Str::after($user->email, '@'));

            $isFake = in_array($domain, self::FAKE_EMAIL_DOMAINS_EXACT, true)
                || collect(self::FAKE_EMAIL_DOMAINS_SUFFIXES)->contains(fn (string $suffix) => Str::endsWith($domain, $suffix));

            if (! $isFake) {
                DB::table('users')->where('id', $user->id)->update(['email_verified_at' => null]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verification_code', 'email_verification_code_expires_at']);
        });
    }
};
