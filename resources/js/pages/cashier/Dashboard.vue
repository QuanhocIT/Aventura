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
    User
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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
    currentTime.value = now.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    currentDate.value = now.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
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
    props.tablesData?.forEach(t => {
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
    const available = list.filter(t => t.status === 'available').length;
    const occupied = list.filter(t => t.status === 'occupied').length;
    const reserved = list.filter(t => t.status === 'reserved').length;
    const cleaning = list.filter(t => t.status === 'cleaning').length;
    return { total, available, occupied, reserved, cleaning };
});

// Filter logic
const filteredTables = computed(() => {
    let list = props.tablesData ?? [];

    if (selectedArea.value !== 'all') {
        list = list.filter(t => t.area === selectedArea.value);
    }

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        list = list.filter(t => 
            t.name.toLowerCase().includes(query) || 
            (t.area && t.area.toLowerCase().includes(query))
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
                cardBorder: 'hover:border-emerald-500/50'
            };
        case 'occupied':
            return {
                label: 'Có khách',
                class: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400 border-indigo-200/50',
                dotClass: 'bg-indigo-500',
                cardBorder: 'hover:border-indigo-500/50'
            };
        case 'reserved':
            return {
                label: 'Đã đặt trước',
                class: 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-400 border-violet-200/50',
                dotClass: 'bg-violet-500',
                cardBorder: 'hover:border-violet-500/50'
            };
        case 'cleaning':
            return {
                label: 'Đang dọn',
                class: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border-amber-200/50',
                dotClass: 'bg-amber-500',
                cardBorder: 'hover:border-amber-500/50'
            };
    }
};
</script>

<template>
    <Head title="Sơ đồ bàn phục vụ" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full min-h-[calc(100vh-100px)]">
        

        <!-- ── Dynamic Statistics Bar ──────────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <Card class="bg-card hover:bg-muted/10 transition-colors border border-border shadow-sm p-4.5 rounded-2xl flex items-center gap-3.5 relative overflow-hidden">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <Layers class="size-5" />
                </div>
                <div class="text-left">
                    <p class="text-xs text-muted-foreground font-medium">Tổng số bàn</p>
                    <h3 class="text-xl font-black text-foreground mt-0.5 font-mono">{{ stats.total }}</h3>
                </div>
            </Card>

            <Card class="bg-card hover:bg-muted/10 transition-colors border border-border shadow-sm p-4.5 rounded-2xl flex items-center gap-3.5 relative overflow-hidden">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100/50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 class="size-5 animate-pulse" />
                </div>
                <div class="text-left">
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Bàn trống</p>
                    <h3 class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-0.5 font-mono">{{ stats.available }}</h3>
                </div>
            </Card>

            <Card class="bg-card hover:bg-muted/10 transition-colors border border-border shadow-sm p-4.5 rounded-2xl flex items-center gap-3.5 relative overflow-hidden">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100/50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400">
                    <Utensils class="size-5" />
                </div>
                <div class="text-left">
                    <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Có khách</p>
                    <h3 class="text-xl font-black text-indigo-600 dark:text-indigo-400 mt-0.5 font-mono">{{ stats.occupied }}</h3>
                </div>
            </Card>

            <Card class="bg-card hover:bg-muted/10 transition-colors border border-border shadow-sm p-4.5 rounded-2xl flex items-center gap-3.5 relative overflow-hidden">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100/50 dark:bg-violet-950/20 text-violet-600 dark:text-violet-400">
                    <Calendar class="size-5" />
                </div>
                <div class="text-left">
                    <p class="text-xs text-violet-600 dark:text-violet-400 font-medium">Đặt trước</p>
                    <h3 class="text-xl font-black text-violet-600 dark:text-violet-400 mt-0.5 font-mono">{{ stats.reserved }}</h3>
                </div>
            </Card>

            <Card class="bg-card hover:bg-muted/10 transition-colors border border-border shadow-sm p-4.5 rounded-2xl flex items-center gap-3.5 relative overflow-hidden col-span-2 sm:col-span-1">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100/50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400">
                    <Sparkles class="size-5" />
                </div>
                <div class="text-left">
                    <p class="text-xs text-amber-600 dark:text-amber-400 font-medium">Đang dọn</p>
                    <h3 class="text-xl font-black text-amber-600 dark:text-amber-400 mt-0.5 font-mono">{{ stats.cleaning }}</h3>
                </div>
            </Card>
        </div>

        <!-- ── Filter & Search Section ──────────────────────────────── -->
        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between border-b pb-4 border-slate-200/50 dark:border-slate-800/50">
            <!-- Left: Filter Buttons / Area tabs -->
            <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto">
                <Button
                    size="sm"
                    variant="outline"
                    @click="selectedArea = 'all'"
                    class="rounded-xl px-4 py-2 font-medium text-xs shadow-sm transition-all"
                    :class="selectedArea === 'all'
                        ? 'bg-indigo-600 border-indigo-600 text-white hover:bg-indigo-700'
                        : 'bg-card text-muted-foreground hover:text-foreground hover:bg-muted'"
                >
                    Tất cả khu vực
                </Button>
                
                <Button
                    v-for="area in uniqueAreas"
                    :key="area"
                    size="sm"
                    variant="outline"
                    @click="selectedArea = area"
                    class="rounded-xl px-4 py-2 font-medium text-xs shadow-sm transition-all"
                    :class="selectedArea === area
                        ? 'bg-indigo-600 border-indigo-600 text-white hover:bg-indigo-700'
                        : 'bg-card text-muted-foreground hover:text-foreground hover:bg-muted'"
                >
                    {{ area }}
                </Button>
            </div>

            <!-- Right: Search Bar -->
            <div class="relative w-full sm:w-80">
                <Search class="absolute left-3 top-2.5 size-4 text-slate-400" />
                <Input
                    v-model="searchQuery"
                    placeholder="Tìm nhanh bàn phục vụ..."
                    class="pl-9 h-9.5 rounded-xl text-xs bg-card border-border shadow-sm focus-visible:ring-1 focus-visible:ring-indigo-500"
                />
            </div>
        </div>

        <!-- ── Clean & Premium Tables Grid ────────────────────────── -->
        <div v-if="filteredTables.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            <Card
                v-for="table in filteredTables"
                :key="table.id"
                class="bg-card text-card-foreground border border-border shadow-sm hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 relative overflow-hidden flex flex-col justify-between group rounded-2xl"
                :class="getTableStatusInfo(table.status).cardBorder"
            >
                <div class="p-4 flex-1 flex flex-col gap-3">
                    <!-- Card Top: Area and Status Indicator -->
                    <div class="flex justify-between items-center gap-2">
                        <span class="text-[10px] font-semibold text-slate-400 tracking-wide uppercase truncate max-w-[70%]">
                            {{ table.area }}
                        </span>
                        
                        <!-- Glowing status pulse -->
                        <span class="flex h-2.5 w-2.5 relative">
                            <span :class="['animate-ping absolute inline-flex h-full w-full rounded-full opacity-75', getTableStatusInfo(table.status).dotClass]"></span>
                            <span :class="['relative inline-flex rounded-full h-2.5 w-2.5', getTableStatusInfo(table.status).dotClass]"></span>
                        </span>
                    </div>

                    <!-- Card Middle: Table Name -->
                    <div class="flex flex-col gap-1 text-left mt-1">
                        <h4 class="text-base font-black text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors flex items-center gap-1.5">
                            <Utensils class="size-4 shrink-0 text-slate-400" />
                            {{ table.name }}
                        </h4>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="px-4 py-3 bg-slate-50/50 dark:bg-slate-900/50 border-t border-border/40 flex items-center justify-between gap-2 rounded-b-2xl">
                    <span class="flex items-center gap-1 text-[10px] text-muted-foreground font-semibold">
                        <Users class="size-3" />
                        {{ table.capacity }} ghế
                    </span>
                    
                    <Badge
                        variant="outline"
                        class="text-[9px] font-bold px-2 py-0.5 rounded-full border shadow-sm"
                        :class="getTableStatusInfo(table.status).class"
                    >
                        {{ getTableStatusInfo(table.status).label }}
                    </Badge>
                </div>
            </Card>
        </div>

        <!-- ── No Tables Found State ─────────────────────────────── -->
        <div v-else class="flex flex-col items-center justify-center border-2 border-dashed border-border/80 bg-slate-50/20 rounded-3xl p-16 text-center max-w-lg mx-auto w-full mt-10">
            <Utensils class="size-12 text-slate-300 dark:text-slate-700 animate-bounce mb-4" />
            <h4 class="text-md font-bold text-slate-800 dark:text-slate-100">Không tìm thấy bàn nào!</h4>
            <p class="text-xs text-muted-foreground mt-1 px-4 leading-relaxed">
                {{ searchQuery.trim() || selectedArea !== 'all' ? 'Vui lòng kiểm tra lại từ khóa tìm kiếm hoặc đổi bộ lọc khu vực khác.' : 'Chưa có sơ đồ bàn nào được cấu hình trong hệ thống.' }}
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
