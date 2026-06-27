<script setup lang="ts">
import {
    BarChart3,
    Bot,
    Building2,
    Check,
    ChevronRight,
    Monitor,
    Package,
    Play,
    Pause,
    QrCode,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Badge } from '@/components/ui/badge';

const featureMap = [
    {
        step: 1,
        icon: QrCode,
        title: 'QR order',
        description:
            'Khách quét QR tại bàn -> tạo order nhanh, giảm sai sót, giảm tải nhân viên.',
        category: 'sales',
        color: 'indigo',
        status: 'Realtime',
        dbTables: ['orders', 'order_items', 'tables'],
        techNote:
            'Sử dụng WebSockets qua Pusher/Laravel Echo giúp đồng bộ order tức thì từ điện thoại khách tới bếp.',
        flow: {
            trigger: 'Khách quét QR, gọi món trên di động',
            process: 'Laravel Broadcast -> WebSockets Event',
            storage: 'Lưu schema orders & order_items',
        },
        sla: 'Đồng bộ < 150ms',
    },
    {
        step: 2,
        icon: Monitor,
        title: 'Kitchen display',
        description:
            'Đơn lên bếp theo realtime, trạng thái rõ ràng, ít nhầm lẫn.',
        category: 'sales',
        color: 'emerald',
        status: 'Realtime',
        dbTables: ['kds_tickets', 'order_items'],
        techNote:
            'KDS lắng nghe sự kiện đẩy đơn từ POS/QR order để cập nhật trạng thái nấu ăn tức thời.',
        flow: {
            trigger: 'Hệ thống nhận order mới của khách',
            process: 'Pusher Broadcast -> Màn hình bếp KDS',
            storage: 'Đồng bộ trạng thái kds_tickets',
        },
        sla: 'Trực quan realtime',
    },
    {
        step: 3,
        icon: Package,
        title: 'Inventory',
        description:
            'Trừ kho theo định lượng, theo dõi nhập xuất, cảnh báo thiếu nguyên liệu.',
        category: 'mgmt',
        color: 'amber',
        status: 'Auto',
        dbTables: ['ingredients', 'recipes', 'inventory_transactions'],
        techNote:
            'Tự động trừ nguyên vật liệu trong kho dựa trên công thức món ăn (recipe) ngay khi thanh toán hóa đơn.',
        flow: {
            trigger: 'POS hoàn tất thanh toán hóa đơn',
            process: 'Recipe Engine tính toán định lượng món',
            storage: 'Trừ stock, ghi vết inventory_transactions',
        },
        sla: 'Chính xác 100%',
    },
    {
        step: 4,
        icon: Users,
        title: 'Staff',
        description:
            'Quản lý nhân sự, ca làm, vai trò, chấm công và hỗ trợ tính lương.',
        category: 'mgmt',
        color: 'sky',
        status: 'Secure',
        dbTables: ['users', 'roles', 'shifts', 'time_sheets'],
        techNote:
            'Phân quyền chi tiết (Spatie ACL) kết hợp chấm công theo ca thực tế để lập bảng tính lương tự động.',
        flow: {
            trigger: 'Nhân viên check-in ca làm việc',
            process: 'Kiểm tra Spatie Policy & IP chi nhánh',
            storage: 'Ghi nhận bảng chấm công time_sheets',
        },
        sla: 'Bảo mật chặt chẽ',
    },
    {
        step: 5,
        icon: ShieldCheck,
        title: 'Audit log',
        description:
            'Ghi vết thao tác nhạy cảm để tra soát, giảm phụ thuộc vào tính trung thực.',
        category: 'system',
        color: 'teal',
        status: 'Secure',
        dbTables: ['audit_logs'],
        techNote:
            'Middleware tự động chụp lại trạng thái dữ liệu trước/sau khi thay đổi và định danh IP người thực hiện.',
        flow: {
            trigger: 'Thay đổi giá món, xóa đơn hoặc hủy hóa đơn',
            process: 'Audit Trail Middleware ghi nhận payload',
            storage: 'Lưu vết immutable vào bảng audit_logs',
        },
        sla: 'Không thể tẩy xóa',
    },
    {
        step: 6,
        icon: BarChart3,
        title: 'Analytics',
        description:
            'Theo dõi doanh thu, hiệu suất, xu hướng món bán chạy, báo cáo vận hành.',
        category: 'sales',
        color: 'rose',
        status: 'Sync',
        dbTables: ['orders', 'payments'],
        techNote:
            'Các chỉ số tổng hợp được cache bằng Redis, tối ưu hóa database index để tải báo cáo chuỗi siêu tốc.',
        flow: {
            trigger: 'Quản lý mở xem báo cáo doanh thu',
            process: 'Redis Cache Checker -> DB Query Aggregates',
            storage: 'Kết xuất JSON báo cáo hiệu suất',
        },
        sla: 'Truy vấn < 10ms',
    },
    {
        step: 7,
        icon: Building2,
        title: 'Multi-branch',
        description:
            'Một tài khoản quản lý nhiều chi nhánh, dữ liệu tách biệt theo tenant.',
        category: 'mgmt',
        color: 'blue',
        status: 'Tenant Scope',
        dbTables: ['branches', 'tenant_scopes'],
        techNote:
            'Áp dụng Laravel Global Query Scope tự động lọc mọi câu lệnh SQL theo branch_id của tenant.',
        flow: {
            trigger: 'User thực hiện bất kỳ thao tác đọc/ghi',
            process: 'Global Query Scope tự động chèn branch_id',
            storage: 'Cô lập dữ liệu tuyệt đối giữa các Tenant',
        },
        sla: 'Bảo mật dữ liệu',
    },
    {
        step: 8,
        icon: Bot,
        title: 'AI insights',
        description:
            'Tổng hợp tín hiệu dữ liệu để gợi ý cảnh báo, dự báo và phát hiện bất thường.',
        category: 'system',
        color: 'violet',
        status: 'AI Model',
        dbTables: ['ai_predictions', 'stock_trends'],
        techNote:
            'Hồi quy tuyến tính học máy chạy nền để phân tích xu hướng và đưa ra cảnh báo sớm về hao hụt kho.',
        flow: {
            trigger: 'Chạy Cronjob định kỳ hàng đêm',
            process: 'Ingest lịch sử orders & stock -> AI Prediction',
            storage: 'Lưu dự báo xu hướng vào ai_predictions',
        },
        sla: 'Chính xác > 92%',
    },
];

const categories = [
    { key: 'all', label: 'Tất cả' },
    { key: 'sales', label: 'Bán hàng & POS' },
    { key: 'mgmt', label: 'Quản lý & Hậu cần' },
    { key: 'system', label: 'Hệ thống & AI' },
] as const;

const activeCategory = ref<string>('all');
const activeFeatureTitle = ref<string>('QR order');

const filteredFeatureMap = computed(() => {
    if (activeCategory.value === 'all') {
        return featureMap;
    }

    return featureMap.filter((item) => item.category === activeCategory.value);
});

const activeFeature = computed(() => {
    return (
        featureMap.find((f) => f.title === activeFeatureTitle.value) ||
        featureMap[0]
    );
});

const colorGlowBorder = computed(() => {
    switch (activeFeature.value.color) {
        case 'indigo':
            return 'rgba(99, 102, 241, 0.4)';
        case 'emerald':
            return 'rgba(16, 185, 129, 0.4)';
        case 'amber':
            return 'rgba(245, 158, 11, 0.4)';
        case 'sky':
            return 'rgba(14, 165, 233, 0.4)';
        case 'rose':
            return 'rgba(244, 63, 94, 0.4)';
        case 'blue':
            return 'rgba(59, 130, 246, 0.4)';
        case 'teal':
            return 'rgba(20, 184, 166, 0.4)';
        case 'violet':
            return 'rgba(139, 92, 246, 0.4)';
        case 'cyan':
            return 'rgba(6, 182, 212, 0.4)';
        default:
            return 'rgba(255, 255, 255, 0.1)';
    }
});

const goToNextStep = () => {
    const currentIndex = featureMap.findIndex(
        (f) => f.title === activeFeatureTitle.value,
    );
    const nextIndex = (currentIndex + 1) % featureMap.length;
    activeFeatureTitle.value = featureMap[nextIndex].title;

    if (
        activeCategory.value !== 'all' &&
        activeCategory.value !== featureMap[nextIndex].category
    ) {
        activeCategory.value = 'all';
    }
};

const isAutoPlaying = ref(false);
let autoPlayTimer: any = null;

const startAutoPlay = () => {
    isAutoPlaying.value = true;
    autoPlayTimer = setInterval(() => {
        goToNextStep();
    }, 3000);
};

const stopAutoPlay = () => {
    isAutoPlaying.value = false;

    if (autoPlayTimer) {
        clearInterval(autoPlayTimer);
        autoPlayTimer = null;
    }
};

const toggleAutoPlay = () => {
    if (isAutoPlaying.value) {
        stopAutoPlay();
    } else {
        startAutoPlay();
    }
};

onMounted(() => {
    startAutoPlay();
});

onUnmounted(() => {
    stopAutoPlay();
});
</script>

<template>
    <section
        id="features"
        class="relative mt-0 overflow-hidden border-y border-border bg-muted/30 px-4 py-10 lg:py-12 lg:px-8"
    >
        <!-- Decorative subtle background grids or glows -->
        <div
            class="absolute inset-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] bg-[size:14px_24px]"
        ></div>

        <div class="relative z-10 mx-auto max-w-7xl">
            <div
                class="reveal-on-scroll flex flex-col justify-between gap-6 md:flex-row md:items-end"
            >
                <div class="max-w-2xl">
                    <Badge
                        variant="outline"
                        class="mb-3 border-primary/30 bg-primary/5 text-primary"
                    >
                        Kiến Trúc Minh Bạch
                    </Badge>
                    <h2
                        class="text-gradient-vibrant text-3xl font-extrabold tracking-tight sm:text-4xl"
                    >
                        Map tính năng theo DB + vận hành
                    </h2>
                    <p
                        class="mt-3 text-sm leading-relaxed text-muted-foreground sm:text-base"
                    >
                        Không marketing mơ hồ. Đây là các lớp chức năng khớp
                        trực tiếp với schema database và báo cáo kỹ thuật
                        thực tế đã chốt.
                    </p>
                </div>

                <!-- Category Pills Filter -->
                <div
                    class="flex w-fit flex-wrap gap-1.5 rounded-xl border border-border/80 bg-muted p-1"
                >
                    <button
                        v-for="cat in categories"
                        :key="cat.key"
                        @click="activeCategory = cat.key"
                        class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all duration-200"
                        :class="
                            activeCategory === cat.key
                                ? 'bg-background font-bold text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/40 hover:text-foreground'
                        "
                    >
                        {{ cat.label }}
                    </button>
                </div>
            </div>

            <!-- Grid Split: 12 Columns -->
            <div class="mt-10 grid items-start gap-8 lg:grid-cols-12">
                <!-- LEFT COLUMN: Operations & Data Flow Console (5/12 columns) -->
                <div class="reveal-on-scroll space-y-4 lg:col-span-5">
                    <div
                        class="relative flex min-h-[480px] flex-col justify-between overflow-hidden rounded-2xl border bg-zinc-950 p-6 text-zinc-100 shadow-2xl transition-all duration-500"
                        :style="`border-color: ${colorGlowBorder}; box-shadow: 0 10px 30px -10px ${colorGlowBorder}`"
                    >
                        <!-- Color Glow Effects behind the console -->
                        <div
                            class="absolute -top-16 -right-16 h-36 w-36 rounded-full opacity-40 blur-[80px] transition-all duration-500"
                            :class="{
                                'bg-indigo-500':
                                    activeFeature.color === 'indigo',
                                'bg-emerald-500':
                                    activeFeature.color === 'emerald',
                                'bg-amber-500':
                                    activeFeature.color === 'amber',
                                'bg-sky-500': activeFeature.color === 'sky',
                                'bg-rose-500':
                                    activeFeature.color === 'rose',
                                'bg-blue-500':
                                    activeFeature.color === 'blue',
                                'bg-teal-500':
                                    activeFeature.color === 'teal',
                                'bg-violet-500':
                                    activeFeature.color === 'violet',
                                'bg-cyan-500':
                                    activeFeature.color === 'cyan',
                            }"
                        ></div>

                        <!-- Console Header -->
                        <div
                            class="flex items-center justify-between border-b border-zinc-900 pb-4"
                        >
                            <div class="flex items-center gap-2">
                                <div class="flex gap-1.5">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-red-500/80"
                                    ></span>
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-yellow-500/80"
                                    ></span>
                                    <span
                                        class="h-2.5 w-2.5 rounded-full bg-green-500/80"
                                    ></span>
                                </div>
                                <span
                                    class="ml-2 font-mono text-[9px] tracking-wider text-zinc-500"
                                    >DATAFLOW_MONITOR v1.0.0</span
                                >
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button
                                    @click="toggleAutoPlay"
                                    class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-2 py-1 text-[9.5px] font-bold transition-all duration-300 active:scale-95"
                                    :class="
                                        isAutoPlaying
                                            ? 'animate-pulse border-primary bg-primary font-extrabold text-primary-foreground'
                                            : 'border-zinc-800 bg-zinc-900 text-zinc-300 hover:border-zinc-700 hover:bg-zinc-800/80 hover:text-white'
                                    "
                                >
                                    <component
                                        :is="isAutoPlaying ? Pause : Play"
                                        class="size-3"
                                    />
                                    <span>{{
                                        isAutoPlaying
                                            ? 'Dừng phát'
                                            : 'Tự động chạy'
                                    }}</span>
                                </button>
                                <button
                                    @click="goToNextStep"
                                    class="flex cursor-pointer items-center gap-1 rounded-lg border border-zinc-800 bg-zinc-900 px-2 py-1 text-[9.5px] font-bold text-zinc-300 transition-all hover:border-zinc-700 hover:bg-zinc-800/80 hover:text-white active:scale-95"
                                >
                                    <span>Bước tiếp theo</span>
                                    <ChevronRight
                                        class="size-3 animate-pulse text-primary"
                                    />
                                </button>
                                <Badge
                                    class="border px-2 py-0.5 font-mono text-[9px] tracking-wider uppercase"
                                    :class="{
                                        'border-indigo-500/30 bg-indigo-950/20 text-indigo-400':
                                            activeFeature.color ===
                                            'indigo',
                                        'border-emerald-500/30 bg-emerald-950/20 text-emerald-400':
                                            activeFeature.color ===
                                            'emerald',
                                        'border-cyan-500/30 bg-cyan-950/20 text-cyan-400':
                                            activeFeature.color === 'cyan',
                                        'border-amber-500/30 bg-amber-950/20 text-amber-400':
                                            activeFeature.color === 'amber',
                                        'border-sky-500/30 bg-sky-950/20 text-sky-400':
                                            activeFeature.color === 'sky',
                                        'border-teal-500/30 bg-teal-950/20 text-teal-400':
                                            activeFeature.color === 'teal',
                                        'border-rose-500/30 bg-rose-950/20 text-rose-400':
                                            activeFeature.color === 'rose',
                                        'border-blue-500/30 bg-blue-950/20 text-blue-400':
                                            activeFeature.color === 'blue',
                                        'border-violet-500/30 bg-violet-950/20 text-violet-400':
                                            activeFeature.color ===
                                            'violet',
                                    }"
                                >
                                    {{ activeFeature.status }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Selected Feature Overview -->
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="rounded-xl border p-2.5 transition-all duration-300"
                                    :class="{
                                        'border-indigo-500/30 bg-indigo-950/50 text-indigo-400':
                                            activeFeature.color ===
                                            'indigo',
                                        'border-emerald-500/30 bg-emerald-950/50 text-emerald-400':
                                            activeFeature.color ===
                                            'emerald',
                                        'border-amber-500/30 bg-amber-950/50 text-amber-400':
                                            activeFeature.color === 'amber',
                                        'border-sky-500/30 bg-sky-950/50 text-sky-400':
                                            activeFeature.color === 'sky',
                                        'border-rose-500/30 bg-rose-950/50 text-rose-400':
                                            activeFeature.color === 'rose',
                                        'border-blue-500/30 bg-blue-950/50 text-blue-400':
                                            activeFeature.color === 'blue',
                                        'border-teal-500/30 bg-teal-950/50 text-teal-400':
                                            activeFeature.color === 'teal',
                                        'border-violet-500/30 bg-violet-950/50 text-violet-400':
                                            activeFeature.color ===
                                            'violet',
                                        'border-cyan-500/30 bg-cyan-950/50 text-cyan-400':
                                            activeFeature.color === 'cyan',
                                    }"
                                >
                                    <component
                                        :is="activeFeature.icon"
                                        class="size-6 animate-pulse"
                                    />
                                </div>
                                <div>
                                    <h3
                                        class="flex items-center gap-2 text-base font-bold tracking-tight text-white"
                                    >
                                        Bước {{ activeFeature.step }}:
                                        {{ activeFeature.title }}
                                    </h3>
                                    <p
                                        class="mt-0.5 text-xs leading-relaxed text-zinc-400"
                                    >
                                        {{ activeFeature.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Flow Visualization -->
                        <div
                            class="mt-5 flex-grow space-y-3.5 border-y border-zinc-900/60 py-4"
                        >
                            <p
                                class="font-mono text-[9px] tracking-widest text-zinc-500 uppercase"
                            >
                                LUỒNG HOẠT ĐỘNG & ĐỒNG BỘ
                            </p>

                            <!-- Step 1: TRIGGER -->
                            <div class="relative flex items-start gap-3">
                                <div
                                    class="relative z-10 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                                    :class="{
                                        'bg-indigo-400 shadow-[0_0_8px_rgba(99,102,241,0.8)]':
                                            activeFeature.color ===
                                            'indigo',
                                        'bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)]':
                                            activeFeature.color ===
                                            'emerald',
                                        'bg-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.8)]':
                                            activeFeature.color === 'amber',
                                        'bg-sky-400 shadow-[0_0_8px_rgba(14,165,233,0.8)]':
                                            activeFeature.color === 'sky',
                                        'bg-rose-400 shadow-[0_0_8px_rgba(244,63,94,0.8)]':
                                            activeFeature.color === 'rose',
                                        'bg-blue-400 shadow-[0_0_8px_rgba(59,130,246,0.8)]':
                                            activeFeature.color === 'blue',
                                        'bg-teal-400 shadow-[0_0_8px_rgba(20,184,166,0.8)]':
                                            activeFeature.color === 'teal',
                                        'bg-violet-400 shadow-[0_0_8px_rgba(139,92,246,0.8)]':
                                            activeFeature.color ===
                                            'violet',
                                        'bg-cyan-400 shadow-[0_0_8px_rgba(6,182,212,0.8)]':
                                            activeFeature.color === 'cyan',
                                    }"
                                ></div>
                                <div
                                    class="absolute top-[14px] left-[3px] h-[28px] w-[1px] bg-gradient-to-b from-zinc-700 to-zinc-900"
                                ></div>
                                <div class="space-y-0.5">
                                    <span
                                        class="font-mono text-[9px] text-zinc-500 uppercase"
                                        >Trigger (Sự kiện kích hoạt)</span
                                    >
                                    <p
                                        class="text-xs font-semibold text-zinc-200"
                                    >
                                        {{ activeFeature.flow.trigger }}
                                    </p>
                                </div>
                            </div>

                            <!-- Step 2: PROCESS -->
                            <div class="relative flex items-start gap-3">
                                <div
                                    class="relative z-10 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                                    :class="{
                                        'bg-indigo-400 shadow-[0_0_8px_rgba(99,102,241,0.8)]':
                                            activeFeature.color ===
                                            'indigo',
                                        'bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)]':
                                            activeFeature.color ===
                                            'emerald',
                                        'bg-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.8)]':
                                            activeFeature.color === 'amber',
                                        'bg-sky-400 shadow-[0_0_8px_rgba(14,165,233,0.8)]':
                                            activeFeature.color === 'sky',
                                        'bg-rose-400 shadow-[0_0_8px_rgba(244,63,94,0.8)]':
                                            activeFeature.color === 'rose',
                                        'bg-blue-400 shadow-[0_0_8px_rgba(59,130,246,0.8)]':
                                            activeFeature.color === 'blue',
                                        'bg-teal-400 shadow-[0_0_8px_rgba(20,184,166,0.8)]':
                                            activeFeature.color === 'teal',
                                        'bg-violet-400 shadow-[0_0_8px_rgba(139,92,246,0.8)]':
                                            activeFeature.color ===
                                            'violet',
                                        'bg-cyan-400 shadow-[0_0_8px_rgba(6,182,212,0.8)]':
                                            activeFeature.color === 'cyan',
                                    }"
                                ></div>
                                <div
                                    class="absolute top-[14px] left-[3px] h-[28px] w-[1px] bg-gradient-to-b from-zinc-700 to-zinc-900"
                                ></div>
                                <div class="space-y-0.5">
                                    <span
                                        class="font-mono text-[9px] text-zinc-500 uppercase"
                                        >Xử lý (Processing)</span
                                    >
                                    <p
                                        class="text-xs font-semibold text-zinc-200"
                                    >
                                        {{ activeFeature.flow.process }}
                                    </p>
                                </div>
                            </div>

                            <!-- Step 3: STORAGE -->
                            <div class="relative flex items-start gap-3">
                                <div
                                    class="relative z-10 mt-2 h-1.5 w-1.5 flex-shrink-0 animate-pulse rounded-full"
                                    :class="{
                                        'bg-indigo-400 shadow-[0_0_8px_rgba(99,102,241,0.8)]':
                                            activeFeature.color ===
                                            'indigo',
                                        'bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)]':
                                            activeFeature.color ===
                                            'emerald',
                                        'bg-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.8)]':
                                            activeFeature.color === 'amber',
                                        'bg-sky-400 shadow-[0_0_8px_rgba(14,165,233,0.8)]':
                                            activeFeature.color === 'sky',
                                        'bg-rose-400 shadow-[0_0_8px_rgba(244,63,94,0.8)]':
                                            activeFeature.color === 'rose',
                                        'bg-blue-400 shadow-[0_0_8px_rgba(59,130,246,0.8)]':
                                            activeFeature.color === 'blue',
                                        'bg-teal-400 shadow-[0_0_8px_rgba(20,184,166,0.8)]':
                                            activeFeature.color === 'teal',
                                        'bg-violet-400 shadow-[0_0_8px_rgba(139,92,246,0.8)]':
                                            activeFeature.color ===
                                            'violet',
                                        'bg-cyan-400 shadow-[0_0_8px_rgba(6,182,212,0.8)]':
                                            activeFeature.color === 'cyan',
                                    }"
                                ></div>
                                <div class="flex-grow space-y-0.5">
                                    <span
                                        class="font-mono text-[9px] text-zinc-500 uppercase"
                                        >Lưu trữ Database</span
                                    >
                                    <p
                                        class="text-xs font-semibold text-white"
                                    >
                                        {{ activeFeature.flow.storage }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Technology & DB details -->
                        <div class="mt-4 space-y-3 pt-3">
                            <div class="space-y-1">
                                <span
                                    class="font-mono text-[9px] tracking-widest text-zinc-500 uppercase"
                                    >MAPPED SCHEMAS (DATABASE TABLES)</span
                                >
                                <div class="flex flex-wrap gap-1.5">
                                    <code
                                        v-for="tbl in activeFeature.dbTables"
                                        :key="tbl"
                                        class="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 font-mono text-[10px] font-bold text-zinc-300 transition-colors hover:border-zinc-700"
                                    >
                                        {{ tbl }}
                                    </code>
                                </div>
                            </div>

                            <div
                                class="rounded-xl border border-zinc-900/60 bg-zinc-900/30 p-3 text-[11px] leading-relaxed text-zinc-400"
                            >
                                <span class="font-bold text-zinc-200"
                                    >Tech note: </span
                                >{{ activeFeature.techNote }}
                            </div>

                            <div
                                class="flex items-center justify-between border-t border-zinc-900/40 pt-3 font-mono text-[10px] text-zinc-500"
                            >
                                <span
                                    >Mục tiêu SLA:
                                    <strong
                                        class="font-medium text-zinc-300"
                                        >{{ activeFeature.sla }}</strong
                                    ></span
                                >
                                <span
                                    >STATUS:
                                    <strong class="text-green-500"
                                        >ACTIVE</strong
                                    ></span
                                >
                            </div>
                        </div>

                        <!-- Global SaaS Operations Step-by-Step Flow Map -->
                        <div
                            class="relative z-10 mt-4 space-y-2 border-t border-zinc-900/60 pt-3"
                        >
                            <span
                                class="flex items-center gap-1.5 font-mono text-[9px] tracking-widest text-zinc-500 uppercase"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-ping rounded-full bg-primary"
                                ></span>
                                TIẾN TRÌNH LUỒNG DỮ LIỆU SAAS (8 BƯỚC VẬN HÀNH CHÍNH)
                            </span>
                            <div
                                class="flex scrollbar-none items-center justify-between gap-1 overflow-x-auto rounded-xl border border-zinc-900/80 bg-zinc-900/20 p-2"
                            >
                                <div
                                    v-for="(item, idx) in featureMap"
                                    :key="item.title"
                                    class="flex flex-shrink-0 items-center"
                                >
                                    <button
                                        @click="
                                            activeFeatureTitle = item.title;
                                            if (
                                                activeCategory !== 'all' &&
                                                activeCategory !==
                                                    item.category
                                            )
                                                activeCategory = 'all';
                                        "
                                        class="group relative flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border font-mono text-xs transition-all duration-300"
                                        :class="
                                            activeFeatureTitle ===
                                            item.title
                                                ? 'scale-110 border-white bg-white font-extrabold text-zinc-950 shadow-lg'
                                                : 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                                        "
                                        :style="
                                            activeFeatureTitle ===
                                            item.title
                                                ? `box-shadow: 0 0 10px ${colorGlowBorder}`
                                                : ''
                                        "
                                    >
                                        <span>{{ item.step }}</span>
                                        <!-- Tooltip hint -->
                                        <span
                                            class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 rounded border border-zinc-900 bg-zinc-950 px-2 py-1 font-sans text-[9px] whitespace-nowrap text-zinc-200 opacity-0 transition-opacity group-hover:opacity-100"
                                        >
                                            Bước {{ item.step }}:
                                            {{ item.title }}
                                        </span>
                                    </button>
                                    <span
                                        v-if="idx < featureMap.length - 1"
                                        class="mx-0.5 font-mono text-[9px] text-zinc-800"
                                        >➔</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Interactive Grid (7/12 columns) -->
                <div class="lg:col-span-7">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="item in filteredFeatureMap"
                            :key="item.title"
                            @mouseenter="activeFeatureTitle = item.title"
                            @click="activeFeatureTitle = item.title"
                            class="group relative cursor-pointer overflow-hidden rounded-2xl border border-border bg-card p-5 transition-all duration-300 hover:shadow-lg"
                            :class="
                                activeFeatureTitle === item.title
                                    ? 'ring-2 ring-offset-2 ring-offset-background'
                                    : 'hover:border-zinc-300 dark:hover:border-zinc-700'
                            "
                            :style="
                                activeFeatureTitle === item.title
                                    ? `border-color: ${item.color === 'indigo' ? 'rgba(99, 102, 241, 0.5)' : item.color === 'emerald' ? 'rgba(16, 185, 129, 0.5)' : item.color === 'amber' ? 'rgba(245, 158, 11, 0.5)' : item.color === 'sky' ? 'rgba(14, 165, 233, 0.5)' : item.color === 'rose' ? 'rgba(244, 63, 94, 0.5)' : item.color === 'blue' ? 'rgba(59, 130, 246, 0.5)' : item.color === 'teal' ? 'rgba(20, 184, 166, 0.5)' : item.color === 'violet' ? 'rgba(139, 92, 246, 0.5)' : 'rgba(6, 182, 212, 0.5)'}; --tw-ring-color: ${item.color === 'indigo' ? '#6366f1' : item.color === 'emerald' ? '#10b981' : item.color === 'amber' ? '#f59e0b' : item.color === 'sky' ? '#0ea5e9' : item.color === 'rose' ? '#f43f5e' : item.color === 'blue' ? '#3b82f6' : item.color === 'teal' ? '#14b8a6' : item.color === 'violet' ? '#8b5cf6' : '#06b6d4'}`
                                    : ''
                            "
                        >
                            <!-- Interactive Hover Glow matching the icon color -->
                            <div
                                class="absolute -right-10 -bottom-10 h-24 w-24 rounded-full opacity-0 blur-2xl transition-all duration-300 group-hover:opacity-10"
                                :class="{
                                    'bg-indigo-500':
                                        item.color === 'indigo',
                                    'bg-emerald-500':
                                        item.color === 'emerald',
                                    'bg-amber-500': item.color === 'amber',
                                    'bg-sky-500': item.color === 'sky',
                                    'bg-rose-500': item.color === 'rose',
                                    'bg-blue-500': item.color === 'blue',
                                    'bg-teal-500': item.color === 'teal',
                                    'bg-violet-500':
                                        item.color === 'violet',
                                    'bg-cyan-500': item.color === 'cyan',
                                }"
                            ></div>

                            <div
                                class="relative z-10 mb-3 flex items-center justify-between"
                            >
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-xl border transition-all duration-300 group-hover:scale-110"
                                    :class="{
                                        'border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-900/40 dark:bg-indigo-950/20 dark:text-indigo-400':
                                            item.color === 'indigo',
                                        'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-400':
                                            item.color === 'emerald',
                                        'border-cyan-100 bg-cyan-50 text-cyan-600 dark:border-cyan-900/40 dark:bg-cyan-950/20 dark:text-cyan-400':
                                            item.color === 'cyan',
                                        'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400':
                                            item.color === 'amber',
                                        'border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/20 dark:text-sky-400':
                                            item.color === 'sky',
                                        'border-teal-100 bg-teal-50 text-teal-600 dark:border-teal-900/40 dark:bg-teal-950/20 dark:text-teal-400':
                                            item.color === 'teal',
                                        'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-400':
                                            item.color === 'rose',
                                        'border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-900/40 dark:bg-blue-950/20 dark:text-blue-400':
                                            item.color === 'blue',
                                        'border-violet-100 bg-violet-50 text-violet-600 dark:border-violet-900/40 dark:bg-violet-950/20 dark:text-violet-400':
                                            item.color === 'violet',
                                    }"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-5"
                                    />
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <!-- Step Number Indicator -->
                                    <span
                                        class="animate-pulse rounded-md px-1.5 py-0.5 font-mono text-[10px] font-extrabold"
                                        :class="{
                                            'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400':
                                                item.color === 'indigo',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400':
                                                item.color === 'emerald',
                                            'bg-cyan-100 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-400':
                                                item.color === 'cyan',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400':
                                                item.color === 'amber',
                                            'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400':
                                                item.color === 'sky',
                                            'bg-teal-100 text-teal-700 dark:bg-teal-950/40 dark:text-teal-400':
                                                item.color === 'teal',
                                            'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400':
                                                item.color === 'rose',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400':
                                                item.color === 'blue',
                                            'bg-violet-100 text-violet-700 dark:bg-violet-950/40 dark:text-violet-400':
                                                item.color === 'violet',
                                        }"
                                    >
                                        {{
                                            String(item.step).padStart(
                                                2,
                                                '0',
                                            )
                                        }}
                                    </span>
                                    <!-- Inline DB Table hint -->
                                    <span
                                        class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-[9px] text-muted-foreground"
                                    >
                                        {{ item.dbTables[0] }}
                                    </span>
                                    <Badge
                                        variant="outline"
                                        class="px-1.5 py-0 text-[9px] tracking-wider uppercase"
                                    >
                                        {{ item.status }}
                                    </Badge>
                                </div>
                            </div>

                            <div class="relative z-10 space-y-1.5">
                                <h3
                                    class="flex items-center gap-2 text-base font-bold tracking-tight text-card-foreground transition-colors group-hover:text-primary"
                                >
                                    {{ item.title }}
                                    <ChevronRight
                                        class="size-3.5 -translate-x-1 text-primary opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100"
                                    />
                                </h3>
                                <p
                                    class="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                                >
                                    {{ item.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
