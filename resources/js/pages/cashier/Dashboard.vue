<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Utensils,
    Users,
    Clock,
    Search,
    Layers,
    Coffee,
    Sparkles,
    CheckCircle2,
    Calendar,
    User,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface TableItem {
    id: number;
    name: string;
    area: string;
    capacity: number;
    status: 'available' | 'occupied' | 'reserved' | 'cleaning';
}

const props = defineProps<{
    tablesData?: TableItem[];
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user ?? null);
const restaurant = computed(() => (page.props as any).tenant ?? null);

// State for Filters
const searchQuery = ref('');
const selectedArea = ref('all');

// Live Clock
const currentTime = ref('');
const currentDate = ref('');
let timerId: any = null;

const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    currentDate.value = now.toLocaleDateString('vi-VN', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

onMounted(() => {
    updateTime();
    timerId = setInterval(updateTime, 1000);
});

onUnmounted(() => {
    if (timerId) {
        clearInterval(timerId);
    }
});

// Dynamic areas computation
const uniqueAreas = computed(() => {
    const areas = new Set<string>();
    props.tablesData?.forEach((t) => {
        if (t.area) {
            areas.add(t.area);
        }
    });

    return Array.from(areas);
});

// Statistics
const stats = computed(() => {
    const list = props.tablesData ?? [];
    const total = list.length;
    const available = list.filter((t) => t.status === 'available').length;
    const occupied = list.filter((t) => t.status === 'occupied').length;
    const reserved = list.filter((t) => t.status === 'reserved').length;
    const cleaning = list.filter((t) => t.status === 'cleaning').length;

    return { total, available, occupied, reserved, cleaning };
});

// Filter logic
const filteredTables = computed(() => {
    let list = props.tablesData ?? [];

    if (selectedArea.value !== 'all') {
        list = list.filter((t) => t.area === selectedArea.value);
    }

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        list = list.filter(
            (t) =>
                t.name.toLowerCase().includes(query) ||
                (t.area && t.area.toLowerCase().includes(query)),
        );
    }

    return list;
});

// Helper for status classes and labels
const getTableStatusInfo = (status: TableItem['status']) => {
    switch (status) {
        case 'available':
            return {
                label: 'Bàn trống',
                class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border-emerald-200/50',
                dotClass: 'bg-emerald-500 animate-pulse',
                cardBorder: 'hover:border-emerald-500/50',
            };
        case 'occupied':
            return {
                label: 'Có khách',
                class: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400 border-indigo-200/50',
                dotClass: 'bg-indigo-500',
                cardBorder: 'hover:border-indigo-500/50',
            };
        case 'reserved':
            return {
                label: 'Đã đặt trước',
                class: 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-400 border-violet-200/50',
                dotClass: 'bg-violet-500',
                cardBorder: 'hover:border-violet-500/50',
            };
        case 'cleaning':
            return {
                label: 'Đang dọn',
                class: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border-amber-200/50',
                dotClass: 'bg-amber-500',
                cardBorder: 'hover:border-amber-500/50',
            };
    }
};
</script>

<template>
    <Head title="Sơ đồ bàn phục vụ" />

    <div
        class="mx-auto flex min-h-[calc(100vh-100px)] w-full max-w-7xl flex-col gap-6 p-6"
    >
        <!-- ── Dynamic Statistics Bar ──────────────────────────────── -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <Card
                class="relative flex items-center gap-3.5 overflow-hidden rounded-2xl border border-border bg-card p-4.5 shadow-sm transition-colors hover:bg-muted/10"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                >
                    <Layers class="size-5" />
                </div>
                <div class="text-left">
                    <p class="text-xs font-medium text-muted-foreground">
                        Tổng số bàn
                    </p>
                    <h3
                        class="mt-0.5 font-mono text-xl font-black text-foreground"
                    >
                        {{ stats.total }}
                    </h3>
                </div>
            </Card>

            <Card
                class="relative flex items-center gap-3.5 overflow-hidden rounded-2xl border border-border bg-card p-4.5 shadow-sm transition-colors hover:bg-muted/10"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100/50 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400"
                >
                    <CheckCircle2 class="size-5 animate-pulse" />
                </div>
                <div class="text-left">
                    <p
                        class="text-xs font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        Bàn trống
                    </p>
                    <h3
                        class="mt-0.5 font-mono text-xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{ stats.available }}
                    </h3>
                </div>
            </Card>

            <Card
                class="relative flex items-center gap-3.5 overflow-hidden rounded-2xl border border-border bg-card p-4.5 shadow-sm transition-colors hover:bg-muted/10"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100/50 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400"
                >
                    <Utensils class="size-5" />
                </div>
                <div class="text-left">
                    <p
                        class="text-xs font-medium text-indigo-600 dark:text-indigo-400"
                    >
                        Có khách
                    </p>
                    <h3
                        class="mt-0.5 font-mono text-xl font-black text-indigo-600 dark:text-indigo-400"
                    >
                        {{ stats.occupied }}
                    </h3>
                </div>
            </Card>

            <Card
                class="relative flex items-center gap-3.5 overflow-hidden rounded-2xl border border-border bg-card p-4.5 shadow-sm transition-colors hover:bg-muted/10"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100/50 text-violet-600 dark:bg-violet-950/20 dark:text-violet-400"
                >
                    <Calendar class="size-5" />
                </div>
                <div class="text-left">
                    <p
                        class="text-xs font-medium text-violet-600 dark:text-violet-400"
                    >
                        Đặt trước
                    </p>
                    <h3
                        class="mt-0.5 font-mono text-xl font-black text-violet-600 dark:text-violet-400"
                    >
                        {{ stats.reserved }}
                    </h3>
                </div>
            </Card>

            <Card
                class="relative col-span-2 flex items-center gap-3.5 overflow-hidden rounded-2xl border border-border bg-card p-4.5 shadow-sm transition-colors hover:bg-muted/10 sm:col-span-1"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100/50 text-amber-600 dark:bg-amber-950/20 dark:text-amber-400"
                >
                    <Sparkles class="size-5" />
                </div>
                <div class="text-left">
                    <p
                        class="text-xs font-medium text-amber-600 dark:text-amber-400"
                    >
                        Đang dọn
                    </p>
                    <h3
                        class="mt-0.5 font-mono text-xl font-black text-amber-600 dark:text-amber-400"
                    >
                        {{ stats.cleaning }}
                    </h3>
                </div>
            </Card>
        </div>

        <!-- ── Filter & Search Section ──────────────────────────────── -->
        <div
            class="flex flex-col items-center justify-between gap-4 border-b border-slate-200/50 pb-4 sm:flex-row dark:border-slate-800/50"
        >
            <!-- Left: Filter Buttons / Area tabs -->
            <div class="flex w-full flex-wrap items-center gap-1.5 sm:w-auto">
                <Button
                    size="sm"
                    variant="outline"
                    @click="selectedArea = 'all'"
                    class="rounded-xl px-4 py-2 text-xs font-medium shadow-sm transition-all"
                    :class="
                        selectedArea === 'all'
                            ? 'border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700'
                            : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    Tất cả khu vực
                </Button>

                <Button
                    v-for="area in uniqueAreas"
                    :key="area"
                    size="sm"
                    variant="outline"
                    @click="selectedArea = area"
                    class="rounded-xl px-4 py-2 text-xs font-medium shadow-sm transition-all"
                    :class="
                        selectedArea === area
                            ? 'border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700'
                            : 'bg-card text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    {{ area }}
                </Button>
            </div>

            <!-- Right: Search Bar -->
            <div class="relative w-full sm:w-80">
                <Search class="absolute top-2.5 left-3 size-4 text-slate-400" />
                <Input
                    v-model="searchQuery"
                    placeholder="Tìm nhanh bàn phục vụ..."
                    class="h-9.5 rounded-xl border-border bg-card pl-9 text-xs shadow-sm focus-visible:ring-1 focus-visible:ring-indigo-500"
                />
            </div>
        </div>

        <!-- ── Clean & Premium Tables Grid ────────────────────────── -->
        <div
            v-if="filteredTables.length > 0"
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
        >
            <Card
                v-for="table in filteredTables"
                :key="table.id"
                class="group relative flex transform flex-col justify-between overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                :class="getTableStatusInfo(table.status).cardBorder"
            >
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <!-- Card Top: Area and Status Indicator -->
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="max-w-[70%] truncate text-[10px] font-semibold tracking-wide text-slate-400 uppercase"
                        >
                            {{ table.area }}
                        </span>

                        <!-- Glowing status pulse -->
                        <span class="relative flex h-2.5 w-2.5">
                            <span
                                :class="[
                                    'absolute inline-flex h-full w-full animate-ping rounded-full opacity-75',
                                    getTableStatusInfo(table.status).dotClass,
                                ]"
                            ></span>
                            <span
                                :class="[
                                    'relative inline-flex h-2.5 w-2.5 rounded-full',
                                    getTableStatusInfo(table.status).dotClass,
                                ]"
                            ></span>
                        </span>
                    </div>

                    <!-- Card Middle: Table Name -->
                    <div class="mt-1 flex flex-col gap-1 text-left">
                        <h4
                            class="flex items-center gap-1.5 text-base font-black text-slate-800 transition-colors group-hover:text-indigo-600 dark:text-slate-100 dark:group-hover:text-indigo-400"
                        >
                            <Utensils class="size-4 shrink-0 text-slate-400" />
                            {{ table.name }}
                        </h4>
                    </div>
                </div>

                <!-- Card Footer -->
                <div
                    class="flex items-center justify-between gap-2 rounded-b-2xl border-t border-border/40 bg-slate-50/50 px-4 py-3 dark:bg-slate-900/50"
                >
                    <span
                        class="flex items-center gap-1 text-[10px] font-semibold text-muted-foreground"
                    >
                        <Users class="size-3" />
                        {{ table.capacity }} ghế
                    </span>

                    <Badge
                        variant="outline"
                        class="rounded-full border px-2 py-0.5 text-[9px] font-bold shadow-sm"
                        :class="getTableStatusInfo(table.status).class"
                    >
                        {{ getTableStatusInfo(table.status).label }}
                    </Badge>
                </div>
            </Card>
        </div>

        <!-- ── No Tables Found State ─────────────────────────────── -->
        <div
            v-else
            class="mx-auto mt-10 flex w-full max-w-lg flex-col items-center justify-center rounded-3xl border-2 border-dashed border-border/80 bg-slate-50/20 p-16 text-center"
        >
            <Utensils
                class="mb-4 size-12 animate-bounce text-slate-300 dark:text-slate-700"
            />
            <h4 class="text-md font-bold text-slate-800 dark:text-slate-100">
                Không tìm thấy bàn nào!
            </h4>
            <p class="mt-1 px-4 text-xs leading-relaxed text-muted-foreground">
                {{
                    searchQuery.trim() || selectedArea !== 'all'
                        ? 'Vui lòng kiểm tra lại từ khóa tìm kiếm hoặc đổi bộ lọc khu vực khác.'
                        : 'Chưa có sơ đồ bàn nào được cấu hình trong hệ thống.'
                }}
            </p>
        </div>
    </div>
</template>

<style scoped>
@keyframes spin-slow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
.animate-spin-slow {
    animation: spin-slow 12s linear infinite;
}
</style>
