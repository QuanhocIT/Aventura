<script setup lang="ts">
import { ref } from 'vue';
import { Play, Zap, BarChart3, Users, ChefHat } from 'lucide-vue-next';

const activeTab = ref<'order' | 'kitchen' | 'report' | 'staff'>('order');

const tabs = [
    { key: 'order' as const, label: 'QR Order', icon: Zap },
    { key: 'kitchen' as const, label: 'Màn hình bếp', icon: ChefHat },
    { key: 'report' as const, label: 'Báo cáo AI', icon: BarChart3 },
    { key: 'staff' as const, label: 'Nhân sự', icon: Users },
];

const demoSteps: Record<
    string,
    { title: string; steps: string[]; stat: string; statLabel: string }
> = {
    order: {
        title: 'Khách quét QR → order trong 30 giây',
        steps: [
            'Khách quét mã QR tại bàn',
            'Chọn món từ menu đẹp, đa ngôn ngữ',
            'Xác nhận đơn – không cần gọi nhân viên',
            'Order tự động đẩy về màn hình bếp',
        ],
        stat: '73%',
        statLabel: 'giảm thời gian order',
    },
    kitchen: {
        title: 'Bếp nhận order realtime – không sai món',
        steps: [
            'Màn hình KDS hiển thị đơn theo thứ tự ưu tiên',
            'Phân loại tự động: khai vị → món chính → tráng miệng',
            'Alert khi đơn chờ quá 8 phút',
            'Đánh dấu hoàn thành – server nhận thông báo tức thì',
        ],
        stat: '91%',
        statLabel: 'giảm tỷ lệ sai món',
    },
    report: {
        title: 'AI phân tích – biết ngay điểm yếu cần cải thiện',
        steps: [
            'Dashboard realtime doanh thu từng giờ',
            'AI nhận diện món bán chậm, giờ cao điểm',
            'Dự báo doanh thu tuần tới dựa trên lịch sử',
            'Xuất báo cáo PDF 1 click',
        ],
        stat: '2.4×',
        statLabel: 'tốc độ ra quyết định',
    },
    staff: {
        title: 'Quản lý ca làm – lương tự động mỗi tháng',
        steps: [
            'Lập lịch ca kéo thả trực quan',
            'Chấm công bằng QR hoặc mã PIN',
            'Tính lương tự động theo ca, thưởng, KPI',
            'Nhân viên xem bảng lương qua app',
        ],
        stat: '5h',
        statLabel: 'tiết kiệm/tuần cho quản lý',
    },
};
</script>

<template>
    <section
        id="video-demo"
        class="relative overflow-hidden bg-background py-20 lg:py-28"
    >
        <!-- Decorative blobs using CSS variables -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute top-0 left-1/4 h-96 w-96 rounded-full bg-primary/5 blur-3xl"
            />
            <div
                class="absolute right-1/4 bottom-0 h-96 w-96 rounded-full bg-secondary/20 blur-3xl"
            />
        </div>

        <div class="relative mx-auto max-w-7xl px-4 lg:px-8">
            <!-- Header -->
            <div class="reveal-on-scroll mb-12 text-center">
                <span
                    class="mb-4 inline-block rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-semibold tracking-widest text-primary uppercase"
                >
                    Demo thực tế
                </span>
                <h2 class="text-3xl font-bold text-foreground sm:text-4xl">
                    Xem Aventura <span class="text-primary">hoạt động</span>
                </h2>
                <p class="mx-auto mt-3 max-w-xl text-muted-foreground">
                    Từ QR order đến báo cáo AI — mọi thứ diễn ra liền mạch trong
                    cùng một hệ thống.
                </p>
            </div>

            <!-- Tab selector -->
            <div
                class="reveal-on-scroll mb-8 flex flex-wrap justify-center gap-2"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition-all duration-300"
                    :class="
                        activeTab === tab.key
                            ? 'border-primary/40 bg-primary/10 text-primary shadow-sm'
                            : 'border-border bg-card text-muted-foreground hover:border-primary/20 hover:text-foreground'
                    "
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                </button>
            </div>

            <!-- Demo card -->
            <div
                class="reveal-on-scroll grid items-center gap-8 lg:grid-cols-2 lg:gap-12"
            >
                <!-- Mockup screen -->
                <div class="relative order-2 lg:order-1">
                    <div
                        class="relative rounded-2xl border border-border bg-card p-1 shadow-xl"
                    >
                        <!-- Fake window chrome -->
                        <div
                            class="flex items-center gap-1.5 border-b border-border px-3 py-2"
                        >
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-destructive/70"
                            />
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-yellow-400/70"
                            />
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-green-500/70"
                            />
                            <span
                                class="ml-4 flex-1 rounded bg-muted px-3 py-0.5 font-mono text-[10px] text-muted-foreground"
                            >
                                aventura.app/{{ activeTab }}
                            </span>
                        </div>

                        <!-- Animated content area -->
                        <div class="min-h-[280px] p-5">
                            <Transition name="fade-tab" mode="out-in">
                                <!-- QR Order mockup -->
                                <div
                                    v-if="activeTab === 'order'"
                                    key="order"
                                    class="space-y-3"
                                >
                                    <div
                                        class="flex items-center justify-between rounded-xl border border-primary/20 bg-primary/10 p-3"
                                    >
                                        <div>
                                            <p
                                                class="text-xs font-semibold text-primary"
                                            >
                                                📱 Bàn 05 – Quét QR
                                            </p>
                                            <p
                                                class="mt-0.5 font-bold text-foreground"
                                            >
                                                Phở bò đặc biệt × 2
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                + Trà đào × 3 &nbsp;·&nbsp;
                                                168.000đ
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full bg-green-500/20 px-2.5 py-1 text-xs font-semibold text-green-600 dark:text-green-400"
                                            >Mới</span
                                        >
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div
                                            v-for="item in [
                                                'Bún bò Huế',
                                                'Cơm tấm sườn',
                                                'Bánh xèo',
                                                'Gỏi cuốn',
                                            ]"
                                            :key="item"
                                            class="flex items-center gap-2 rounded-lg border border-border bg-muted p-2.5"
                                        >
                                            <span
                                                class="h-8 w-8 flex-shrink-0 rounded-md bg-muted-foreground/10"
                                            />
                                            <div>
                                                <p
                                                    class="text-xs font-medium text-foreground"
                                                >
                                                    {{ item }}
                                                </p>
                                                <p
                                                    class="text-[10px] text-muted-foreground"
                                                >
                                                    85.000đ
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kitchen screen -->
                                <div
                                    v-else-if="activeTab === 'kitchen'"
                                    key="kitchen"
                                    class="space-y-2.5"
                                >
                                    <div
                                        v-for="(ticket, i) in [
                                            {
                                                table: 'Bàn 03',
                                                items: 'Phở bò × 2, Chả giò × 1',
                                                wait: '4 phút',
                                                urgent: false,
                                            },
                                            {
                                                table: 'Bàn 07',
                                                items: 'Bún riêu × 1, Rau muống × 2',
                                                wait: '9 phút',
                                                urgent: true,
                                            },
                                            {
                                                table: 'Bàn 11',
                                                items: 'Cơm tấm sườn × 3',
                                                wait: '2 phút',
                                                urgent: false,
                                            },
                                        ]"
                                        :key="i"
                                        class="flex items-center justify-between rounded-xl border p-3"
                                        :class="
                                            ticket.urgent
                                                ? 'animate-pulse-subtle border-destructive/40 bg-destructive/10'
                                                : 'border-border bg-muted/40'
                                        "
                                    >
                                        <div>
                                            <p
                                                class="text-xs font-bold"
                                                :class="
                                                    ticket.urgent
                                                        ? 'text-destructive'
                                                        : 'text-primary'
                                                "
                                            >
                                                🍳 {{ ticket.table }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs text-muted-foreground"
                                            >
                                                {{ ticket.items }}
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                            :class="
                                                ticket.urgent
                                                    ? 'bg-destructive/20 text-destructive'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                        >
                                            {{ ticket.wait }}
                                        </span>
                                    </div>
                                </div>

                                <!-- AI Report -->
                                <div
                                    v-else-if="activeTab === 'report'"
                                    key="report"
                                    class="space-y-3"
                                >
                                    <div class="grid grid-cols-3 gap-2">
                                        <div
                                            v-for="stat in [
                                                {
                                                    label: 'Doanh thu',
                                                    val: '12.4M',
                                                    trend: '+18%',
                                                },
                                                {
                                                    label: 'Đơn hàng',
                                                    val: '234',
                                                    trend: '+7%',
                                                },
                                                {
                                                    label: 'Avg. bill',
                                                    val: '185K',
                                                    trend: '+11%',
                                                },
                                            ]"
                                            :key="stat.label"
                                            class="rounded-xl border border-border bg-muted p-3 text-center"
                                        >
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ stat.label }}
                                            </p>
                                            <p
                                                class="text-lg font-bold text-foreground"
                                            >
                                                {{ stat.val }}
                                            </p>
                                            <p
                                                class="text-xs font-semibold text-green-600 dark:text-green-400"
                                            >
                                                {{ stat.trend }}
                                            </p>
                                        </div>
                                    </div>
                                    <!-- Fake bar chart -->
                                    <div
                                        class="rounded-xl border border-border bg-muted p-3"
                                    >
                                        <p
                                            class="mb-2 text-xs text-muted-foreground"
                                        >
                                            Doanh thu 7 ngày gần nhất
                                        </p>
                                        <div
                                            class="flex h-16 items-end gap-1.5"
                                        >
                                            <div
                                                v-for="(h, i) in [
                                                    40, 65, 45, 80, 55, 90, 70,
                                                ]"
                                                :key="i"
                                                class="flex-1 rounded-t-sm bg-gradient-to-t from-primary to-primary/60 transition-all duration-700"
                                                :style="`height: ${h}%`"
                                            />
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-xl border border-accent-foreground/10 bg-accent p-3"
                                    >
                                        <p
                                            class="text-xs font-semibold text-accent-foreground"
                                        >
                                            🤖 AI gợi ý:
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            "Thứ 3 – 4 doanh thu thấp hơn 32%.
                                            Thêm khuyến mãi lunch set sẽ cải
                                            thiện ngay."
                                        </p>
                                    </div>
                                </div>

                                <!-- Staff -->
                                <div
                                    v-else-if="activeTab === 'staff'"
                                    key="staff"
                                    class="space-y-2.5"
                                >
                                    <div
                                        class="rounded-xl border border-border bg-muted p-3"
                                    >
                                        <p
                                            class="mb-2.5 text-xs text-muted-foreground"
                                        >
                                            Lịch ca tuần này
                                        </p>
                                        <div class="space-y-2">
                                            <div
                                                v-for="staff in [
                                                    {
                                                        name: 'Nguyễn Anh Tú',
                                                        shift: 'Ca sáng 6:00–14:00',
                                                        status: 'on',
                                                    },
                                                    {
                                                        name: 'Trần Thị Mai',
                                                        shift: 'Ca chiều 14:00–22:00',
                                                        status: 'on',
                                                    },
                                                    {
                                                        name: 'Lê Minh Hoàng',
                                                        shift: 'Nghỉ hôm nay',
                                                        status: 'off',
                                                    },
                                                ]"
                                                :key="staff.name"
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/20 text-xs font-bold text-primary"
                                                >
                                                    {{ staff.name[0] }}
                                                </span>
                                                <div class="min-w-0 flex-1">
                                                    <p
                                                        class="truncate text-xs font-medium text-foreground"
                                                    >
                                                        {{ staff.name }}
                                                    </p>
                                                    <p
                                                        class="text-[10px] text-muted-foreground"
                                                    >
                                                        {{ staff.shift }}
                                                    </p>
                                                </div>
                                                <span
                                                    class="h-2 w-2 shrink-0 rounded-full"
                                                    :class="
                                                        staff.status === 'on'
                                                            ? 'bg-green-500'
                                                            : 'bg-muted-foreground/40'
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div
                                            class="rounded-xl border border-border bg-muted p-3"
                                        >
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                Lương tháng này
                                            </p>
                                            <p
                                                class="text-lg font-bold text-foreground"
                                            >
                                                42.8M
                                            </p>
                                            <p class="text-xs text-primary">
                                                8 nhân viên
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-xl border border-green-500/20 bg-green-500/10 p-3"
                                        >
                                            <p
                                                class="text-xs text-green-600 dark:text-green-400"
                                            >
                                                Chấm công
                                            </p>
                                            <p
                                                class="text-lg font-bold text-foreground"
                                            >
                                                96.2%
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                đúng giờ
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Play badge overlay -->
                    <div
                        class="absolute -right-4 -bottom-4 z-10 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-primary shadow-lg ring-4 shadow-primary/30 ring-background transition-transform hover:scale-110"
                    >
                        <Play
                            class="ml-0.5 h-5 w-5 fill-primary-foreground text-primary-foreground"
                        />
                    </div>
                </div>

                <!-- Steps -->
                <div class="order-1 space-y-6 lg:order-2">
                    <Transition name="fade-tab" mode="out-in">
                        <div :key="activeTab">
                            <h3 class="mb-6 text-xl font-bold text-foreground">
                                {{ demoSteps[activeTab].title }}
                            </h3>
                            <ol class="space-y-4">
                                <li
                                    v-for="(step, i) in demoSteps[activeTab]
                                        .steps"
                                    :key="i"
                                    class="flex items-start gap-4"
                                >
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-xs font-bold text-primary"
                                    >
                                        {{ i + 1 }}
                                    </span>
                                    <span
                                        class="pt-0.5 text-muted-foreground"
                                        >{{ step }}</span
                                    >
                                </li>
                            </ol>

                            <!-- Stat callout -->
                            <div
                                class="mt-8 flex items-center gap-5 rounded-2xl border border-border bg-accent p-5"
                            >
                                <div class="shrink-0 text-center">
                                    <p class="text-3xl font-black text-primary">
                                        {{ demoSteps[activeTab].stat }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ demoSteps[activeTab].statLabel }}
                                    </p>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    Được ghi nhận từ dữ liệu thực tế của các nhà
                                    hàng đang dùng Aventura.
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.fade-tab-enter-active,
.fade-tab-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}
.fade-tab-enter-from {
    opacity: 0;
    transform: translateY(8px);
}
.fade-tab-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@keyframes pulse-subtle {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.75;
    }
}
.animate-pulse-subtle {
    animation: pulse-subtle 2.5s ease-in-out infinite;
}
</style>
