<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Award, CalendarDays, Gift, Search, Users, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const page = usePage();
const userRoles = computed(() => (page.props.auth as any)?.user?.roles ?? []);
const isOwner = computed(() => userRoles.value.includes('owner') || userRoles.value.includes('super_admin'));

type Bonus = {
    id: number;
    employee_id: number;
    employee_name: string | null;
    employee_code: string | null;
    branch_name: string | null;
    amount: number;
    reason: string;
    awarded_at: string;
    awarded_by_name: string | null;
    status: 'active' | 'cancelled';
};

const props = defineProps<{
    bonuses: Bonus[];
    employees: Array<{
        id: number;
        full_name: string;
        employee_code: string;
        branch_id: number | null;
    }>;
    summary: {
        this_month_amount: number;
        this_month_count: number;
        active_employees: number;
    };
}>();

const showForm = ref(false);
const searchQuery = ref('');
const statusFilter = ref<'all' | 'active' | 'cancelled'>('all');

const form = useForm({
    employee_id: (props.employees[0]?.id ?? '') as number | '',
    amount: '' as number | string,
    reason: '',
    awarded_at: new Date().toISOString().slice(0, 10),
});

const filteredBonuses = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase('vi-VN');

    return props.bonuses.filter((bonus) => {
        const matchesStatus = statusFilter.value === 'all' || bonus.status === statusFilter.value;
        const haystack = `${bonus.employee_name ?? ''} ${bonus.employee_code ?? ''} ${bonus.reason}`.toLocaleLowerCase('vi-VN');

        return matchesStatus && (!query || haystack.includes(query));
    });
});

const vnd = (amount: number) => `${Math.round(amount).toLocaleString('vi-VN')}đ`;

const formatDate = (date: string) => {
    const parsed = new Date(`${date}T00:00:00`);

    return Number.isNaN(parsed.getTime())
        ? date
        : parsed.toLocaleDateString('vi-VN');
};

const statusLabel = (status: Bonus['status']) =>
    status === 'active' ? 'Đang áp dụng' : 'Đã hủy';

const openForm = () => {
    form.employee_id = props.employees[0]?.id ?? '';
    showForm.value = true;
};

const submit = () => {
    form.post('/bonuses', {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset('amount', 'reason');
        },
    });
};
</script>

<template>
    <Head title="Thưởng nhân viên" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <header class="flex flex-col gap-4 border-b border-border/70 pb-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500">
                    <Gift class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Thưởng nhân viên</h1>
                    <p class="text-sm text-muted-foreground">Ghi nhận thành tích và tự động cộng vào kỳ lương tương ứng.</p>
                </div>
            </div>
            <Button class="bg-emerald-600 text-white shadow-sm hover:bg-emerald-700" @click="openForm">
                <Award class="mr-2 size-4" />
                Ghi nhận thưởng
            </Button>
        </header>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>Thưởng trong tháng</span>
                    <Award class="size-4 text-emerald-500" />
                </div>
                <p class="mt-3 text-2xl font-black text-emerald-600">{{ vnd(props.summary.this_month_amount) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ props.summary.this_month_count }} khoản đang áp dụng</p>
            </div>
            <div class="rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-5">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>Nhân viên đang làm việc</span>
                    <Users class="size-4 text-indigo-500" />
                </div>
                <p class="mt-3 text-2xl font-black text-indigo-600">{{ props.summary.active_employees }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Trong phạm vi chi nhánh hiện tại</p>
            </div>
            <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <span>Nguyên tắc tính lương</span>
                    <CalendarDays class="size-4 text-amber-500" />
                </div>
                <p class="mt-3 text-sm font-bold text-amber-700 dark:text-amber-300">Theo ngày thưởng</p>
                <p class="mt-1 text-xs text-muted-foreground">Khoản thưởng sẽ khóa theo kỳ lương sau khi chốt.</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-bold">Lịch sử thưởng</h2>
                    <p class="text-xs text-muted-foreground">Theo dõi các khoản thưởng đã ghi nhận trong phạm vi dữ liệu hiện tại.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative">
                        <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="searchQuery" class="w-full pl-9 sm:w-64" placeholder="Tìm nhân viên, lý do..." />
                    </div>
                    <select v-model="statusFilter" class="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="active">Đang áp dụng</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>
            </div>

            <div v-if="filteredBonuses.length === 0" class="p-12 text-center">
                <Gift class="mx-auto size-10 text-muted-foreground/40" />
                <p class="mt-3 font-semibold">Chưa có khoản thưởng phù hợp</p>
                <p class="mt-1 text-sm text-muted-foreground">Hãy ghi nhận khoản thưởng đầu tiên cho nhân viên.</p>
            </div>

            <div v-else class="divide-y divide-border/70">
                <div v-for="bonus in filteredBonuses" :key="bonus.id" class="flex flex-col gap-4 p-4 transition-colors hover:bg-muted/30 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 font-bold text-emerald-600">
                            {{ (bonus.employee_name ?? '?').slice(0, 1).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold">{{ bonus.employee_name ?? 'Không xác định' }}</p>
                                <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">{{ bonus.employee_code }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[11px]" :class="bonus.status === 'active' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600'">{{ statusLabel(bonus.status) }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm text-muted-foreground">{{ bonus.reason }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ formatDate(bonus.awarded_at) }} · {{ bonus.branch_name ?? 'Toàn chuỗi' }} · Ghi bởi {{ bonus.awarded_by_name ?? 'Hệ thống' }}</p>
                        </div>
                    </div>
                    <div class="shrink-0 text-left sm:text-right">
                        <p class="text-lg font-black text-emerald-600">+{{ vnd(bonus.amount) }}</p>
                        <p class="text-xs text-muted-foreground">Cộng vào bảng lương</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <Teleport to="body">
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" @click.self="showForm = false">
            <div class="w-full max-w-lg rounded-2xl border bg-card p-6 shadow-2xl">
                <div class="mb-5 flex items-start justify-between border-b border-border/70 pb-4">
                    <div>
                        <div class="flex items-center gap-2 text-emerald-600">
                            <Award class="size-5" />
                            <h2 class="font-bold">{{ isOwner ? 'Ghi nhận khoản thưởng' : 'Đề xuất khoản thưởng nhân viên' }}</h2>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ isOwner ? 'Khoản thưởng sẽ tự động liên kết với kỳ lương theo ngày thưởng.' : 'Đề xuất thưởng sẽ được gửi tới Chủ doanh nghiệp phê duyệt trước khi cộng vào bảng lương.' }}
                        </p>
                    </div>
                    <button type="button" class="rounded-md p-1 text-muted-foreground hover:bg-muted" @click="showForm = false">
                        <X class="size-4" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label>Nhân viên</Label>
                        <select v-model="form.employee_id" required class="h-10 rounded-md border bg-background px-3 text-sm">
                            <option v-for="employee in props.employees" :key="employee.id" :value="employee.id">{{ employee.full_name }} ({{ employee.employee_code }})</option>
                        </select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Số tiền thưởng</Label>
                            <Input v-model="form.amount" type="number" min="1" step="1000" placeholder="500000" required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Ngày thưởng</Label>
                            <Input v-model="form.awarded_at" type="date" required />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Lý do</Label>
                        <textarea v-model="form.reason" rows="4" required class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Hoàn thành tốt công việc, đạt KPI..." />
                    </div>
                    <div class="flex justify-end gap-2 border-t border-border/70 pt-4">
                        <Button type="button" variant="outline" @click="showForm = false">Hủy</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-emerald-600 text-white hover:bg-emerald-700">
                            {{ isOwner ? 'Lưu khoản thưởng' : 'Gửi đề xuất phê duyệt' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
