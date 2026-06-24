<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle,
    AlertTriangle,
    XCircle,
    Info,
    RefreshCw,
    Server,
    ShieldAlert
} from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import GuestLayout from '@/layouts/GuestLayout.vue';

// Configure to render under the public GuestLayout
defineOptions({ layout: GuestLayout });

interface DailyHistory {
    date: string;
    display_date: string;
    uptime_percentage: number;
    average_latency: number;
    status: 'operational' | 'partial_outage' | 'major_outage' | 'no_data';
}

interface ServiceStats {
    service_key: string;
    name: string;
    overall_uptime: number;
    daily_history: DailyHistory[];
    is_maintenance: boolean;
    maintenance_message: string | null;
}

const overallStatus = ref<'operational' | 'outage' | 'maintenance' | 'loading'>('loading');
const servicesStats = ref<ServiceStats[]>([]);
const currentStatuses = ref<Record<string, any>>({});
const isLoading = ref(true);

// Tooltip State
const hoveredDay = ref<DailyHistory | null>(null);
const hoveredServiceKey = ref<string | null>(null);
const tooltipX = ref(0);
const tooltipY = ref(0);

async function loadStatusData() {
    isLoading.value = true;

    try {
        const res = await fetch('/api/status-data');
        const data = await res.json();
        
        overallStatus.value = data.overall_status;
        servicesStats.value = Object.values(data.uptime_stats);
        currentStatuses.value = data.current_statuses;
    } catch (e) {
        console.error('Lỗi khi tải dữ liệu trạng thái:', e);
        overallStatus.value = 'outage';
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    loadStatusData();
});

function showTooltip(event: MouseEvent, day: DailyHistory, serviceKey: string) {
    hoveredDay.value = day;
    hoveredServiceKey.value = serviceKey;
    
    // Position tooltip above the hovered bar element
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();
    
    tooltipX.value = rect.left + window.scrollX + rect.width / 2;
    tooltipY.value = rect.top + window.scrollY - 10;
}

function hideTooltip() {
    hoveredDay.value = null;
    hoveredServiceKey.value = null;
}

function getDayColor(status: string): string {
    switch (status) {
        case 'operational':
            return 'bg-emerald-500 hover:bg-emerald-400';
        case 'partial_outage':
            return 'bg-amber-500 hover:bg-amber-400';
        case 'major_outage':
            return 'bg-rose-500 hover:bg-rose-400';
        case 'no_data':
        default:
            return 'bg-zinc-800 hover:bg-zinc-700';
    }
}

function getStatusLabel(status: string): string {
    switch (status) {
        case 'operational': return 'Hoạt động';
        case 'partial_outage': return 'Gián đoạn nhẹ';
        case 'major_outage': return 'Sự cố nghiêm trọng';
        case 'no_data': return 'Không có dữ liệu';
        default: return 'Bình thường';
    }
}
</script>

<template>
    <Head title="Trạng thái hệ thống" />

    <div class="min-h-[70vh] bg-[#030712] text-zinc-100 py-12 px-4 sm:px-6 lg:px-8 selection:bg-indigo-500/30 selection:text-indigo-200">
        <div class="max-w-4xl mx-auto space-y-8 relative">
            <div class="absolute top-0 right-0 pointer-events-none w-96 h-96 bg-[radial-gradient(circle_at_center,rgba(79,70,229,0.05)_0%,transparent_60%)]"></div>

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center p-2 rounded-xl bg-indigo-600/10 text-indigo-400 border border-indigo-500/20">
                            <ShieldAlert class="size-5" />
                        </span>
                        <h1 class="text-2xl font-bold tracking-tight bg-gradient-to-r from-zinc-50 via-zinc-200 to-zinc-400 bg-clip-text text-transparent">
                            Trạng thái Hệ thống Aventura
                        </h1>
                    </div>
                    <p class="text-xs text-zinc-400">
                        Uptime và lịch sử ổn định của các phân hệ &amp; microservices trong 30 ngày qua.
                    </p>
                </div>
                
                <Button 
                    variant="ghost" 
                    size="sm" 
                    @click="loadStatusData" 
                    :disabled="isLoading" 
                    class="text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900 border border-zinc-800 rounded-lg gap-1.5 h-9"
                >
                    <RefreshCw :class="['size-3.5', { 'animate-spin': isLoading }]" />
                    Làm mới
                </Button>
            </div>

            <!-- 1. Overall System Status Banner -->
            <div v-if="isLoading">
                <Skeleton class="h-28 w-full rounded-2xl bg-zinc-900" />
            </div>
            
            <div v-else class="rounded-2xl border p-6 backdrop-blur-md transition-all shadow-xl"
                :class="{
                    'border-emerald-500/20 bg-emerald-500/[0.03]': overallStatus === 'operational',
                    'border-amber-500/20 bg-amber-500/[0.03]': overallStatus === 'maintenance',
                    'border-rose-500/20 bg-rose-500/[0.03]': overallStatus === 'outage'
                }"
            >
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-full shrink-0"
                        :class="{
                            'bg-emerald-500/10 text-emerald-400 border border-emerald-500/25': overallStatus === 'operational',
                            'bg-amber-500/10 text-amber-500 border border-amber-500/25': overallStatus === 'maintenance',
                            'bg-rose-500/10 text-rose-500 border border-rose-500/25': overallStatus === 'outage'
                        }"
                    >
                        <CheckCircle v-if="overallStatus === 'operational'" class="size-8" />
                        <AlertTriangle v-if="overallStatus === 'maintenance'" class="size-8" />
                        <XCircle v-if="overallStatus === 'outage'" class="size-8" />
                    </div>
                    
                    <div class="space-y-1">
                        <h2 class="text-lg font-bold">
                            <span v-if="overallStatus === 'operational'">Tất Cả Hệ Thống Hoạt Động Bình Thường</span>
                            <span v-if="overallStatus === 'maintenance'">Đang Tiến Hành Bảo Trì Hệ Thống</span>
                            <span v-if="overallStatus === 'outage'">Một Số Dịch Vụ Đang Gặp Sự Cố Gián Đoạn</span>
                        </h2>
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            <span v-if="overallStatus === 'operational'">Chúng tôi duy trì theo dõi liên tục 24/7 để đảm bảo độ tin cậy tốt nhất cho nhà hàng của bạn.</span>
                            <span v-if="overallStatus === 'maintenance'">Chúng tôi đang nâng cấp một số phân hệ vệ tinh. Hệ thống Laravel cốt lõi vẫn hoạt động ổn định.</span>
                            <span v-if="overallStatus === 'outage'">Kỹ thuật viên đã nhận diện sự cố và đang tích cực xử lý để sớm đưa các dịch vụ trở lại trực tuyến.</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. Services Uptime List -->
            <div class="space-y-6">
                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500">Độ ổn định từng dịch vụ</h3>
                
                <div v-if="isLoading" class="space-y-4">
                    <Skeleton v-for="n in 4" :key="n" class="h-24 w-full rounded-2xl bg-zinc-900" />
                </div>
                
                <div v-else class="space-y-4">
                    <Card 
                        v-for="service in servicesStats" 
                        :key="service.service_key"
                        class="bg-zinc-950/40 border-zinc-900 backdrop-blur-md"
                    >
                        <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-6">
                            
                            <!-- Left: Service details & overall uptime -->
                            <div class="space-y-1 shrink-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-sm text-zinc-200">{{ service.name }}</h4>
                                    
                                    <!-- Current status dot -->
                                    <span v-if="service.is_maintenance" class="size-2 rounded-full bg-amber-500 animate-pulse" title="Bảo trì"></span>
                                    <span v-else-if="currentStatuses[service.service_key]?.last_status === 'online'" class="size-2 rounded-full bg-emerald-500 animate-pulse" title="Online"></span>
                                    <span v-else class="size-2 rounded-full bg-rose-500 animate-pulse" title="Offline"></span>
                                </div>
                                <div class="text-[10px] text-zinc-500 font-mono flex items-center gap-1">
                                    <Server class="size-3" />
                                    <span>Cổng {{ currentStatuses[service.service_key]?.port ?? 80 }}</span>
                                </div>
                            </div>
                            
                            <!-- Middle: Timeline Bar Chart (30 Days) -->
                            <div class="flex-1 space-y-1.5">
                                <div class="flex items-center gap-[3px] h-8">
                                    <div 
                                        v-for="day in service.daily_history" 
                                        :key="day.date"
                                        :class="['h-6 flex-1 rounded-sm cursor-pointer transition-all hover:scale-y-115', getDayColor(day.status)]"
                                        @mouseenter="(e) => showTooltip(e, day, service.service_key)"
                                        @mouseleave="hideTooltip"
                                    ></div>
                                </div>
                                
                                <div class="flex justify-between items-center text-[10px] text-zinc-500 font-semibold px-0.5">
                                    <span>30 ngày trước</span>
                                    <span class="w-16 h-[1px] bg-zinc-800 flex-1 mx-3"></span>
                                    <span>Hôm nay</span>
                                </div>
                            </div>

                            <!-- Right: Uptime metric percentage -->
                            <div class="text-right shrink-0">
                                <div class="text-xs text-zinc-500 font-bold uppercase tracking-wider">Uptime 30 ngày</div>
                                <div class="text-base font-black text-indigo-400 font-mono mt-0.5">
                                    {{ service.overall_uptime }}%
                                </div>
                            </div>

                        </div>
                    </Card>
                </div>
            </div>

            <!-- 3. Active Maintenance / Outage Announcements -->
            <div v-if="!isLoading && servicesStats.some(s => s.is_maintenance && s.maintenance_message)">
                <div class="space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500">Thông tin bảo trì &amp; vận hành</h3>
                    
                    <div class="space-y-3">
                        <div 
                            v-for="service in servicesStats.filter(s => s.is_maintenance && s.maintenance_message)" 
                            :key="service.service_key"
                            class="bg-amber-500/5 border border-amber-500/10 rounded-2xl p-4 flex gap-3"
                        >
                            <Info class="size-5 text-amber-500 shrink-0 mt-0.5" />
                            <div class="space-y-0.5">
                                <h5 class="text-xs font-bold text-amber-500 uppercase tracking-wider">
                                    Bảo trì: {{ service.name }}
                                </h5>
                                <p class="text-xs text-zinc-300 leading-relaxed italic">
                                    "{{ service.maintenance_message }}"
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom floating Hover Tooltip -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 translate-y-1 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-1 scale-95"
            >
                <div 
                    v-if="hoveredDay"
                    class="absolute z-50 bg-slate-950 border border-zinc-800 rounded-lg px-3 py-2 shadow-xl pointer-events-none text-xs w-48 space-y-1"
                    :style="{
                        left: `${tooltipX - 96}px`, 
                        top: `${tooltipY - 76}px`
                    }"
                >
                    <div class="font-bold text-zinc-300 flex items-center justify-between">
                        <span>{{ hoveredDay.display_date }}</span>
                        <span class="text-[10px] text-zinc-500 font-mono">Uptime</span>
                    </div>
                    <div class="border-t border-zinc-900 my-1"></div>
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Trạng thái:</span>
                        <span class="font-semibold" :class="{
                            'text-emerald-400': hoveredDay.status === 'operational',
                            'text-amber-400': hoveredDay.status === 'partial_outage',
                            'text-rose-500': hoveredDay.status === 'major_outage',
                            'text-zinc-500': hoveredDay.status === 'no_data',
                        }">
                            {{ getStatusLabel(hoveredDay.status) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-zinc-400">
                        <span>Độ trễ trung bình:</span>
                        <span class="font-mono text-zinc-300 font-semibold">
                            {{ hoveredDay.status === 'no_data' ? '—' : hoveredDay.average_latency + 'ms' }}
                        </span>
                    </div>
                </div>
            </Transition>

        </div>
    </div>
</template>
