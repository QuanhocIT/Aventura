<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, FileSearch2, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, FilterBar, StatusBadge, Pagination, EmptyState } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    logs: {
        data: Array<{
            id: number;
            restaurant: string | null;
            user_name: string;
            user_email: string | null;
            user_role: string;
            event: string;
            action: string;
            subject_type: string | null;
            subject_id: number | null;
            ip_address: string | null;
            old_values: Record<string, any> | null;
            new_values: Record<string, any> | null;
            created_at: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    restaurants: Array<{ id: number; name: string }>;
    filters: { restaurant_id?: string; event?: string; action?: string; from?: string; to?: string };
    total: number;
}>();

const restaurantFilter = ref(props.filters.restaurant_id ?? '');
const eventFilter = ref(props.filters.event ?? '');
const actionFilter = ref(props.filters.action ?? '');
const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(actionFilter, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilter, 500);
});

function applyFilter() {
    router.get('/super-admin/audit-logs', {
        restaurant_id: restaurantFilter.value || undefined,
        event: eventFilter.value || undefined,
        action: actionFilter.value || undefined,
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    restaurantFilter.value = '';
    eventFilter.value = '';
    actionFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
    applyFilter();
}

const expandedRow = ref<number | null>(null);
function toggleExpand(id: number) {
    expandedRow.value = expandedRow.value === id ? null : id;
}

const eventLabel: Record<string, string> = {
    created: 'Tạo mới', updated: 'Cập nhật', deleted: 'Xóa',
};

const actionLabel: Record<string, string> = {
    reset_password: 'Reset mật khẩu',
    disable_2fa: 'Tắt 2FA',
    toggle_account_status: 'Đổi trạng thái TK',
    seed_demo_order: 'Seed đơn demo',
};

function formatAction(action: string): string {
    return actionLabel[action] ?? action.replace(/_/g, ' ');
}

const hasActiveFilter = () =>
    restaurantFilter.value || eventFilter.value ||
    actionFilter.value || fromFilter.value || toFilter.value;
</script>

<template>
    <Head title="Audit Log hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Audit Log hệ thống"
            :subtitle="`Nhật ký thao tác cấp hệ thống · Tổng ${total.toLocaleString()} bản ghi`"
            :icon="FileSearch2"
        >
            <template #actions>
                <Link href="/super-admin/accounts">
                    <Button variant="outline" size="sm">Quản lý tài khoản →</Button>
                </Link>
            </template>
        </PageHeader>

        <FilterBar>
            <Select v-model="restaurantFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[180px]">
                    <SelectValue placeholder="Tất cả nhà hàng" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả nhà hàng</SelectItem>
                    <SelectItem value="system">— Hệ thống —</SelectItem>
                    <SelectItem v-for="r in restaurants" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="eventFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Tất cả sự kiện" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả sự kiện</SelectItem>
                    <SelectItem value="created">Tạo mới</SelectItem>
                    <SelectItem value="updated">Cập nhật</SelectItem>
                    <SelectItem value="deleted">Xóa</SelectItem>
                </SelectContent>
            </Select>
            <div class="relative min-w-40 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="actionFilter" placeholder="Tìm hành động..." class="pl-9" />
            </div>
            <Input v-model="fromFilter" type="date" class="w-[150px]" @change="applyFilter" />
            <Input v-model="toFilter" type="date" class="w-[150px]" @change="applyFilter" />
            <template v-if="hasActiveFilter()" #actions>
                <Button variant="ghost" size="sm" @click="resetFilters" class="text-xs text-muted-foreground">Xoá lọc</Button>
            </template>
        </FilterBar>

        <Card class="stagger-2 overflow-hidden">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-border/60 bg-muted/20 backdrop-blur-sm">
                            <tr>
                                <th class="w-8 px-4 py-3" />
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Thời gian</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Nhà hàng</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Người thực hiện</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Sự kiện</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Hành động</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Đối tượng</th>
                                <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted-foreground">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(log, idx) in logs.data" :key="log.id">
                                <tr
                                    class="cursor-pointer border-b border-border/30 transition-all duration-200 hover:-translate-y-px hover:bg-muted/20 hover:shadow-[0_2px_12px_rgba(0,0,0,0.04)] dark:hover:shadow-[0_2px_12px_rgba(0,0,0,0.15)]"
                                    :class="[expandedRow === log.id ? 'bg-muted/20' : '', idx % 2 !== 0 ? 'bg-muted/[0.06]' : '']"
                                    :style="{ animationDelay: `${idx * 30}ms` }"
                                    @click="toggleExpand(log.id)"
                                >
                                    <td class="px-4 py-3 text-muted-foreground">
                                        <ChevronDown v-if="expandedRow === log.id" class="size-4 transition-transform" />
                                        <ChevronRight v-else class="size-4 transition-transform" />
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">{{ log.created_at }}</td>
                                    <td class="px-4 py-3 text-xs">
                                        <span v-if="log.restaurant" class="font-medium">{{ log.restaurant }}</span>
                                        <span v-else class="italic text-muted-foreground">— Hệ thống —</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-xs font-medium">{{ log.user_name }}</p>
                                        <p v-if="log.user_email" class="text-xs text-muted-foreground">{{ log.user_email }}</p>
                                        <span
                                            v-if="log.user_role"
                                            class="inline-block rounded-full bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                        >
                                            {{ log.user_role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <StatusBadge :status="log.event">
                                            {{ eventLabel[log.event] ?? log.event }}
                                        </StatusBadge>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ formatAction(log.action) }}</td>
                                    <td class="px-4 py-3 text-xs text-muted-foreground">
                                        <span v-if="log.subject_type">
                                            {{ log.subject_type }}
                                            <span v-if="log.subject_id" class="font-mono">#{{ log.subject_id }}</span>
                                        </span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ log.ip_address ?? '—' }}</td>
                                </tr>

                                <!-- Expanded row: terminal-style diff -->
                                <tr v-if="expandedRow === log.id" class="border-b border-border/30">
                                    <td colspan="8" class="p-4">
                                        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 p-5 shadow-xl">
                                            <div class="mb-3 flex items-center gap-1.5">
                                                <span class="size-2.5 rounded-full bg-rose-500/90 shadow-[0_0_6px_#f43f5e]" />
                                                <span class="size-2.5 rounded-full bg-amber-500/90 shadow-[0_0_6px_#f59e0b]" />
                                                <span class="size-2.5 rounded-full bg-emerald-500/90 shadow-[0_0_6px_#10b981]" />
                                                <span class="ml-2 font-mono text-[9px] font-extrabold uppercase tracking-wider text-slate-500">diff viewer</span>
                                            </div>
                                            <div class="grid gap-4 md:grid-cols-2">
                                                <div>
                                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-rose-400">Dữ liệu cũ</p>
                                                    <pre
                                                        v-if="log.old_values"
                                                        class="max-h-40 overflow-auto rounded-lg bg-rose-950/30 p-3 font-mono text-xs text-rose-300"
                                                    >{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                                                    <span v-else class="text-xs italic text-slate-500">— Không có —</span>
                                                </div>
                                                <div>
                                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-emerald-400">Dữ liệu mới</p>
                                                    <pre
                                                        v-if="log.new_values"
                                                        class="max-h-40 overflow-auto rounded-lg bg-emerald-950/30 p-3 font-mono text-xs text-emerald-300"
                                                    >{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                                                    <span v-else class="text-xs italic text-slate-500">— Không có —</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!logs.data.length">
                                <td colspan="8">
                                    <EmptyState
                                        :icon="FileSearch2"
                                        title="Không có bản ghi nào phù hợp"
                                        description="Thử thay đổi bộ lọc hoặc mở rộng khoảng thời gian"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="logs.last_page > 1" :links="logs.links" />
            </CardContent>
        </Card>
    </div>
</template>
