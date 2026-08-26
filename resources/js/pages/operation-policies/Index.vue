<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BadgeCheck,
    Ban,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    CircleAlert,
    Clock3,
    FileSearch2,
    History,
    Info,
    Landmark,
    LockKeyhole,
    RotateCcw,
    Save,
    Search,
    Shield,
    ShieldCheck,
    SlidersHorizontal,
    UserRound,
    UsersRound,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Policy = {
    id: number;
    restaurant_id: number;
    max_discount_percent_staff: number | string;
    max_discount_percent_manager: number | string;
    max_cancel_amount_staff: number | string;
    max_cancel_amount_manager: number | string;
    staff_view_revenue: boolean;
    staff_view_salary: boolean;
    staff_view_cost_price: boolean;
    manager_view_other_salary: boolean;
    restrict_to_shift_hours: boolean;
    audit_all_changes: boolean;
    created_at?: string | null;
    updated_at?: string | null;
};

type AuditLog = {
    id: number;
    action: string;
    user_name: string;
    user_role: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
};

type TabKey = 'policy' | 'audit';
type PreviewMode = 'discount' | 'cancel';
type PreviewRole = 'staff' | 'manager';
type PolicyForm = {
    max_discount_percent_staff: number;
    max_discount_percent_manager: number;
    max_cancel_amount_staff: number;
    max_cancel_amount_manager: number;
    staff_view_revenue: boolean;
    staff_view_salary: boolean;
    staff_view_cost_price: boolean;
    manager_view_other_salary: boolean;
    restrict_to_shift_hours: boolean;
    audit_all_changes: boolean;
};

const props = defineProps<{ policy: Policy; recentAudit: AuditLog[] }>();
const page = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        const messages = flash as { success?: string; error?: string };

        if (messages?.success) {
            toast.success(messages.success);
        }

        if (messages?.error) {
            toast.error(messages.error);
        }
    },
    { deep: true },
);

const activeTab = ref<TabKey>('policy');
const auditSearch = ref('');
const auditFilter = ref<'all' | 'policy' | 'bypass' | 'approval'>('all');
const expandedAuditId = ref<number | null>(null);
const previewMode = ref<PreviewMode>('discount');
const previewRole = ref<PreviewRole>('staff');
const previewValue = ref(15);

const form = useForm<PolicyForm>({
    max_discount_percent_staff: Number(
        props.policy.max_discount_percent_staff ?? 10,
    ),
    max_discount_percent_manager: Number(
        props.policy.max_discount_percent_manager ?? 30,
    ),
    max_cancel_amount_staff: Number(props.policy.max_cancel_amount_staff ?? 0),
    max_cancel_amount_manager: Number(
        props.policy.max_cancel_amount_manager ?? 500000,
    ),
    staff_view_revenue: Boolean(props.policy.staff_view_revenue),
    staff_view_salary: Boolean(props.policy.staff_view_salary),
    staff_view_cost_price: Boolean(props.policy.staff_view_cost_price),
    manager_view_other_salary: Boolean(props.policy.manager_view_other_salary),
    restrict_to_shift_hours: Boolean(props.policy.restrict_to_shift_hours),
    audit_all_changes: Boolean(props.policy.audit_all_changes),
});

function formSnapshot(): string {
    return JSON.stringify({
        max_discount_percent_staff: Number(form.max_discount_percent_staff),
        max_discount_percent_manager: Number(form.max_discount_percent_manager),
        max_cancel_amount_staff: Number(form.max_cancel_amount_staff),
        max_cancel_amount_manager: Number(form.max_cancel_amount_manager),
        staff_view_revenue: Boolean(form.staff_view_revenue),
        staff_view_salary: Boolean(form.staff_view_salary),
        staff_view_cost_price: Boolean(form.staff_view_cost_price),
        manager_view_other_salary: Boolean(form.manager_view_other_salary),
        restrict_to_shift_hours: Boolean(form.restrict_to_shift_hours),
        audit_all_changes: Boolean(form.audit_all_changes),
    });
}

const savedSnapshot = ref(formSnapshot());
const hasChanges = computed(() => formSnapshot() !== savedSnapshot.value);

watch(
    () => props.policy,
    (policy) => {
        form.max_discount_percent_staff = Number(
            policy.max_discount_percent_staff,
        );
        form.max_discount_percent_manager = Number(
            policy.max_discount_percent_manager,
        );
        form.max_cancel_amount_staff = Number(policy.max_cancel_amount_staff);
        form.max_cancel_amount_manager = Number(
            policy.max_cancel_amount_manager,
        );
        form.staff_view_revenue = Boolean(policy.staff_view_revenue);
        form.staff_view_salary = Boolean(policy.staff_view_salary);
        form.staff_view_cost_price = Boolean(policy.staff_view_cost_price);
        form.manager_view_other_salary = Boolean(
            policy.manager_view_other_salary,
        );
        form.restrict_to_shift_hours = Boolean(policy.restrict_to_shift_hours);
        form.audit_all_changes = Boolean(policy.audit_all_changes);
        savedSnapshot.value = formSnapshot();
    },
    { deep: true },
);

function formatNumber(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString('vi-VN', {
        maximumFractionDigits: 2,
    });
}

function formatMoney(value: number | string | null | undefined): string {
    return `${formatNumber(value)}đ`;
}

function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('vi-VN', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          });
}

function formatAuditValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'Bật' : 'Tắt';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
}

function actionLabel(action: string): string {
    return (
        {
            policy_updated: 'Cập nhật chính sách',
            policy_check: 'Kiểm tra quyền',
            discount_applied: 'Áp dụng giảm giá',
            discount_applied_bypass: 'Bypass giảm giá',
            price_discount_bypass: 'Bypass đổi giá',
            order_cancelled_bypass: 'Bypass hủy đơn',
            order_item_cancel_requested: 'Yêu cầu hủy món',
            order_item_lock_bypass: 'Bypass khóa món',
            refund_requested: 'Yêu cầu hoàn tiền',
            order_refund_processed: 'Xử lý hoàn tiền',
        }[action] ?? action
    );
}

function actionTone(action: string): string {
    if (action.includes('bypass')) {
        return 'border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-300';
    }

    if (action.includes('requested')) {
        return 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-300';
    }

    return 'border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-300';
}

function setTab(value: string): void {
    activeTab.value = value === 'audit' ? 'audit' : 'policy';
}

function setAuditFilter(value: string): void {
    auditFilter.value =
        value === 'policy' || value === 'bypass' || value === 'approval'
            ? value
            : 'all';
}

const discountStaff = computed(() => Number(form.max_discount_percent_staff));
const discountManager = computed(() =>
    Number(form.max_discount_percent_manager),
);
const cancelStaff = computed(() => Number(form.max_cancel_amount_staff));
const cancelManager = computed(() => Number(form.max_cancel_amount_manager));

const protectedDataCount = computed(
    () =>
        [
            form.staff_view_revenue,
            form.staff_view_salary,
            form.staff_view_cost_price,
            form.manager_view_other_salary,
        ].filter(Boolean).length,
);

const riskItems = computed(() => {
    const items: {
        title: string;
        detail: string;
        tone: 'danger' | 'warning' | 'info';
    }[] = [];

    if (!form.audit_all_changes) {
        items.push({
            title: 'Audit đang bị tắt',
            detail: 'Các thay đổi và thao tác nhạy cảm có thể không đủ dấu vết để đối soát.',
            tone: 'danger',
        });
    }

    if (discountStaff.value > 20 || discountManager.value > 50) {
        items.push({
            title: 'Hạn mức giảm giá cao',
            detail: 'Nên kiểm tra lại biên lợi nhuận và yêu cầu bypass khi vượt hạn mức.',
            tone: 'warning',
        });
    }

    if (cancelStaff.value > 0) {
        items.push({
            title: 'Nhân viên được hủy đơn trực tiếp',
            detail: `Nhân viên có thể hủy đơn đến ${formatMoney(cancelStaff.value)} mà không cần bypass.`,
            tone: 'warning',
        });
    }

    if (!form.restrict_to_shift_hours) {
        items.push({
            title: 'Không giới hạn theo giờ ca',
            detail: 'Thao tác nhạy cảm vẫn có thể được thực hiện ngoài ca làm việc.',
            tone: 'info',
        });
    }

    return items;
});

const policyScore = computed(() => {
    let score = 100;

    if (!form.audit_all_changes) {
        score -= 30;
    }

    if (!form.restrict_to_shift_hours) {
        score -= 10;
    }

    if (discountStaff.value > 20 || discountManager.value > 50) {
        score -= 15;
    }

    if (cancelStaff.value > 0) {
        score -= 10;
    }

    return Math.max(0, score);
});

const policyScoreLabel = computed(() =>
    policyScore.value >= 85
        ? 'Kiểm soát tốt'
        : policyScore.value >= 65
          ? 'Cần theo dõi'
          : 'Rủi ro cao',
);

const previewResult = computed(() => {
    const value = Math.max(0, Number(previewValue.value) || 0);
    const isStaff = previewRole.value === 'staff';

    if (previewMode.value === 'discount') {
        const max = isStaff ? discountStaff.value : discountManager.value;

        return value <= max
            ? {
                  allowed: true,
                  title: 'Được phép thực hiện',
                  detail: `Mức ${formatNumber(value)}% nằm trong hạn mức ${formatNumber(max)}%.`,
              }
            : {
                  allowed: false,
                  title: 'Cần phê duyệt / bypass',
                  detail: `Mức ${formatNumber(value)}% vượt hạn mức ${formatNumber(max)}% của ${isStaff ? 'nhân viên' : 'quản lý'}.`,
              };
    }

    const max = isStaff ? cancelStaff.value : cancelManager.value;

    if (isStaff && max === 0) {
        return {
            allowed: false,
            title: 'Bị chặn',
            detail: 'Nhân viên không được hủy đơn trực tiếp theo chính sách hiện tại.',
        };
    }

    if (max > 0 && value > max) {
        return {
            allowed: false,
            title: 'Cần phê duyệt / bypass',
            detail: `Giá trị ${formatMoney(value)} vượt hạn mức ${formatMoney(max)}.`,
        };
    }

    return {
        allowed: true,
        title: 'Được phép thực hiện',
        detail:
            max > 0
                ? `Giá trị ${formatMoney(value)} nằm trong hạn mức ${formatMoney(max)}.`
                : 'Không có giới hạn giá trị cho vai trò này.',
    };
});

const filteredAudit = computed(() => {
    const query = auditSearch.value.trim().toLowerCase();

    return props.recentAudit.filter((log) => {
        const matchesQuery =
            !query ||
            [
                log.action,
                log.user_name,
                log.user_role ?? '',
                log.ip_address ?? '',
            ]
                .join(' ')
                .toLowerCase()
                .includes(query);

        if (!matchesQuery) {
            return false;
        }

        if (auditFilter.value === 'policy') {
            return log.action === 'policy_updated';
        }

        if (auditFilter.value === 'bypass') {
            return log.action.includes('bypass');
        }

        if (auditFilter.value === 'approval') {
            return log.action.includes('requested');
        }

        return true;
    });
});

const auditBypassCount = computed(
    () =>
        props.recentAudit.filter((log) => log.action.includes('bypass')).length,
);
const auditApprovalCount = computed(
    () =>
        props.recentAudit.filter((log) => log.action.includes('requested'))
            .length,
);

const dataAccessRows = [
    {
        key: 'staff_view_revenue' as const,
        label: 'Doanh thu',
        description: 'Báo cáo doanh thu và số liệu bán hàng tổng hợp.',
        role: 'Nhân viên',
    },
    {
        key: 'staff_view_salary' as const,
        label: 'Lương cá nhân',
        description: 'Cho phép nhân viên xem bảng lương của chính mình.',
        role: 'Nhân viên',
    },
    {
        key: 'staff_view_cost_price' as const,
        label: 'Giá vốn nguyên liệu',
        description: 'Thông tin giá vốn và chi phí đầu vào của món ăn.',
        role: 'Nhân viên',
    },
    {
        key: 'manager_view_other_salary' as const,
        label: 'Lương nhân viên khác',
        description: 'Cho phép quản lý xem lương của nhân sự trong nhà hàng.',
        role: 'Quản lý',
    },
];

function resetForm(): void {
    form.reset();
    form.clearErrors();
}

function submit(): void {
    form.transform((data: PolicyForm) => ({
        ...data,
        max_discount_percent_staff: Number(data.max_discount_percent_staff),
        max_discount_percent_manager: Number(data.max_discount_percent_manager),
        max_cancel_amount_staff: Number(data.max_cancel_amount_staff),
        max_cancel_amount_manager: Number(data.max_cancel_amount_manager),
    })).post('/operation-policies', {
        preserveScroll: true,
        onSuccess: () => {
            savedSnapshot.value = formSnapshot();
            toast.success('Đã lưu chính sách và cập nhật ma trận kiểm soát.');
        },
        onError: () =>
            toast.error('Vui lòng kiểm tra lại các hạn mức trước khi lưu.'),
    });
}

function toggleAudit(logId: number): void {
    expandedAuditId.value = expandedAuditId.value === logId ? null : logId;
}
</script>

<template>
    <Head title="Phân quyền thao tác" />

    <div class="mx-auto w-full max-w-[1600px] space-y-5 p-4 lg:p-7">
        <header
            class="relative overflow-hidden rounded-[1.75rem] border border-indigo-200/80 bg-gradient-to-r from-indigo-50/70 via-slate-50 to-indigo-100/40 text-slate-900 shadow-sm dark:border-indigo-500/20 dark:bg-slate-950 dark:text-white dark:shadow-xl"
        >
            <div
                class="absolute -top-36 -right-16 size-96 rounded-full bg-indigo-500/20 blur-3xl"
            />
            <div
                class="absolute -bottom-36 left-1/3 size-80 rounded-full bg-sky-500/10 blur-3xl"
            />
            <div
                class="relative flex flex-col gap-6 px-5 py-6 lg:flex-row lg:items-end lg:justify-between lg:px-7"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center rounded-2xl border border-indigo-200 bg-indigo-500/10 text-indigo-600 dark:border-indigo-300/20 dark:bg-indigo-500/20 dark:text-indigo-200 dark:shadow-lg dark:shadow-indigo-900/30"
                    >
                        <ShieldCheck class="size-7" />
                    </div>
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <Badge
                                class="border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200"
                                ><span
                                    class="mr-1.5 size-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"
                                />
                                Đang áp dụng</Badge
                            ><Badge
                                class="border-indigo-300/30 bg-indigo-500/10 text-indigo-700 dark:border-indigo-300/20 dark:bg-indigo-400/10 dark:text-indigo-100"
                                >Điểm kiểm soát {{ policyScore }}/100</Badge
                            >
                        </div>
                        <h1
                            class="text-2xl font-black tracking-tight text-slate-900 lg:text-3xl dark:text-white"
                        >
                            Phân quyền & Giới hạn thao tác
                        </h1>
                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            Điều hành hạn mức giảm giá, hủy đơn, dữ liệu nhạy
                            cảm và dấu vết kiểm toán theo vai trò.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="hasChanges"
                        class="mr-1 flex items-center gap-1.5 text-xs font-semibold text-amber-200"
                        ><CircleAlert class="size-4" /> Chưa lưu thay đổi</span
                    ><Button
                        v-if="hasChanges"
                        type="button"
                        variant="ghost"
                        class="gap-2 text-slate-200 hover:bg-white/10 hover:text-white"
                        @click="resetForm"
                        ><RotateCcw class="size-4" /> Khôi phục</Button
                    ><Button
                        type="button"
                        class="gap-2 bg-indigo-500 text-white shadow-lg shadow-indigo-950/30 hover:bg-indigo-400"
                        :disabled="form.processing"
                        @click="submit"
                        ><Save class="size-4" />
                        {{
                            form.processing ? 'Đang lưu...' : 'Lưu chính sách'
                        }}</Button
                    >
                </div>
            </div>
            <div
                class="relative flex flex-wrap gap-x-6 gap-y-2 border-t border-white/[0.08] px-5 py-3 text-xs text-slate-400 lg:px-7"
            >
                <span class="flex items-center gap-1.5"
                    ><History class="size-3.5" /> Cập nhật gần nhất:
                    {{ formatDateTime(policy.updated_at) }}</span
                ><span class="flex items-center gap-1.5"
                    ><FileSearch2 class="size-3.5" />
                    {{ props.recentAudit.length }} sự kiện nhạy cảm gần
                    đây</span
                >
            </div>
        </header>

        <div
            v-if="riskItems.length"
            class="flex flex-col gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/[0.08] px-4 py-3 text-sm lg:flex-row lg:items-center dark:bg-amber-500/[0.06]"
        >
            <div
                class="flex shrink-0 items-center gap-2 font-bold text-amber-700 dark:text-amber-300"
            >
                <AlertTriangle class="size-4" /> {{ riskItems.length }} điểm cần
                lưu ý
            </div>
            <div class="flex flex-1 flex-wrap gap-2">
                <span
                    v-for="item in riskItems.slice(0, 3)"
                    :key="item.title"
                    class="rounded-lg border border-amber-500/15 bg-white/60 px-2.5 py-1 text-xs text-amber-800 dark:bg-slate-950/30 dark:text-amber-200"
                    >{{ item.title }}</span
                >
            </div>
            <button
                type="button"
                class="flex items-center gap-1 text-xs font-bold text-amber-700 hover:underline dark:text-amber-300"
                @click="activeTab = 'policy'"
            >
                Xem cấu hình <ArrowRight class="size-3.5" />
            </button>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-bold tracking-wider text-slate-400 uppercase"
                        >Giảm giá trực tiếp</span
                    ><LockKeyhole class="size-4 text-indigo-500" />
                </div>
                <div
                    class="mt-5 text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                >
                    {{ formatNumber(discountStaff) }}%
                    <span class="text-base text-slate-400">/</span>
                    {{ formatNumber(discountManager) }}%
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Nhân viên / Quản lý trước khi cần phê duyệt
                </p>
            </div>
            <div
                class="rounded-2xl border border-emerald-500/20 bg-emerald-500/[0.04] p-5 shadow-sm dark:bg-emerald-500/[0.03]"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-300"
                        >Hủy đơn trực tiếp</span
                    ><Ban class="size-4 text-emerald-500" />
                </div>
                <div
                    class="mt-5 text-2xl font-black tracking-tight text-emerald-700 dark:text-emerald-300"
                >
                    {{
                        cancelStaff === 0
                            ? 'Chặn staff'
                            : formatMoney(cancelStaff)
                    }}
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Quản lý:
                    {{
                        cancelManager === 0
                            ? 'Không giới hạn'
                            : formatMoney(cancelManager)
                    }}
                </p>
            </div>
            <div
                class="rounded-2xl border border-sky-500/20 bg-sky-500/[0.04] p-5 shadow-sm dark:bg-sky-500/[0.03]"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-bold tracking-wider text-sky-600 uppercase dark:text-sky-300"
                        >Dữ liệu được mở</span
                    ><UsersRound class="size-4 text-sky-500" />
                </div>
                <div
                    class="mt-5 text-2xl font-black tracking-tight text-sky-700 dark:text-sky-300"
                >
                    {{ protectedDataCount }}/4
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Nhóm dữ liệu nhạy cảm đang bật quyền xem
                </p>
            </div>
            <div
                class="rounded-2xl border border-violet-500/20 bg-violet-500/[0.04] p-5 shadow-sm dark:bg-violet-500/[0.03]"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[11px] font-bold tracking-wider text-violet-600 uppercase dark:text-violet-300"
                        >Dấu vết kiểm toán</span
                    ><FileSearch2 class="size-4 text-violet-500" />
                </div>
                <div
                    class="mt-5 text-2xl font-black tracking-tight text-violet-700 dark:text-violet-300"
                >
                    {{ form.audit_all_changes ? 'Đang bật' : 'Đang tắt' }}
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ auditBypassCount }} bypass · {{ auditApprovalCount }} yêu
                    cầu gần đây
                </p>
            </div>
        </section>

        <div
            class="flex flex-wrap items-center gap-1 border-b border-slate-200/80 dark:border-white/[0.08]"
        >
            <button
                v-for="tab in [
                    {
                        key: 'policy',
                        label: 'Cấu hình & Mô phỏng',
                        icon: SlidersHorizontal,
                    },
                    { key: 'audit', label: 'Audit Trail', icon: FileSearch2 },
                ]"
                :key="tab.key"
                type="button"
                class="flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-bold transition"
                :class="
                    activeTab === tab.key
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-300'
                        : 'border-transparent text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                "
                @click="setTab(tab.key)"
            >
                <component :is="tab.icon" class="size-4" /> {{ tab.label
                }}<span
                    v-if="tab.key === 'audit'"
                    class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] dark:bg-white/[0.08]"
                    >{{ props.recentAudit.length }}</span
                >
            </button>
        </div>

        <div
            v-if="activeTab === 'policy'"
            class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]"
        >
            <form class="space-y-5" @submit.prevent="submit">
                <section
                    class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                >
                    <div
                        class="border-b border-slate-200/80 px-5 py-5 dark:border-white/[0.08]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500"
                                >
                                    <LockKeyhole class="size-5" />
                                </div>
                                <div>
                                    <h2
                                        class="font-bold text-slate-900 dark:text-white"
                                    >
                                        Hạn mức giảm giá
                                    </h2>
                                    <p
                                        class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >
                                        Trong hạn mức được phép thực hiện ngay.
                                        Vượt hạn mức sẽ yêu cầu bypass hoặc phê
                                        duyệt của cấp trên.
                                    </p>
                                </div>
                            </div>
                            <Badge
                                class="border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"
                                >Theo % đơn hàng</Badge
                            >
                        </div>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="discount-staff"
                                >Nhân viên tối đa (%)</Label
                            ><Input
                                id="discount-staff"
                                v-model.number="form.max_discount_percent_staff"
                                type="number"
                                min="0"
                                max="100"
                                step="0.1"
                            />
                            <p class="text-[11px] text-slate-500">
                                Hiện tại:
                                {{
                                    formatNumber(
                                        form.max_discount_percent_staff,
                                    )
                                }}%
                            </p>
                            <p
                                v-if="form.errors.max_discount_percent_staff"
                                class="text-xs text-rose-500"
                            >
                                {{ form.errors.max_discount_percent_staff }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="discount-manager"
                                >Quản lý tối đa (%)</Label
                            ><Input
                                id="discount-manager"
                                v-model.number="
                                    form.max_discount_percent_manager
                                "
                                type="number"
                                min="0"
                                max="100"
                                step="0.1"
                            />
                            <p class="text-[11px] text-slate-500">
                                Phải lớn hơn hoặc bằng hạn mức nhân viên.
                            </p>
                            <p
                                v-if="form.errors.max_discount_percent_manager"
                                class="text-xs text-rose-500"
                            >
                                {{ form.errors.max_discount_percent_manager }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                >
                    <div
                        class="border-b border-slate-200/80 px-5 py-5 dark:border-white/[0.08]"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500"
                            >
                                <Ban class="size-5" />
                            </div>
                            <div>
                                <h2
                                    class="font-bold text-slate-900 dark:text-white"
                                >
                                    Hạn mức hủy đơn
                                </h2>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Đơn vượt hạn mức phải có mã bypass của người
                                    có thẩm quyền. Nhân viên đặt 0 nghĩa là
                                    không được hủy trực tiếp; quản lý đặt 0
                                    nghĩa là không giới hạn giá trị.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="cancel-staff"
                                >Nhân viên tối đa (VND)</Label
                            ><Input
                                id="cancel-staff"
                                v-model.number="form.max_cancel_amount_staff"
                                type="number"
                                min="0"
                                step="1000"
                            />
                            <p class="text-[11px] text-slate-500">
                                {{
                                    cancelStaff === 0
                                        ? 'Đang chặn hủy đơn trực tiếp.'
                                        : `Cho phép đến ${formatMoney(cancelStaff)}.`
                                }}
                            </p>
                            <p
                                v-if="form.errors.max_cancel_amount_staff"
                                class="text-xs text-rose-500"
                            >
                                {{ form.errors.max_cancel_amount_staff }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="cancel-manager"
                                >Quản lý tối đa (VND)</Label
                            ><Input
                                id="cancel-manager"
                                v-model.number="form.max_cancel_amount_manager"
                                type="number"
                                min="0"
                                step="1000"
                            />
                            <p class="text-[11px] text-slate-500">
                                {{
                                    cancelManager === 0
                                        ? 'Không giới hạn theo giá trị.'
                                        : `Vượt ${formatMoney(cancelManager)} sẽ cần bypass.`
                                }}
                            </p>
                            <p
                                v-if="form.errors.max_cancel_amount_manager"
                                class="text-xs text-rose-500"
                            >
                                {{ form.errors.max_cancel_amount_manager }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                >
                    <div
                        class="border-b border-slate-200/80 px-5 py-5 dark:border-white/[0.08]"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-500"
                            >
                                <Landmark class="size-5" />
                            </div>
                            <div>
                                <h2
                                    class="font-bold text-slate-900 dark:text-white"
                                >
                                    Ma trận truy cập dữ liệu
                                </h2>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Chỉ mở dữ liệu cần thiết cho vai trò. Chủ
                                    nhà hàng vẫn luôn có quyền xem toàn bộ.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        class="divide-y divide-slate-200/80 dark:divide-white/[0.08]"
                    >
                        <label
                            v-for="row in dataAccessRows"
                            :key="row.key"
                            class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-white/[0.03]"
                            ><span class="flex min-w-0 items-start gap-3"
                                ><span
                                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-white/[0.06] dark:text-slate-300"
                                    ><UserRound class="size-4" /></span
                                ><span class="min-w-0"
                                    ><span
                                        class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100"
                                        >{{ row.label }}
                                        <Badge
                                            variant="outline"
                                            class="text-[10px]"
                                            >{{ row.role }}</Badge
                                        ></span
                                    ><span
                                        class="mt-1 block text-xs text-slate-500 dark:text-slate-400"
                                        >{{ row.description }}</span
                                    ></span
                                ></span
                            ><input
                                v-model="form[row.key]"
                                type="checkbox"
                                class="size-4 shrink-0 rounded border-slate-300 accent-indigo-500"
                        /></label>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                >
                    <div
                        class="border-b border-slate-200/80 px-5 py-5 dark:border-white/[0.08]"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-500"
                            >
                                <Clock3 class="size-5" />
                            </div>
                            <div>
                                <h2
                                    class="font-bold text-slate-900 dark:text-white"
                                >
                                    Thời gian & kiểm toán
                                </h2>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Kiểm soát thời điểm được thao tác và đảm bảo
                                    mọi quyết định nhạy cảm có thể truy ngược.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3 p-5 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200/80 p-4 transition hover:border-violet-400/40 hover:bg-violet-500/[0.03] dark:border-white/[0.08]"
                            ><input
                                v-model="form.restrict_to_shift_hours"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded accent-violet-500"
                            /><span
                                ><span
                                    class="block text-sm font-semibold text-slate-800 dark:text-slate-100"
                                    >Giới hạn theo giờ ca</span
                                ><span
                                    class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >Staff/manager chỉ được thực hiện giảm giá,
                                    hủy đơn trong ca đang hoạt động.</span
                                ></span
                            ></label
                        ><label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200/80 p-4 transition hover:border-violet-400/40 hover:bg-violet-500/[0.03] dark:border-white/[0.08]"
                            ><input
                                v-model="form.audit_all_changes"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded accent-violet-500"
                            /><span
                                ><span
                                    class="block text-sm font-semibold text-slate-800 dark:text-slate-100"
                                    >Bắt buộc audit</span
                                ><span
                                    class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >Ghi lại cập nhật chính sách, bypass và các
                                    yêu cầu thao tác nhạy cảm.</span
                                ></span
                            ></label
                        >
                    </div>
                </section>

                <div
                    class="flex items-center justify-between rounded-2xl border border-indigo-500/15 bg-indigo-500/[0.05] px-5 py-4"
                >
                    <div
                        class="flex items-center gap-3 text-sm text-indigo-900 dark:text-indigo-100"
                    >
                        <Info class="size-4 shrink-0" /><span
                            >Thay đổi chỉ có hiệu lực sau khi bấm
                            <strong>Lưu chính sách</strong>.</span
                        >
                    </div>
                    <Button
                        type="submit"
                        :disabled="form.processing || !hasChanges"
                        class="hidden gap-2 bg-indigo-500 text-white hover:bg-indigo-400 sm:flex"
                        ><Save class="size-4" /> Lưu thay đổi</Button
                    >
                </div>
            </form>

            <aside class="space-y-5 xl:sticky xl:top-5">
                <section
                    class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div
                                class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"
                            >
                                <SlidersHorizontal
                                    class="size-4 text-indigo-500"
                                />
                                Mô phỏng quyết định
                            </div>
                            <p
                                class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                Kiểm tra nhanh hệ thống sẽ xử lý một thao tác
                                theo chính sách hiện tại.
                            </p>
                        </div>
                        <Badge
                            class="border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"
                            >Preview</Badge
                        >
                    </div>
                    <div class="mt-5 space-y-4">
                        <div
                            class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1 dark:bg-white/[0.05]"
                        >
                            <button
                                v-for="mode in [
                                    { key: 'discount', label: 'Giảm giá' },
                                    { key: 'cancel', label: 'Hủy đơn' },
                                ]"
                                :key="mode.key"
                                type="button"
                                class="rounded-lg px-3 py-2 text-xs font-bold transition"
                                :class="
                                    previewMode === mode.key
                                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white'
                                        : 'text-slate-500'
                                "
                                @click="previewMode = mode.key as PreviewMode"
                            >
                                {{ mode.label }}
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="role in [
                                    { key: 'staff', label: 'Nhân viên' },
                                    { key: 'manager', label: 'Quản lý' },
                                ]"
                                :key="role.key"
                                type="button"
                                class="rounded-xl border px-3 py-2 text-xs font-bold transition"
                                :class="
                                    previewRole === role.key
                                        ? 'border-indigo-500/40 bg-indigo-500/10 text-indigo-600 dark:text-indigo-300'
                                        : 'border-slate-200 text-slate-500 dark:border-white/[0.08]'
                                "
                                @click="previewRole = role.key as PreviewRole"
                            >
                                {{ role.label }}
                            </button>
                        </div>
                        <div class="space-y-2">
                            <Label for="preview-value">{{
                                previewMode === 'discount'
                                    ? 'Mức giảm (%)'
                                    : 'Giá trị đơn (VND)'
                            }}</Label
                            ><Input
                                id="preview-value"
                                v-model.number="previewValue"
                                type="number"
                                min="0"
                                step="previewMode === 'discount' ? 0.1 : 1000"
                            />
                        </div>
                        <div
                            class="rounded-xl border p-4"
                            :class="
                                previewResult.allowed
                                    ? 'border-emerald-500/20 bg-emerald-500/[0.06]'
                                    : 'border-amber-500/20 bg-amber-500/[0.06]'
                            "
                        >
                            <div
                                class="flex items-center gap-2 text-sm font-bold"
                                :class="
                                    previewResult.allowed
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-amber-700 dark:text-amber-300'
                                "
                            >
                                <CheckCircle2
                                    v-if="previewResult.allowed"
                                    class="size-4"
                                /><CircleAlert v-else class="size-4" />
                                {{ previewResult.title }}
                            </div>
                            <p
                                class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300"
                            >
                                {{ previewResult.detail }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"
                        >
                            <Shield class="size-4 text-indigo-500" /> Sức khỏe
                            chính sách
                        </div>
                        <span
                            class="text-2xl font-black text-indigo-600 dark:text-indigo-300"
                            >{{ policyScore }}</span
                        >
                    </div>
                    <div
                        class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/[0.08]"
                    >
                        <div
                            class="h-full rounded-full transition-all"
                            :class="
                                policyScore >= 85
                                    ? 'bg-emerald-500'
                                    : policyScore >= 65
                                      ? 'bg-amber-500'
                                      : 'bg-rose-500'
                            "
                            :style="{ width: `${policyScore}%` }"
                        />
                    </div>
                    <p
                        class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        {{ policyScoreLabel }} · Điểm giảm khi audit tắt, ngoài
                        ca được phép hoặc hạn mức mở rộng.
                    </p>
                    <div
                        v-if="riskItems.length"
                        class="mt-4 space-y-2 border-t border-slate-200/80 pt-4 dark:border-white/[0.08]"
                    >
                        <div
                            v-for="item in riskItems"
                            :key="item.title"
                            class="flex items-start gap-2 text-xs"
                        >
                            <AlertTriangle
                                class="mt-0.5 size-3.5 shrink-0"
                                :class="
                                    item.tone === 'danger'
                                        ? 'text-rose-500'
                                        : item.tone === 'warning'
                                          ? 'text-amber-500'
                                          : 'text-sky-500'
                                "
                            /><span
                                class="text-slate-600 dark:text-slate-300"
                                >{{ item.detail }}</span
                            >
                        </div>
                    </div>
                    <div
                        v-else
                        class="mt-4 flex items-center gap-2 border-t border-slate-200/80 pt-4 text-xs font-semibold text-emerald-600 dark:border-white/[0.08] dark:text-emerald-300"
                    >
                        <BadgeCheck class="size-4" /> Không có cảnh báo cấu
                        hình.
                    </div>
                </section>
            </aside>
        </div>

        <section
            v-else
            class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
        >
            <div
                class="flex flex-col gap-4 border-b border-slate-200/80 px-5 py-5 lg:flex-row lg:items-end lg:justify-between dark:border-white/[0.08]"
            >
                <div>
                    <div
                        class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white"
                    >
                        <FileSearch2 class="size-5 text-indigo-500" /> Audit
                        Trail — Nhật ký thao tác nhạy cảm
                    </div>
                    <p
                        class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        Theo dõi ai đã thay đổi chính sách, dùng bypass hoặc gửi
                        yêu cầu vượt quyền.
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        /><Input
                            v-model="auditSearch"
                            class="h-9 w-full pl-9 text-xs sm:w-64"
                            placeholder="Tìm người, hành động, IP..."
                        />
                    </div>
                    <div
                        class="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 dark:bg-white/[0.05]"
                    >
                        <button
                            v-for="filter in [
                                { key: 'all', label: 'Tất cả' },
                                { key: 'policy', label: 'Chính sách' },
                                { key: 'bypass', label: 'Bypass' },
                                { key: 'approval', label: 'Yêu cầu' },
                            ]"
                            :key="filter.key"
                            type="button"
                            class="shrink-0 rounded-md px-2.5 py-1.5 text-[11px] font-bold"
                            :class="
                                auditFilter === filter.key
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white'
                                    : 'text-slate-500'
                            "
                            @click="setAuditFilter(filter.key)"
                        >
                            {{ filter.label }}
                        </button>
                    </div>
                </div>
            </div>
            <div
                v-if="filteredAudit.length"
                class="divide-y divide-slate-200/80 dark:divide-white/[0.08]"
            >
                <div
                    v-for="log in filteredAudit"
                    :key="log.id"
                    class="transition hover:bg-slate-50 dark:hover:bg-white/[0.02]"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-5 py-4 text-left"
                        @click="toggleAudit(log.id)"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                            :class="
                                log.action.includes('bypass')
                                    ? 'bg-rose-500/10 text-rose-500'
                                    : log.action.includes('requested')
                                      ? 'bg-amber-500/10 text-amber-500'
                                      : 'bg-sky-500/10 text-sky-500'
                            "
                            ><Shield class="size-4" /></span
                        ><span class="min-w-0 flex-1"
                            ><span
                                class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100"
                                ><span class="truncate">{{
                                    actionLabel(log.action)
                                }}</span
                                ><Badge
                                    :class="actionTone(log.action)"
                                    class="text-[10px]"
                                    >{{ log.user_role ?? 'system' }}</Badge
                                ></span
                            ><span class="mt-1 block text-xs text-slate-500"
                                >{{ log.user_name }} ·
                                {{ formatDateTime(log.created_at) }} · IP
                                {{ log.ip_address ?? '—' }}</span
                            ></span
                        ><ChevronUp
                            v-if="expandedAuditId === log.id"
                            class="size-4 shrink-0 text-slate-400"
                        /><ChevronDown
                            v-else
                            class="size-4 shrink-0 text-slate-400"
                        />
                    </button>
                    <div
                        v-if="expandedAuditId === log.id"
                        class="grid gap-3 border-t border-slate-200/80 bg-slate-50/70 px-5 py-4 lg:grid-cols-2 dark:border-white/[0.08] dark:bg-white/[0.02]"
                    >
                        <div
                            class="rounded-xl border border-rose-500/15 bg-rose-500/[0.04] p-3"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-[11px] font-bold tracking-wider text-rose-600 uppercase dark:text-rose-300"
                            >
                                <X class="size-3.5" /> Trước thay đổi
                            </div>
                            <pre
                                class="max-h-48 overflow-auto text-[11px] leading-5 whitespace-pre-wrap text-slate-600 dark:text-slate-300"
                                >{{ formatAuditValue(log.old_values) }}</pre
                            >
                        </div>
                        <div
                            class="rounded-xl border border-emerald-500/15 bg-emerald-500/[0.04] p-3"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-[11px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-300"
                            >
                                <Check class="size-3.5" /> Sau thay đổi
                            </div>
                            <pre
                                class="max-h-48 overflow-auto text-[11px] leading-5 whitespace-pre-wrap text-slate-600 dark:text-slate-300"
                                >{{ formatAuditValue(log.new_values) }}</pre
                            >
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="flex flex-col items-center justify-center px-5 py-20 text-center"
            >
                <FileSearch2
                    class="size-10 text-slate-300 dark:text-slate-700"
                />
                <h3
                    class="mt-4 text-sm font-bold text-slate-700 dark:text-slate-200"
                >
                    Không tìm thấy nhật ký
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Thử đổi bộ lọc hoặc từ khóa tìm kiếm.
                </p>
            </div>
        </section>
    </div>
</template>
