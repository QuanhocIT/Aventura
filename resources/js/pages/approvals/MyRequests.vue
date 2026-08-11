<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ClipboardList,
    Clock,
    ShieldAlert,
    ShieldX,
} from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type RequestStatus = 'pending' | 'escalated' | 'approved' | 'rejected';

type MyRequest = {
    id: number;
    operation_type: string;
    operation_label: string;
    status: RequestStatus;
    branch_name: string | null;
    amount_involved: number | null;
    reviewer_name: string | null;
    reviewer_role: string | null;
    rejection_reason: string | null;
    escalation_reason: string | null;
    reviewed_at: string | null;
    created_at: string;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    requests: Paginated<MyRequest>;
    statusFilter: string;
    stats: {
        pending: number;
        escalated: number;
        approved: number;
        rejected: number;
    };
}>();

const currency = new Intl.NumberFormat('vi-VN');

const statusConfig: Record<
    RequestStatus,
    { label: string; badge: string; dot: string }
> = {
    pending: {
        label: 'Chờ duyệt',
        badge: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/40',
        dot: 'bg-amber-500 animate-pulse',
    },
    escalated: {
        label: 'Đã chuyển Chủ',
        badge: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/30 dark:text-violet-400 dark:border-violet-900/40',
        dot: 'bg-violet-500 animate-pulse',
    },
    approved: {
        label: 'Đã phê duyệt',
        badge: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/40',
        dot: 'bg-emerald-500',
    },
    rejected: {
        label: 'Bị từ chối',
        badge: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/40',
        dot: 'bg-rose-500',
    },
};

const roleLabels: Record<string, string> = {
    owner: 'Chủ doanh nghiệp',
    manager: 'Quản lý chi nhánh',
    super_admin: 'Quản trị hệ thống',
};

function reviewerLine(request: MyRequest): string | null {
    if (!request.reviewer_name) {
        return null;
    }

    const role = request.reviewer_role
        ? (roleLabels[request.reviewer_role] ?? request.reviewer_role)
        : null;

    return role ? `${role} · ${request.reviewer_name}` : request.reviewer_name;
}

function applyFilter(value: string) {
    router.get(
        '/my-requests',
        { status: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const filters = [
    { value: 'all', label: 'Tất cả', count: null as number | null },
    {
        value: 'open',
        label: 'Đang chờ',
        count: props.stats.pending + props.stats.escalated,
    },
    { value: 'approved', label: 'Đã duyệt', count: props.stats.approved },
    { value: 'rejected', label: 'Từ chối', count: props.stats.rejected },
];
</script>

<template>
    <Head title="Yêu cầu của tôi" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
        <div class="flex flex-col gap-1">
            <h1
                class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
            >
                Yêu cầu của tôi
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Trạng thái các yêu cầu bạn đã gửi và ai là người quyết định.
            </p>
        </div>

        <!-- Tổng quan -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                v-for="tile in [
                    {
                        label: 'Đang chờ',
                        value: stats.pending + stats.escalated,
                        icon: Clock,
                        cls: 'text-amber-600 dark:text-amber-400',
                    },
                    {
                        label: 'Đã duyệt',
                        value: stats.approved,
                        icon: CheckCircle2,
                        cls: 'text-emerald-600 dark:text-emerald-400',
                    },
                    {
                        label: 'Từ chối',
                        value: stats.rejected,
                        icon: ShieldX,
                        cls: 'text-rose-600 dark:text-rose-400',
                    },
                    {
                        label: 'Chuyển Chủ',
                        value: stats.escalated,
                        icon: ShieldAlert,
                        cls: 'text-violet-600 dark:text-violet-400',
                    },
                ]"
                :key="tile.label"
                class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"
            >
                <component :is="tile.icon" :class="['h-4 w-4', tile.cls]" />
                <div>
                    <div
                        class="text-lg leading-none font-bold text-slate-900 tabular-nums dark:text-slate-100"
                    >
                        {{ tile.value }}
                    </div>
                    <div
                        class="mt-1 text-[10px] font-semibold tracking-wide text-slate-400 uppercase"
                    >
                        {{ tile.label }}
                    </div>
                </div>
            </div>
        </div>

        <Card>
            <CardHeader
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle class="text-base">Lịch sử yêu cầu</CardTitle>
                    <CardDescription
                        >Tổng cộng {{ requests.total }} yêu
                        cầu.</CardDescription
                    >
                </div>

                <div
                    class="flex shrink-0 items-center gap-1 self-start rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <button
                        v-for="f in filters"
                        :key="f.value"
                        type="button"
                        @click="applyFilter(f.value)"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[10px] font-bold whitespace-nowrap transition-colors',
                            statusFilter === f.value
                                ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400',
                        ]"
                    >
                        {{ f.label }}
                        <span
                            v-if="f.count !== null"
                            class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-slate-200/70 px-1 text-[9px] font-black tabular-nums dark:bg-slate-700"
                            >{{ f.count }}</span
                        >
                    </button>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div
                    v-if="requests.data.length === 0"
                    class="flex flex-col items-center justify-center gap-3 py-16 text-slate-400"
                >
                    <ClipboardList class="h-8 w-8" />
                    <p class="text-sm font-medium">Bạn chưa gửi yêu cầu nào.</p>
                </div>

                <ul
                    v-else
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <li
                        v-for="req in requests.data"
                        :key="req.id"
                        class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                                >
                                    {{ req.operation_label }}
                                </span>
                                <span
                                    v-if="req.amount_involved"
                                    class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 tabular-nums dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ currency.format(req.amount_involved) }}đ
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs text-slate-400 dark:text-slate-500"
                            >
                                Gửi lúc {{ req.created_at }}
                                <template v-if="req.branch_name">
                                    · {{ req.branch_name }}
                                </template>
                            </p>

                            <!-- Ai đã quyết định -->
                            <p
                                v-if="reviewerLine(req)"
                                class="mt-1.5 text-xs font-medium text-slate-600 dark:text-slate-300"
                            >
                                {{
                                    req.status === 'approved'
                                        ? 'Phê duyệt bởi'
                                        : 'Xử lý bởi'
                                }}
                                {{ reviewerLine(req) }}
                                <template v-if="req.reviewed_at">
                                    · {{ req.reviewed_at }}
                                </template>
                            </p>

                            <p
                                v-if="
                                    req.status === 'rejected' &&
                                    req.rejection_reason
                                "
                                class="mt-1.5 rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"
                            >
                                Lý do: {{ req.rejection_reason }}
                            </p>

                            <p
                                v-if="
                                    req.status === 'escalated' &&
                                    req.escalation_reason
                                "
                                class="mt-1.5 rounded-lg bg-violet-50 px-2.5 py-1.5 text-xs text-violet-700 dark:bg-violet-950/30 dark:text-violet-300"
                            >
                                {{ req.escalation_reason }}
                            </p>
                        </div>

                        <span
                            :class="[
                                'inline-flex shrink-0 items-center gap-1.5 self-start rounded-full border px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase',
                                statusConfig[req.status].badge,
                            ]"
                        >
                            <span
                                :class="[
                                    'h-1.5 w-1.5 rounded-full',
                                    statusConfig[req.status].dot,
                                ]"
                            />
                            {{ statusConfig[req.status].label }}
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- Phân trang -->
        <div
            v-if="requests.links.length > 3"
            class="flex flex-wrap items-center justify-center gap-1"
        >
            <button
                v-for="link in requests.links"
                :key="link.label"
                type="button"
                :disabled="!link.url"
                @click="link.url && router.visit(link.url)"
                :class="[
                    'rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                    link.active
                        ? 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900'
                        : link.url
                          ? 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                          : 'cursor-not-allowed text-slate-300 dark:text-slate-700',
                ]"
                v-html="link.label"
            />
        </div>
    </div>
</template>
