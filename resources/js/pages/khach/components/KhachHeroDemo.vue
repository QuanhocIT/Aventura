<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
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
    <div class="relative w-full max-w-lg mx-auto lg:ml-auto rounded-2xl border border-white/10 bg-zinc-950/75 backdrop-blur-xl shadow-2xl p-6 sm:p-8 transition-all duration-500 hover:border-amber-500/25">
        
        <!-- Header with Live Demo tag -->
        <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-5">
            <div>
                <span class="text-xs text-amber-400 font-extrabold tracking-widest uppercase block mb-1">
                    Live demo
                </span>
                <h2 class="text-2xl font-bold text-white tracking-tight">
                    {{ demoState.title }}
                </h2>
            </div>
            
            <!-- Glowing Badge -->
            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-[10px] font-bold text-amber-400 uppercase tracking-wider animate-pulse">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Interactive
            </span>
        </div>
        
        <!-- Navigation Tab triggers -->
        <div class="flex gap-2 bg-white/5 border border-white/10 p-1.5 rounded-xl">
            <button
                v-for="tab in demoTabs"
                :key="tab.key"
                class="flex-1 cursor-pointer rounded-lg py-2.5 text-xs font-bold transition-all duration-300"
                :class="activeDemo === tab.key
                    ? 'bg-amber-500 text-zinc-950 shadow-lg font-extrabold scale-[1.02]'
                    : 'text-zinc-300 hover:bg-white/10 hover:text-white'"
                @click="activeDemo = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Flow Realtime Visual Dashboard Panel -->
        <div class="mt-5 rounded-xl border border-white/10 bg-white/5 p-4 shadow-inner">
            <!-- Panel header -->
            <div class="flex items-center justify-between border-b border-white/10 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span class="text-xs font-bold text-white uppercase tracking-wider">
                        {{ activeDemo === 'pos' ? 'POS Checkout' : activeDemo === 'kds' ? 'Màn hình Bếp KDS' : 'Báo cáo thông minh' }}
                    </span>
                </div>
                <span class="text-[10px] font-bold text-amber-400 uppercase tracking-widest bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/10">
                    Realtime
                </span>
            </div>
            
            <!-- TAB 1: POS Checkout Visual Mockup -->
            <div v-if="activeDemo === 'pos'" class="grid gap-3 sm:grid-cols-2">
                <!-- POS Bill summary -->
                <div class="relative space-y-3.5 rounded-xl bg-zinc-950/40 border border-white/10 p-4 text-xs flex flex-col justify-between h-[125px] sm:h-[145px]">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-zinc-400">
                            <span>Bàn phục vụ:</span>
                            <span class="font-bold text-white bg-white/10 px-2 py-0.5 rounded text-[10px]">Bàn 12</span>
                        </div>
                        <div class="flex items-center justify-between text-zinc-400">
                            <span>Số lượng món:</span>
                            <span class="font-bold text-white">03 món</span>
                        </div>
                    </div>
                    
                    <!-- Receipt decorative dots line -->
                    <div class="border-t border-dashed border-white/20 my-1"></div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400 font-medium">Tổng tiền:</span>
                        <span class="text-lg font-extrabold text-amber-400 tracking-tight">168.000đ</span>
                    </div>
                </div>
                
                <!-- POS Order list details -->
                <div class="space-y-2.5 rounded-xl bg-zinc-950/40 border border-white/10 p-4 text-xs h-[125px] sm:h-[145px] flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-zinc-200 py-1 border-b border-white/5">
                            <span class="flex items-center gap-1.5"><span class="text-amber-400">🍜</span> Phở bò tái</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-500/15 text-indigo-400 border border-indigo-500/10">x2</span>
                        </div>
                        <div class="flex items-center justify-between text-zinc-200 py-1 border-b border-white/5">
                            <span class="flex items-center gap-1.5"><span class="text-amber-400">🍋</span> Trà chanh</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-500/15 text-indigo-400 border border-indigo-500/10">x1</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <span class="font-semibold text-zinc-400">Thanh toán</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-500/20 text-emerald-400 border border-emerald-500/25 tracking-wide uppercase">Hoàn tất</span>
                    </div>
                </div>
            </div>
            
            <!-- TAB 2: KDS Kitchen Board Visual Mockup -->
            <div v-else-if="activeDemo === 'kds'" class="grid gap-3 sm:grid-cols-2">
                <!-- KDS stats metrics card -->
                <div class="space-y-3 rounded-xl bg-zinc-950/40 border border-white/10 p-4 text-xs h-[125px] sm:h-[145px] flex flex-col justify-between">
                    <div class="flex items-center justify-between py-1 border-b border-white/5">
                        <span class="text-zinc-400 font-medium">Order chờ chế biến:</span>
                        <span class="font-bold text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded text-[10px] animate-pulse">2 đơn</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-white/5">
                        <span class="text-zinc-400 font-medium">Đang chế biến:</span>
                        <span class="font-bold text-sky-400 bg-sky-400/10 px-2 py-0.5 rounded text-[10px]">1 đơn</span>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-zinc-400 font-medium">Trễ thời gian (SLA):</span>
                        <span class="font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded text-[10px]">0 đơn</span>
                    </div>
                </div>
                
                <!-- KDS cooking ticket list -->
                <div class="space-y-2 rounded-xl bg-zinc-950/40 border border-white/10 p-3 text-[11px] leading-normal h-[125px] sm:h-[145px] flex flex-col justify-between">
                    <div class="flex items-center justify-between p-1.5 rounded bg-white/5 border border-white/5">
                        <span class="text-white font-bold">1. Bún bò Huế</span>
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/20">Đang làm</span>
                    </div>
                    <div class="flex items-center justify-between p-1.5 rounded bg-white/5 border border-white/5">
                        <span class="text-white font-bold">2. Cơm gà chiên</span>
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/25">Sẵn sàng</span>
                    </div>
                    <div class="flex items-center justify-between p-1.5 rounded bg-white/5 border border-white/5">
                        <span class="text-zinc-300 font-bold">3. Trà đào cam sả</span>
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-white/10 text-zinc-300 border border-white/10">In bill</span>
                    </div>
                </div>
            </div>
            
            <!-- TAB 3: Executive Analytics Report Visual Mockup -->
            <div v-else-if="activeDemo === 'report'" class="grid gap-3 sm:grid-cols-2">
                <!-- Report metrics KPI -->
                <div class="space-y-3.5 rounded-xl bg-zinc-950/40 border border-white/10 p-4 text-xs h-[125px] sm:h-[145px] flex flex-col justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-400 text-sm">💰</span>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold">Doanh thu trong ngày</span>
                            <span class="text-base font-extrabold text-white">12,8M VNĐ</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 border-t border-white/5 pt-2">
                        <span class="text-emerald-400 text-sm">📈</span>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold">Hoàn thành order</span>
                            <span class="text-xs font-bold text-white">98% (SLA tốt)</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 border-t border-white/5 pt-2">
                        <span class="text-rose-400 text-sm">🏆</span>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold">Món bán chạy nhất</span>
                            <span class="text-xs font-bold text-amber-400">Phở bò tái nạm</span>
                        </div>
                    </div>
                </div>
                
                <!-- AI and Audit Signals -->
                <div class="space-y-2 rounded-xl bg-zinc-950/40 border border-white/10 p-3 text-xs leading-normal h-[125px] sm:h-[145px] flex flex-col justify-between">
                    <div class="flex items-center justify-between p-1.5 border-b border-white/5 text-zinc-200">
                        <span class="flex items-center gap-1.5">⚡ Cao điểm</span>
                        <span class="font-bold text-sky-400">11:30 - 13:30</span>
                    </div>
                    <div class="flex items-center justify-between p-1.5 border-b border-white/5 text-zinc-200">
                        <span class="flex items-center gap-1.5">⚠️ Kho tồn</span>
                        <span class="font-bold text-rose-400">2 nguyên liệu thấp</span>
                    </div>
                    <div class="flex items-center justify-between p-1.5 text-zinc-200">
                        <span class="flex items-center gap-1.5">🔒 Tra soát</span>
                        <span class="font-bold text-amber-400">1 thay đổi giá</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA button at the bottom of the widget -->
        <div class="mt-6 pt-2 border-t border-white/10">
            <Button as-child size="lg" class="w-full bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-600 hover:to-yellow-700 text-zinc-950 font-extrabold py-4 text-xs tracking-wider uppercase shadow-lg shadow-amber-500/20 active:scale-95 transition-all duration-300">
                <Link :href="register()">Đăng ký dùng thử miễn phí</Link>
            </Button>
        </div>

    </div>
</template>
