<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

const demoTabs = [
    { key: 'pos', label: 'POS' },
    { key: 'kds', label: 'Bếp' },
    { key: 'report', label: 'Báo cáo' },
] as const;

const activeDemo = ref<(typeof demoTabs)[number]['key']>('pos');

const demoState = computed(() => {
    if (activeDemo.value === 'kds') {
        return {
            title: 'Kitchen board',
            left: ['2 order chờ', '1 order đang làm', '0 trễ SLA'],
            right: [
                { label: 'Bún bò', status: 'Đang làm' },
                { label: 'Cơm gà', status: 'Sẵn sàng' },
                { label: 'Trà đào', status: 'Chờ in bill' },
            ],
        };
    }

    if (activeDemo.value === 'report') {
        return {
            title: 'Daily snapshot',
            left: [
                'Doanh thu: 12,8M',
                'Tỷ lệ hoàn thành: 98%',
                'Món bán chạy: Phở bò',
            ],
            right: [
                { label: 'Giờ cao điểm', status: '11:30 - 13:30' },
                { label: 'Cảnh báo kho', status: '2 nguyên liệu thấp' },
                { label: 'Audit', status: '1 thay đổi giá' },
            ],
        };
    }

    return {
        title: 'POS checkout',
        left: ['Bàn 12', '3 món', 'Tổng: 168.000đ'],
        right: [
            { label: 'Phở bò tái', status: 'x2' },
            { label: 'Trà chanh', status: 'x1' },
            { label: 'Thanh toán', status: 'Hoàn tất' },
        ],
    };
});
</script>

<template>
    <!-- Right Column: Interactive Live Demo widget styled in travel-glassmorphism -->
    <div
        class="relative mx-auto w-full max-w-lg rounded-2xl border border-white/10 bg-zinc-950/75 p-6 shadow-2xl backdrop-blur-xl transition-all duration-500 hover:border-amber-500/25 sm:p-8 lg:ml-auto"
    >
        <!-- Header with Live Demo tag -->
        <div
            class="mb-5 flex items-center justify-between border-b border-white/10 pb-4"
        >
            <div>
                <span
                    class="mb-1 block text-xs font-extrabold tracking-widest text-amber-400 uppercase"
                >
                    Live demo
                </span>
                <h2 class="text-2xl font-bold tracking-tight text-white">
                    {{ demoState.title }}
                </h2>
            </div>

            <!-- Glowing Badge -->
            <span
                class="inline-flex animate-pulse items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-[10px] font-bold tracking-wider text-amber-400 uppercase"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Interactive
            </span>
        </div>

        <!-- Navigation Tab triggers -->
        <div
            class="flex gap-2 rounded-xl border border-white/10 bg-white/5 p-1.5"
        >
            <button
                v-for="tab in demoTabs"
                :key="tab.key"
                class="flex-1 cursor-pointer rounded-lg py-2.5 text-xs font-bold transition-all duration-300"
                :class="
                    activeDemo === tab.key
                        ? 'scale-[1.02] bg-amber-500 font-extrabold text-zinc-950 shadow-lg'
                        : 'text-zinc-300 hover:bg-white/10 hover:text-white'
                "
                @click="activeDemo = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Flow Realtime Visual Dashboard Panel -->
        <div
            class="mt-5 rounded-xl border border-white/10 bg-white/5 p-4 shadow-inner"
        >
            <!-- Panel header -->
            <div
                class="mb-4 flex items-center justify-between border-b border-white/10 pb-3"
            >
                <div class="flex items-center gap-2">
                    <span
                        class="h-2 w-2 animate-ping rounded-full bg-emerald-500"
                    ></span>
                    <span
                        class="text-xs font-bold tracking-wider text-white uppercase"
                    >
                        {{
                            activeDemo === 'pos'
                                ? 'POS Checkout'
                                : activeDemo === 'kds'
                                  ? 'Màn hình Bếp KDS'
                                  : 'Báo cáo thông minh'
                        }}
                    </span>
                </div>
                <span
                    class="rounded border border-amber-500/10 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold tracking-widest text-amber-400 uppercase"
                >
                    Realtime
                </span>
            </div>

            <!-- Live Demo Mockup Views wrapped in transition -->
            <Transition name="fade-slide-tab" mode="out-in">
                <!-- TAB 1: POS Checkout Visual Mockup -->
                <div
                    v-if="activeDemo === 'pos'"
                    key="pos"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <!-- POS Bill summary -->
                    <div
                        class="relative flex h-[125px] flex-col justify-between space-y-3.5 rounded-xl border border-white/10 bg-zinc-950/40 p-4 text-xs sm:h-[145px]"
                    >
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-zinc-400"
                            >
                                <span>Bàn phục vụ:</span>
                                <span
                                    class="rounded bg-white/10 px-2 py-0.5 text-[10px] font-bold text-white"
                                    >Bàn 12</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between text-zinc-400"
                            >
                                <span>Số lượng món:</span>
                                <span class="font-bold text-white">03 món</span>
                            </div>
                        </div>

                        <!-- Receipt decorative dots line -->
                        <div
                            class="my-1 border-t border-dashed border-white/20"
                        ></div>

                        <div class="flex items-center justify-between">
                            <span class="font-medium text-zinc-400"
                                >Tổng tiền:</span
                            >
                            <span
                                class="text-lg font-extrabold tracking-tight text-amber-400"
                                >168.000đ</span
                            >
                        </div>
                    </div>

                    <!-- POS Order list details -->
                    <div
                        class="flex h-[125px] flex-col justify-between space-y-2.5 rounded-xl border border-white/10 bg-zinc-950/40 p-4 text-xs sm:h-[145px]"
                    >
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between border-b border-white/5 py-1 text-zinc-200"
                            >
                                <span class="flex items-center gap-1.5"
                                    ><span class="text-amber-400">🍜</span> Phở
                                    bò tái</span
                                >
                                <span
                                    class="rounded border border-indigo-500/10 bg-indigo-500/15 px-1.5 py-0.5 text-[9px] font-bold text-indigo-400"
                                    >x2</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between border-b border-white/5 py-1 text-zinc-200"
                            >
                                <span class="flex items-center gap-1.5"
                                    ><span class="text-amber-400">🍋</span> Trà
                                    chanh</span
                                >
                                <span
                                    class="rounded border border-indigo-500/10 bg-indigo-500/15 px-1.5 py-0.5 text-[9px] font-bold text-indigo-400"
                                    >x1</span
                                >
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="font-semibold text-zinc-400"
                                >Thanh toán</span
                            >
                            <span
                                class="rounded-full border border-emerald-500/25 bg-emerald-500/20 px-2.5 py-0.5 text-[9px] font-extrabold tracking-wide text-emerald-400 uppercase"
                                >Hoàn tất</span
                            >
                        </div>
                    </div>
                </div>

                <!-- TAB 2: KDS Kitchen Board Visual Mockup -->
                <div
                    v-else-if="activeDemo === 'kds'"
                    key="kds"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <!-- KDS stats metrics card -->
                    <div
                        class="flex h-[125px] flex-col justify-between space-y-3 rounded-xl border border-white/10 bg-zinc-950/40 p-4 text-xs sm:h-[145px]"
                    >
                        <div
                            class="flex items-center justify-between border-b border-white/5 py-1"
                        >
                            <span class="font-medium text-zinc-400"
                                >Order chờ chế biến:</span
                            >
                            <span
                                class="animate-pulse rounded bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-500"
                                >2 đơn</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between border-b border-white/5 py-1"
                        >
                            <span class="font-medium text-zinc-400"
                                >Đang chế biến:</span
                            >
                            <span
                                class="rounded bg-sky-400/10 px-2 py-0.5 text-[10px] font-bold text-sky-400"
                                >1 đơn</span
                            >
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="font-medium text-zinc-400"
                                >Trễ thời gian (SLA):</span
                            >
                            <span
                                class="rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-400"
                                >0 đơn</span
                            >
                        </div>
                    </div>

                    <!-- KDS cooking ticket list -->
                    <div
                        class="flex h-[125px] flex-col justify-between space-y-2 rounded-xl border border-white/10 bg-zinc-950/40 p-3 text-[11px] leading-normal sm:h-[145px]"
                    >
                        <div
                            class="flex items-center justify-between rounded border border-white/5 bg-white/5 p-1.5"
                        >
                            <span class="font-bold text-white"
                                >1. Bún bò Huế</span
                            >
                            <span
                                class="rounded border border-amber-500/20 bg-amber-500/15 px-2 py-0.5 text-[9px] font-bold text-amber-400"
                                >Đang làm</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between rounded border border-white/5 bg-white/5 p-1.5"
                        >
                            <span class="font-bold text-white"
                                >2. Cơm gà chiên</span
                            >
                            <span
                                class="rounded border border-emerald-500/25 bg-emerald-500/20 px-2 py-0.5 text-[9px] font-bold text-emerald-400"
                                >Sẵn sàng</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between rounded border border-white/5 bg-white/5 p-1.5"
                        >
                            <span class="font-bold text-zinc-300"
                                >3. Trà đào cam sả</span
                            >
                            <span
                                class="rounded border border-white/10 bg-white/10 px-2 py-0.5 text-[9px] font-bold text-zinc-300"
                                >In bill</span
                            >
                        </div>
                    </div>
                </div>

                <!-- TAB 3: Executive Analytics Report Visual Mockup -->
                <div
                    v-else-if="activeDemo === 'report'"
                    key="report"
                    class="grid gap-3 sm:grid-cols-2"
                >
                    <!-- Report metrics KPI -->
                    <div
                        class="flex h-[125px] flex-col justify-between space-y-3.5 rounded-xl border border-white/10 bg-zinc-950/40 p-4 text-xs sm:h-[145px]"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-amber-400">💰</span>
                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-semibold tracking-wider text-zinc-400 uppercase"
                                    >Doanh thu trong ngày</span
                                >
                                <span
                                    class="text-base font-extrabold text-white"
                                    >12,8M VNĐ</span
                                >
                            </div>
                        </div>
                        <div
                            class="flex items-center gap-2 border-t border-white/5 pt-2"
                        >
                            <span class="text-sm text-emerald-400">📈</span>
                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-semibold tracking-wider text-zinc-400 uppercase"
                                    >Hoàn thành order</span
                                >
                                <span class="text-xs font-bold text-white"
                                    >98% (SLA tốt)</span
                                >
                            </div>
                        </div>
                        <div
                            class="flex items-center gap-2 border-t border-white/5 pt-2"
                        >
                            <span class="text-sm text-rose-400">🏆</span>
                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-semibold tracking-wider text-zinc-400 uppercase"
                                    >Món bán chạy nhất</span
                                >
                                <span class="text-xs font-bold text-amber-400"
                                    >Phở bò tái nạm</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- AI and Audit Signals -->
                    <div
                        class="flex h-[125px] flex-col justify-between space-y-2 rounded-xl border border-white/10 bg-zinc-950/40 p-3 text-xs leading-normal sm:h-[145px]"
                    >
                        <div
                            class="flex items-center justify-between border-b border-white/5 p-1.5 text-zinc-200"
                        >
                            <span class="flex items-center gap-1.5"
                                >⚡ Cao điểm</span
                            >
                            <span class="font-bold text-sky-400"
                                >11:30 - 13:30</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between border-b border-white/5 p-1.5 text-zinc-200"
                        >
                            <span class="flex items-center gap-1.5"
                                >⚠️ Kho tồn</span
                            >
                            <span class="font-bold text-rose-400"
                                >2 nguyên liệu thấp</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-between p-1.5 text-zinc-200"
                        >
                            <span class="flex items-center gap-1.5"
                                >🔒 Tra soát</span
                            >
                            <span class="font-bold text-amber-400"
                                >1 thay đổi giá</span
                            >
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- CTA button at the bottom of the widget -->
        <div class="mt-6 border-t border-white/10 pt-2">
            <Button
                as-child
                size="lg"
                class="w-full bg-gradient-to-r from-amber-500 to-yellow-600 py-4 text-xs font-extrabold tracking-wider text-zinc-950 uppercase shadow-lg shadow-amber-500/20 transition-all duration-300 hover:from-amber-600 hover:to-yellow-700 active:scale-95"
            >
                <Link :href="register()">Đăng ký dùng thử miễn phí</Link>
            </Button>
        </div>
    </div>
</template>
