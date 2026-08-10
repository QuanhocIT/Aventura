<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BriefcaseBusiness,
    CalendarDays,
    CheckCircle2,
    Clock3,
    KeyRound,
    Mail,
    MapPin,
    MessageSquareQuote,
    Phone,
    ShieldCheck,
    Star,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

type Employee = {
    full_name: string;
    employee_code: string;
    email?: string | null;
    phone?: string | null;
    date_of_birth?: string | null;
    gender?: string | null;
    address?: string | null;
    job_title?: string | null;
    employment_type?: string | null;
    hire_date?: string | null;
    status?: string | null;
    branch_name?: string | null;
    role_name?: string | null;
    rating_star?: number;
    rating_count?: number;
};

type Account = {
    name: string;
    email: string;
    phone?: string | null;
    status?: string | null;
    email_verified: boolean;
    last_login_at?: string | null;
    roles: string[];
};

type Review = {
    id: string;
    rating: number;
    overall_rating: number;
    comment?: string | null;
    reviewer_name: string;
    created_at?: string | null;
};

const props = defineProps<{
    employee: Employee;
    account: Account;
    reviews: Review[];
}>();

defineOptions({ layout: AppLayout });

const showPasswordForm = ref(false);

const initials = computed(() =>
    props.employee.full_name
        .split(' ')
        .filter(Boolean)
        .slice(-2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase(),
);

const roleLabel = computed(() => {
    const labels: Record<string, string> = {
        cashier: 'Thu ngân',
        waiter: 'Phục vụ',
        kitchen: 'Nhân viên bếp',
        inventory_staff: 'Nhân viên kho',
        shipper: 'Nhân viên giao hàng',
    };

    return (
        labels[props.employee.role_name ?? ''] ??
        props.employee.role_name ??
        'Nhân viên'
    );
});

const employmentTypeLabel = computed(() => {
    const labels: Record<string, string> = {
        full_time: 'Toàn thời gian',
        part_time: 'Bán thời gian',
        seasonal: 'Thời vụ',
    };

    return labels[props.employee.employment_type ?? ''] ?? 'Chưa cập nhật';
});

const accountRoles = computed(() =>
    props.account.roles
        .map((role) => roleLabelFromKey(role))
        .filter(Boolean)
        .join(', '),
);

const roleLabelFromKey = (role: string) => {
    const labels: Record<string, string> = {
        cashier: 'Thu ngân',
        waiter: 'Phục vụ',
        kitchen: 'Bếp',
        inventory_staff: 'Kho',
        shipper: 'Giao hàng',
    };

    return labels[role] ?? role;
};

const displayValue = (value?: string | null) => value || 'Chưa cập nhật';
</script>

<template>
    <Head title="Hồ sơ cá nhân" />

    <div class="min-h-full bg-muted/30 pb-10 dark:bg-background">
        <section
            class="mx-auto w-full max-w-[1600px] space-y-6 px-5 py-6 xl:px-8 xl:py-8"
        >
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.16em] text-primary uppercase"
                    >
                        Khu vực nhân viên
                    </p>
                    <h1
                        class="mt-1 text-3xl font-black tracking-tight text-foreground"
                    >
                        Hồ sơ cá nhân
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Thông tin nhân viên, tài khoản đăng nhập và đánh giá từ
                        khách hàng.
                    </p>
                </div>

                <Link
                    href="/employee-portal"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-background px-4 py-2.5 text-sm font-semibold text-foreground shadow-sm transition hover:border-primary/40 hover:text-primary"
                >
                    <ArrowLeft class="size-4" />
                    Về cổng nhân sự
                </Link>
            </div>

            <section
                class="relative overflow-hidden rounded-3xl border border-border bg-card p-6 shadow-sm sm:p-8"
            >
                <div
                    class="absolute -top-24 -right-24 size-64 rounded-full bg-primary/5 blur-3xl"
                />
                <div
                    class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex size-20 shrink-0 items-center justify-center rounded-3xl bg-primary text-2xl font-black text-primary-foreground shadow-lg shadow-primary/20"
                        >
                            {{ initials || 'NV' }}
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2
                                    class="text-2xl font-black tracking-tight text-foreground"
                                >
                                    {{ employee.full_name }}
                                </h2>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 class="size-3.5" />
                                    {{
                                        employee.status === 'active'
                                            ? 'Đang làm việc'
                                            : 'Không hoạt động'
                                    }}
                                </span>
                            </div>
                            <p
                                class="mt-1 text-sm font-medium text-muted-foreground"
                            >
                                {{ displayValue(employee.job_title) }} · Mã nhân
                                viên {{ employee.employee_code }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-4 rounded-2xl border border-border bg-muted/40 px-5 py-4"
                    >
                        <Star class="size-7 fill-amber-400 text-amber-400" />
                        <div>
                            <p class="text-2xl font-black text-foreground">
                                {{
                                    employee.rating_count
                                        ? Number(employee.rating_star).toFixed(
                                              1,
                                          )
                                        : '—'
                                }}
                                <span
                                    v-if="employee.rating_count"
                                    class="text-sm font-semibold text-muted-foreground"
                                    >/ 5</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ employee.rating_count || 0 }} đánh giá từ
                                khách hàng
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section
                    class="rounded-3xl border border-border bg-card shadow-sm"
                >
                    <div class="border-b border-border px-6 py-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                            >
                                <UserRound class="size-5" />
                            </div>
                            <div>
                                <h2 class="font-bold text-foreground">
                                    Thông tin nhân viên
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    Thông tin công việc và liên hệ của bạn.
                                </p>
                            </div>
                        </div>
                    </div>

                    <dl class="grid gap-x-6 gap-y-5 px-6 py-6 sm:grid-cols-2">
                        <div>
                            <dt
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Chức vụ
                            </dt>
                            <dd
                                class="mt-1 flex items-center gap-2 text-sm font-semibold text-foreground"
                            >
                                <BriefcaseBusiness
                                    class="size-4 text-primary"
                                />
                                {{ roleLabel }} · {{ employmentTypeLabel }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Chi nhánh
                            </dt>
                            <dd
                                class="mt-1 flex items-center gap-2 text-sm font-semibold text-foreground"
                            >
                                <MapPin class="size-4 text-primary" />
                                {{ displayValue(employee.branch_name) }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Email công việc
                            </dt>
                            <dd
                                class="mt-1 flex items-center gap-2 text-sm font-semibold break-all text-foreground"
                            >
                                <Mail class="size-4 shrink-0 text-primary" />
                                {{ displayValue(employee.email) }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Số điện thoại
                            </dt>
                            <dd
                                class="mt-1 flex items-center gap-2 text-sm font-semibold text-foreground"
                            >
                                <Phone class="size-4 text-primary" />
                                {{ displayValue(employee.phone) }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Ngày vào làm
                            </dt>
                            <dd
                                class="mt-1 flex items-center gap-2 text-sm font-semibold text-foreground"
                            >
                                <CalendarDays class="size-4 text-primary" />
                                {{ displayValue(employee.hire_date) }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Ngày sinh
                            </dt>
                            <dd
                                class="mt-1 text-sm font-semibold text-foreground"
                            >
                                {{ displayValue(employee.date_of_birth) }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Địa chỉ
                            </dt>
                            <dd
                                class="mt-1 text-sm font-semibold text-foreground"
                            >
                                {{ displayValue(employee.address) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-3xl border border-border bg-card shadow-sm"
                >
                    <div class="border-b border-border px-6 py-5">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex size-10 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                            >
                                <ShieldCheck class="size-5" />
                            </div>
                            <div>
                                <h2 class="font-bold text-foreground">
                                    Tài khoản đăng nhập
                                </h2>
                                <p class="text-xs text-muted-foreground">
                                    Thông tin tài khoản đang sử dụng trên hệ
                                    thống.
                                </p>
                            </div>
                        </div>
                    </div>

                    <dl class="space-y-5 px-6 py-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <dt
                                    class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Tên tài khoản
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-semibold text-foreground"
                                >
                                    {{ account.name }}
                                </dd>
                            </div>
                            <UserRound class="size-4 text-muted-foreground" />
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <dt
                                    class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Email đăng nhập
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-semibold break-all text-foreground"
                                >
                                    {{ account.email }}
                                </dd>
                            </div>
                            <Mail
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <dt
                                    class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Vai trò
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-semibold text-foreground"
                                >
                                    {{ accountRoles || 'Nhân viên' }}
                                </dd>
                            </div>
                            <BriefcaseBusiness
                                class="size-4 text-muted-foreground"
                            />
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <dt
                                    class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Trạng thái email
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-semibold text-foreground"
                                >
                                    {{
                                        account.email_verified
                                            ? 'Đã xác minh'
                                            : 'Chưa xác minh'
                                    }}
                                </dd>
                            </div>
                            <CheckCircle2
                                :class="
                                    account.email_verified
                                        ? 'text-emerald-500'
                                        : 'text-muted-foreground'
                                "
                                class="size-4"
                            />
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <dt
                                    class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Đăng nhập gần nhất
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-semibold text-foreground"
                                >
                                    {{ displayValue(account.last_login_at) }}
                                </dd>
                            </div>
                            <Clock3 class="size-4 text-muted-foreground" />
                        </div>
                    </dl>

                    <div class="border-t border-border px-6 py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-bold text-foreground">
                                    Đổi mật khẩu đăng nhập
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Mật khẩu hiện tại không thể xem lại. Bạn cần nhập mật khẩu hiện tại để đặt mật khẩu mới.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="shrink-0 rounded-xl"
                                @click="showPasswordForm = !showPasswordForm"
                            >
                                <KeyRound class="size-4" />
                                {{ showPasswordForm ? 'Đóng' : 'Đổi mật khẩu' }}
                            </Button>
                        </div>

                        <Form
                            v-if="showPasswordForm"
                            v-bind="SecurityController.update.form()"
                            :options="{ preserveScroll: true }"
                            reset-on-success
                            :reset-on-error="[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]"
                            class="mt-5 space-y-4"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <label
                                    for="employee_current_password"
                                    class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Mật khẩu hiện tại
                                </label>
                                <PasswordInput
                                    id="employee_current_password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    placeholder="Nhập mật khẩu hiện tại"
                                    class="rounded-xl"
                                />
                                <InputError :message="errors.current_password" />
                            </div>

                            <div class="grid gap-2">
                                <label
                                    for="employee_new_password"
                                    class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Mật khẩu mới
                                </label>
                                <PasswordInput
                                    id="employee_new_password"
                                    name="password"
                                    autocomplete="new-password"
                                    placeholder="Nhập mật khẩu mới"
                                    class="rounded-xl"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <label
                                    for="employee_password_confirmation"
                                    class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                                >
                                    Xác nhận mật khẩu mới
                                </label>
                                <PasswordInput
                                    id="employee_password_confirmation"
                                    name="password_confirmation"
                                    autocomplete="new-password"
                                    placeholder="Nhập lại mật khẩu mới"
                                    class="rounded-xl"
                                />
                                <InputError
                                    :message="errors.password_confirmation"
                                />
                            </div>

                            <div class="flex justify-end pt-1">
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    class="rounded-xl"
                                >
                                    Lưu mật khẩu
                                </Button>
                            </div>
                        </Form>
                    </div>
                </section>
            </div>

            <section class="rounded-3xl border border-border bg-card shadow-sm">
                <div
                    class="flex flex-col justify-between gap-3 border-b border-border px-6 py-5 sm:flex-row sm:items-center"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-500"
                        >
                            <MessageSquareQuote class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-bold text-foreground">
                                Đánh giá từ khách hàng
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Những nhận xét được ghi nhận cho bạn trong quá
                                trình phục vụ.
                            </p>
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-muted px-3 py-1 text-xs font-bold text-muted-foreground"
                    >
                        {{ reviews.length }} nhận xét gần đây
                    </span>
                </div>

                <div
                    v-if="reviews.length"
                    class="grid gap-4 p-6 md:grid-cols-2"
                >
                    <article
                        v-for="review in reviews"
                        :key="review.id"
                        class="rounded-2xl border border-border bg-muted/30 p-5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-foreground">
                                    {{ review.reviewer_name }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ review.created_at }}
                                </p>
                            </div>
                            <div
                                class="flex items-center gap-0.5"
                                :aria-label="`${review.rating} sao`"
                            >
                                <Star
                                    v-for="star in 5"
                                    :key="star"
                                    class="size-4"
                                    :class="
                                        star <= review.rating
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-muted-foreground/30'
                                    "
                                />
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-muted-foreground">
                            {{
                                review.comment ||
                                'Khách hàng không để lại nhận xét chi tiết.'
                            }}
                        </p>
                    </article>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center px-6 py-14 text-center"
                >
                    <MessageSquareQuote
                        class="size-10 text-muted-foreground/40"
                    />
                    <h3 class="mt-3 font-bold text-foreground">
                        Chưa có nhận xét riêng
                    </h3>
                    <p class="mt-1 max-w-md text-sm text-muted-foreground">
                        Khi khách hàng đánh giá bạn trong đơn hàng, nhận xét sẽ
                        xuất hiện tại đây.
                    </p>
                </div>
            </section>
        </section>
    </div>
</template>
