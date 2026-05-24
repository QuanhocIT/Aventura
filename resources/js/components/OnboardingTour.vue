<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Play, X, ArrowRight, CheckCircle2, ChevronRight, Award, Compass } from 'lucide-vue-next';

type TourStep = {
    selector: string;
    title: string;
    content: string;
    placement: 'top' | 'bottom' | 'left' | 'right';
    page: string; // The URL/page it must run on
};

const page = usePage();
const user = computed(() => page.props.auth?.user as any);
const isSuperAdmin = computed(() => {
    const roles = page.props.roles ?? [];
    return Array.isArray(roles) ? roles.includes('super_admin') || roles.includes('admin') : false;
});

// Tiáº¿n Ä‘á»™ Onboarding lÆ°u trong User Model
const onboardingStatus = computed(() => user.value?.onboarding_status);

// Tráº¡ng thÃ¡i Tour hiá»‡n táº¡i
const currentDay = ref(1);
const activeStepIndex = ref(0);
const isTourActive = ref(false);
const targetRect = ref<DOMRect | null>(null);
const tooltipStyle = ref<any>({});
const scrollPosition = ref({ x: window.scrollX, y: window.scrollY });
const isSuccessOpen = ref(false);

// Äá»‹nh nghÄ©a táº¥t cáº£ cÃ¡c bÆ°á»›c Tour cho 3 NgÃ y
const tourSteps: Record<number, TourStep[]> = {
    1: [
        {
            selector: '#sidebar-link-products',
            title: 'BÆ°á»›c chÃ¢n Ä‘áº§u tiÃªn ðŸ‘£',
            content: 'ChÃ o má»«ng báº¡n Ä‘áº¿n vá»›i F&BViet! Äáº§u tiÃªn, hÃ£y truy cáº­p vÃ o menu Thá»±c Ä‘Æ¡n & MÃ³n Ä‘á»ƒ thiáº¿t láº­p menu bÃ¡n hÃ ng cá»§a quÃ¡n.',
            placement: 'right',
            page: 'dashboard'
        },
        {
            selector: '#btn-add-category',
            title: 'Táº¡o nhÃ³m mÃ³n Äƒn ðŸ“‚',
            content: 'Tuyá»‡t vá»i! HÃ£y nháº¥p vÃ o Ä‘Ã¢y Ä‘á»ƒ thÃªm nhÃ³m thá»±c Ä‘Æ¡n má»›i (vÃ­ dá»¥: Khai vá»‹, MÃ³n nÆ°á»›c, NÆ°á»›c Ã©p).',
            placement: 'bottom',
            page: 'products'
        },
        {
            selector: '#btn-add-product',
            title: 'ThÃªm mÃ³n Äƒn Ä‘áº§u tiÃªn ðŸ²',
            content: 'BÃ¢y giá» hÃ£y báº¥m nÃºt nÃ y Ä‘á»ƒ nháº­p cÃ¡c mÃ³n Äƒn thá»±c táº¿ cá»§a quÃ¡n vÃ o nhÃ³m thá»±c Ä‘Æ¡n tÆ°Æ¡ng á»©ng.',
            placement: 'top',
            page: 'products'
        }
    ],
    2: [
        {
            selector: '#sidebar-link-inventory',
            title: 'Chuáº©n hÃ³a váº­n hÃ nh ðŸ“¦',
            content: 'NgÃ y 2: Quáº£n lÃ½ kho. HÃ£y báº¥m vÃ o Kho nguyÃªn liá»‡u Ä‘á»ƒ cáº¥u hÃ¬nh nguyÃªn liá»‡u vÃ  kÃ­ch hoáº¡t cÆ¡ cháº¿ trá»« kho tá»± Ä‘á»™ng.',
            placement: 'right',
            page: 'dashboard'
        },
        {
            selector: '.btn-set-recipe:first-of-type',
            title: 'CÃ i Ä‘áº·t Ä‘á»‹nh lÆ°á»£ng ðŸ§ª',
            content: 'Nháº¥p vÃ o nÃºt Thiáº¿t láº­p cá»§a má»™t mÃ³n Äƒn Ä‘á»ƒ xÃ¢y dá»±ng cÃ´ng thá»©c náº¥u (vÃ­ dá»¥: 1 bÃ¡t phá»Ÿ = 150g bÃ¡nh phá»Ÿ, 80g thá»‹t bÃ²).',
            placement: 'left',
            page: 'inventory'
        }
    ],
    3: [
        {
            selector: '#sidebar-link-employees',
            title: 'Quáº£n trá»‹ nhÃ¢n sá»± ðŸ‘¥',
            content: 'NgÃ y 3: Thiáº¿t láº­p nhÃ¢n sá»±. Nháº¥p vÃ o Ä‘Ã¢y Ä‘á»ƒ thÃªm nhÃ¢n viÃªn vÃ  phÃ¢n quyá»n Thu ngÃ¢n, Báº¿p nhanh chÃ³ng.',
            placement: 'right',
            page: 'dashboard'
        },
        {
            selector: '#btn-add-employee',
            title: 'ThÃªm nhÃ¢n sá»± má»›i âž•',
            content: 'Báº¥m vÃ o Ä‘Ã¢y Ä‘á»ƒ táº¡o tÃ i khoáº£n Ä‘Äƒng nháº­p cho nhÃ¢n viÃªn cá»§a báº¡n.',
            placement: 'bottom',
            page: 'employees'
        },
        {
            selector: '#scheduler-card',
            title: 'Xáº¿p lá»‹ch lÃ m viá»‡c ðŸ“…',
            content: 'Cuá»‘i cÃ¹ng, quáº£n lÃ½ ca lÃ m viá»‡c vÃ  xáº¿p lá»‹ch lÃ m viá»‡c hÃ ng tuáº§n cho nhÃ¢n viÃªn trá»±c quan ngay táº¡i Ä‘Ã¢y.',
            placement: 'top',
            page: 'employees'
        }
    ]
};

// Láº¥y danh sÃ¡ch cÃ¡c bÆ°á»›c cá»§a NgÃ y hiá»‡n táº¡i
const currentSteps = computed(() => tourSteps[currentDay.value] ?? []);
const activeStep = computed<TourStep | null>(() => currentSteps.value[activeStepIndex.value] ?? null);

// HÃ m tÃ¬m pháº§n tá»­ vÃ  láº¥y vá»‹ trÃ­
let searchInterval: any = null;

const updateTargetPosition = () => {
    if (!isTourActive.value || !activeStep.value) return;

    const el = document.querySelector(activeStep.value.selector) as HTMLElement;
    if (el) {
        // Tá»± Ä‘á»™ng scroll pháº§n tá»­ vÃ o táº§m nhÃ¬n náº¿u cáº§n
        const rect = el.getBoundingClientRect();
        targetRect.value = rect;

        // TÃ­nh toÃ¡n vá»‹ trÃ­ cá»§a tooltip
        const margin = 12;
        let top = 0;
        let left = 0;

        const scrollY = window.scrollY;
        const scrollX = window.scrollX;

        if (activeStep.value.placement === 'bottom') {
            top = rect.bottom + scrollY + margin;
            left = rect.left + scrollX + (rect.width / 2) - 160; // 160 lÃ  ná»­a width tooltip Æ°á»›c lÆ°á»£ng
        } else if (activeStep.value.placement === 'top') {
            top = rect.top + scrollY - 180 - margin; // 180 lÃ  height Æ°á»›c lÆ°á»£ng
            left = rect.left + scrollX + (rect.width / 2) - 160;
        } else if (activeStep.value.placement === 'right') {
            top = rect.top + scrollY + (rect.height / 2) - 80;
            left = rect.right + scrollX + margin;
        } else if (activeStep.value.placement === 'left') {
            top = rect.top + scrollY + (rect.height / 2) - 80;
            left = rect.left + scrollX - 330 - margin; // 330 lÃ  width tooltip Æ°á»›c lÆ°á»£ng
        }

        // TrÃ¡nh tooltip vÄƒng ra ngoÃ i viewport
        if (left < 10) left = 10;
        if (left + 320 > window.innerWidth) left = window.innerWidth - 340;

        tooltipStyle.value = {
            top: `${top}px`,
            left: `${left}px`,
            position: 'absolute',
            zIndex: 9999
        };
    } else {
        targetRect.value = null;
    }
};

const startTargetPolling = () => {
    stopTargetPolling();
    searchInterval = setInterval(() => {
        updateTargetPosition();
    }, 400);
};

const stopTargetPolling = () => {
    if (searchInterval) {
        clearInterval(searchInterval);
        searchInterval = null;
    }
};

// Khá»Ÿi cháº¡y Tour
const startTour = (day: number) => {
    currentDay.value = day;
    activeStepIndex.value = 0;
    isTourActive.value = true;
    isSuccessOpen.value = false;
    nextTick(() => {
        updateTargetPosition();
        startTargetPolling();
    });
};

const skipTour = () => {
    isTourActive.value = false;
    stopTargetPolling();
    targetRect.value = null;
};

// Xá»­ lÃ½ bÆ°á»›c káº¿ tiáº¿p
const nextStep = () => {
    if (activeStepIndex.value < currentSteps.value.length - 1) {
        activeStepIndex.value++;
        
        // Gá»­i API lÆ°u tiáº¿n Ä‘á»™ bÆ°á»›c
        router.post('/api/onboarding/update', {
            current_day: currentDay.value,
            step: `step_${activeStepIndex.value}`,
            completed: true
        }, { preserveScroll: true });

        nextTick(() => {
            updateTargetPosition();
        });
    } else {
        // HoÃ n thÃ nh tour cá»§a NgÃ y
        completeDay();
    }
};

// HoÃ n thÃ nh cáº£ ngÃ y
const completeDay = () => {
    isTourActive.value = false;
    stopTargetPolling();
    targetRect.value = null;
    isSuccessOpen.value = true;

    // Gá»i API lÆ°u tráº¡ng thÃ¡i hoÃ n thÃ nh ngÃ y
    router.post('/api/onboarding/update', {
        current_day: currentDay.value,
        completed_day: currentDay.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Táº£i láº¡i dá»¯ liá»‡u trang
        }
    });
};

// Chuyá»ƒn sang ngÃ y káº¿ tiáº¿p
const startNextDay = () => {
    isSuccessOpen.value = false;
    const next = currentDay.value + 1;
    if (next <= 3) {
        router.visit(next === 2 ? '/inventory' : '/employees', {
            onSuccess: () => {
                setTimeout(() => {
                    startTour(next);
                }, 1000);
            }
        });
    } else {
        router.visit('/support');
    }
};

// Theo dÃµi sá»± thay Ä‘á»•i URL Ä‘á»ƒ tá»± Ä‘á»™ng kÃ­ch hoáº¡t/cáº­p nháº­t vá»‹ trÃ­
watch(() => page.url, () => {
    nextTick(() => {
        updateTargetPosition();
    });
});

// Láº¯ng nghe tráº¡ng thÃ¡i onboarding cá»§a User tá»« Backend Ä‘á»ƒ tá»± Ä‘á»™ng kÃ­ch hoáº¡t
onMounted(() => {
    window.addEventListener('resize', updateTargetPosition);
    window.addEventListener('scroll', updateTargetPosition);

    // KÃ­ch hoáº¡t tour tá»± Ä‘á»™ng náº¿u user Ä‘Äƒng nháº­p láº§n Ä‘áº§u vÃ  chÆ°a hoÃ n thÃ nh Day 1
    setTimeout(() => {
        if (user.value && !isSuperAdmin.value) {
            const status = onboardingStatus.value;
            if (!status) {
                // ÄÄƒng nháº­p láº§n Ä‘áº§u chÆ°a cÃ³ status, kÃ­ch hoáº¡t Day 1
                startTour(1);
            } else {
                const day1 = status.day_1;
                const day2 = status.day_2;
                const day3 = status.day_3;

                if (!day1 || !day1.completed_at) {
                    startTour(1);
                } else if ((!day2 || !day2.completed_at) && status.current_day === 2) {
                    startTour(2);
                } else if ((!day3 || !day3.completed_at) && status.current_day === 3) {
                    startTour(3);
                }
            }
        }
    }, 2000); // Äá»£i 2 giÃ¢y sau khi app táº£i xong Ä‘á»ƒ gÃ¢y áº¥n tÆ°á»£ng tá»± nhiÃªn
});

onUnmounted(() => {
    window.removeEventListener('resize', updateTargetPosition);
    window.removeEventListener('scroll', updateTargetPosition);
    stopTargetPolling();
});

// Xem cÃ³ Ä‘ang cháº¡y Ä‘Ãºng trang Ä‘Æ°á»£c cáº¥u hÃ¬nh cho Step hay khÃ´ng
const isCorrectPage = computed(() => {
    if (!activeStep.value) return false;
    const currentUrl = page.url.toLowerCase();
    
    if (activeStep.value.page === 'dashboard') {
        return currentUrl.includes('/dashboard');
    }
    return currentUrl.includes('/' + activeStep.value.page);
});

// Äiá»u hÆ°á»›ng nhanh Ä‘áº¿n trang Ä‘Ãºng náº¿u khÃ¡ch hÃ ng Ä‘i láº¡c
const navigateToStepPage = () => {
    if (!activeStep.value) return;
    const dest = activeStep.value.page === 'dashboard' ? '/dashboard' : '/' + activeStep.value.page;
    router.visit(dest);
};

// Phá»¥c vá»¥ viá»‡c reset tour thá»§ cÃ´ng tá»« bÃªn ngoÃ i (vÃ­ dá»¥ tá»« Support Page)
defineExpose({
    startTour
});
</script>

<template>
    <div>
        <!-- Backdrop Ä‘Ã¨ sÃ¡ng Ä‘Ã¨ lÃªn pháº§n tá»­ Ä‘Æ°á»£c chá»n -->
        <div
            v-if="isTourActive && targetRect && isCorrectPage"
            class="pointer-events-none fixed inset-0 transition-opacity duration-300"
            style="z-index: 9998;"
            :style="{
                background: `radial-gradient(circle 85px at ${targetRect.left + targetRect.width / 2}px ${targetRect.top + targetRect.height / 2}px, transparent 100%, rgba(15, 23, 42, 0.45) 100%)`
            }"
        />

        <!-- Pulse Highlight Ä‘Ã¨ trá»±c tiáº¿p lÃªn pháº§n tá»­ má»¥c tiÃªu -->
        <div
            v-if="isTourActive && targetRect && isCorrectPage"
            class="pointer-events-none absolute rounded-md border-2 border-primary animate-pulse"
            style="z-index: 9998;"
            :style="{
                top: `${targetRect.top + scrollPosition.y - 2}px`,
                left: `${targetRect.left + scrollPosition.x - 2}px`,
                width: `${targetRect.width + 4}px`,
                height: `${targetRect.height + 4}px`,
                boxShadow: '0 0 0 9999px rgba(0, 0, 0, 0.3), 0 0 15px 4px #6366f1'
            }"
        />

        <!-- Glowing indicator dot -->
        <span
            v-if="isTourActive && targetRect && isCorrectPage"
            class="absolute size-4 rounded-full bg-indigo-500 animate-ping pointer-events-none"
            style="z-index: 9999;"
            :style="{
                top: `${targetRect.top + scrollPosition.y + targetRect.height / 2 - 8}px`,
                left: `${targetRect.left + scrollPosition.x + targetRect.width / 2 - 8}px`
            }"
        />

        <!-- Tooltip Card -->
        <div
            v-if="isTourActive && activeStep"
            :style="tooltipStyle"
            class="w-[320px] rounded-2xl p-5 border border-slate-200/60 dark:border-slate-800/60 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl shadow-[0_20px_50px_rgba(0,0,0,0.15)] transform transition-all duration-300 scale-100 flex flex-col gap-4 text-left"
        >
            <!-- Step Header -->
            <div class="flex items-center justify-between border-b pb-2.5">
                <span class="flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded-full">
                    <Compass class="size-3.5" />
                    {{ activeStep.title }}
                </span>
                <button @click="skipTour" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <X class="size-4" />
                </button>
            </div>

            <!-- Cáº£nh bÃ¡o Ä‘i sai trang -->
            <div v-if="!isCorrectPage" class="rounded-lg bg-amber-50 dark:bg-amber-950/40 p-3 text-xs text-amber-800 dark:text-amber-300 border border-amber-200/50">
                <p class="font-medium">Báº¡n Ä‘Ã£ chuyá»ƒn trang!</p>
                <p class="mt-1">Äá»ƒ tiáº¿p tá»¥c pháº§n hÆ°á»›ng dáº«n, vui lÃ²ng nháº¥p vÃ o nÃºt bÃªn dÆ°á»›i Ä‘á»ƒ quay láº¡i Ä‘Ãºng trang.</p>
                <button
                    @click="navigateToStepPage"
                    class="mt-2.5 flex items-center gap-1 font-semibold text-amber-900 dark:text-amber-200 hover:underline"
                >
                    Äáº¿n trang hÆ°á»›ng dáº«n <ChevronRight class="size-3" />
                </button>
            </div>

            <!-- Content -->
            <div v-else class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed font-normal">
                {{ activeStep.content }}
            </div>

            <!-- Footer & Progress -->
            <div class="flex items-center justify-between mt-1 pt-3 border-t">
                <!-- Dots progress -->
                <div class="flex gap-1">
                    <span
                        v-for="(s, idx) in currentSteps"
                        :key="idx"
                        class="h-1.5 rounded-full transition-all duration-300"
                        :class="idx === activeStepIndex ? 'w-4 bg-indigo-600 dark:bg-indigo-400' : 'w-1.5 bg-slate-200 dark:bg-slate-700'"
                    />
                </div>

                <!-- Action Button -->
                <button
                    v-if="isCorrectPage"
                    @click="nextStep"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-medium text-xs px-3.5 py-2 shadow-md shadow-indigo-600/10 transition-all"
                >
                    {{ activeStepIndex === currentSteps.length - 1 ? 'HoÃ n thÃ nh' : 'Tiáº¿p tá»¥c' }}
                    <ArrowRight class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Success Modal (HoÃ n thÃ nh cáº£ ngÃ y) -->
        <div
            v-if="isSuccessOpen"
            class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm transition-opacity duration-300"
            style="z-index: 10000;"
        >
            <div class="max-w-md w-full bg-white dark:bg-slate-900 p-8 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-2xl flex flex-col items-center text-center gap-6 animate-in fade-in zoom-in-95 duration-200">
                <div class="size-16 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400 animate-bounce">
                    <Award class="size-9" />
                </div>

                <div>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">
                        ChÃºc má»«ng báº¡n Ä‘Ã£ hoÃ n thÃ nh NgÃ y {{ currentDay }}! ðŸŽ‰
                    </h2>
                    <p class="mt-2.5 text-sm text-slate-500 dark:text-slate-400 leading-relaxed px-2">
                        <span v-if="currentDay === 1">
                            Tuyá»‡t vá»i! Báº¡n Ä‘Ã£ náº¯m vá»¯ng cÃ¡c bÆ°á»›c táº¡o nhÃ³m thá»±c Ä‘Æ¡n vÃ  thÃªm mÃ³n Äƒn thá»±c táº¿. Cá»­a hÃ ng cá»§a báº¡n Ä‘Ã£ sáºµn sÃ ng bÃ¡n sáº£n pháº©m Ä‘áº§u tiÃªn!
                        </span>
                        <span v-else-if="currentDay === 2">
                            Xuáº¥t sáº¯c! Viá»‡c thiáº¿t láº­p cÃ´ng thá»©c vÃ  Ä‘á»‹nh lÆ°á»£ng nguyÃªn liá»‡u sáº½ giÃºp pháº§n má»m tá»± Ä‘á»™ng tÃ­nh toÃ¡n tá»“n kho cá»§a báº¡n chÃ­nh xÃ¡c sau má»—i hÃ³a Ä‘Æ¡n bÃ¡n hÃ ng.
                        </span>
                        <span v-else>
                            HoÃ n háº£o! Báº¡n Ä‘Ã£ hoÃ n táº¥t chuá»—i Guided Tours chuáº©n hÃ³a F&B. Báº¡n hiá»‡n Ä‘Ã£ lÃ m chá»§ Ä‘Æ°á»£c nhÃ¢n sá»±, quáº£n lÃ½ lá»‹ch lÃ m viá»‡c vÃ  kho nguyÃªn liá»‡u!
                        </span>
                    </p>
                </div>

                <!-- Progress bar inside success modal -->
                <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                    <div
                        class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                        :style="{ width: `${(currentDay / 3) * 100}%` }"
                    />
                </div>

                <div class="flex flex-col gap-2 w-full mt-2">
                    <button
                        v-if="currentDay < 3"
                        @click="startNextDay"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm py-3 transition-colors shadow-lg shadow-indigo-600/10 active:scale-98"
                    >
                        Tiáº¿n Ä‘áº¿n NgÃ y {{ currentDay + 1 }}
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        @click="isSuccessOpen = false"
                        class="w-full inline-flex items-center justify-center rounded-xl border border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60 text-slate-600 dark:text-slate-300 font-semibold text-sm py-3 transition-colors"
                    >
                        Äá»ƒ sau / ÄÃ³ng
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>



