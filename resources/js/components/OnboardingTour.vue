<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { Play, X, ArrowRight, CheckCircle2, ChevronRight, Award, Compass } from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue';

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

// Tiến độ Onboarding lưu trong User Model
const onboardingStatus = computed(() => user.value?.onboarding_status);

// Trạng thái Tour hiện tại
const currentDay = ref(1);
const activeStepIndex = ref(0);
const isTourActive = ref(false);
const targetRect = ref<DOMRect | null>(null);
const tooltipStyle = ref<any>({});
const scrollPosition = ref({ x: window.scrollX, y: window.scrollY });
const isSuccessOpen = ref(false);

// Định nghĩa tất cả các bước Tour cho 3 Ngày
const tourSteps: Record<number, TourStep[]> = {
    1: [
        {
            selector: '#sidebar-link-products',
            title: 'Bước chân đầu tiên 👣',
            content: 'Chào mừng bạn đến với F&BViet! Đầu tiên, hãy truy cập vào menu Thực đơn & Món để thiết lập menu bán hàng của quán.',
            placement: 'right',
            page: 'dashboard'
        },
        {
            selector: '#btn-add-category',
            title: 'Tạo nhóm món ăn 📂',
            content: 'Tuyệt vời! Hãy nhấp vào đây để thêm nhóm thực đơn mới (ví dụ: Khai vị, Món nước, Nước ép).',
            placement: 'bottom',
            page: 'products'
        },
        {
            selector: '#btn-add-product',
            title: 'Thêm món ăn đầu tiên 🍲',
            content: 'Bây giờ hãy bấm nút này để nhập các món ăn thực tế của quán vào nhóm thực đơn tương ứng.',
            placement: 'top',
            page: 'products'
        }
    ],
    2: [
        {
            selector: '#sidebar-link-inventory',
            title: 'Chuẩn hóa vận hành 📦',
            content: 'Ngày 2: Quản lý kho. Hãy bấm vào Kho nguyên liệu để cấu hình nguyên liệu và kích hoạt cơ chế trừ kho tự động.',
            placement: 'right',
            page: 'dashboard'
        },
        {
            selector: '.btn-set-recipe:first-of-type',
            title: 'Cài đặt định lượng 🧪',
            content: 'Nhấp vào nút Thiết lập của một món ăn để xây dựng công thức nấu (ví dụ: 1 bát phở = 150g bánh phở, 80g thịt bò).',
            placement: 'left',
            page: 'inventory'
        }
    ],
    3: [
        {
            selector: '#sidebar-link-employees',
            title: 'Quản trị nhân sự 👥',
            content: 'Ngày 3: Thiết lập nhân sự. Nhấp vào đây để thêm nhân viên và phân quyền Thu ngân, Bếp nhanh chóng.',
            placement: 'right',
            page: 'dashboard'
        },
        {
            selector: '#btn-add-employee',
            title: 'Thêm nhân sự mới ➕',
            content: 'Bấm vào đây để tạo tài khoản đăng nhập cho nhân viên của bạn.',
            placement: 'bottom',
            page: 'employees'
        },
        {
            selector: '#scheduler-card',
            title: 'Xếp lịch làm việc 📅',
            content: 'Cuối cùng, quản lý ca làm việc và xếp lịch làm việc hàng tuần cho nhân viên trực quan ngay tại đây.',
            placement: 'top',
            page: 'employees'
        }
    ]
};

// Lấy danh sách các bước của Ngày hiện tại
const currentSteps = computed(() => tourSteps[currentDay.value] ?? []);
const activeStep = computed<TourStep | null>(() => currentSteps.value[activeStepIndex.value] ?? null);

// Hàm tìm phần tử và lấy vị trí
let searchInterval: any = null;

const updateTargetPosition = () => {
    if (!isTourActive.value || !activeStep.value) {
return;
}

    const el = document.querySelector(activeStep.value.selector) as HTMLElement;

    if (el) {
        // Tự động scroll phần tử vào tầm nhìn nếu cần
        const rect = el.getBoundingClientRect();
        targetRect.value = rect;

        // Tính toán vị trí của tooltip
        const margin = 12;
        let top = 0;
        let left = 0;

        const scrollY = window.scrollY;
        const scrollX = window.scrollX;

        if (activeStep.value.placement === 'bottom') {
            top = rect.bottom + scrollY + margin;
            left = rect.left + scrollX + (rect.width / 2) - 160; // 160 là nửa width tooltip ước lượng
        } else if (activeStep.value.placement === 'top') {
            top = rect.top + scrollY - 180 - margin; // 180 là height ước lượng
            left = rect.left + scrollX + (rect.width / 2) - 160;
        } else if (activeStep.value.placement === 'right') {
            top = rect.top + scrollY + (rect.height / 2) - 80;
            left = rect.right + scrollX + margin;
        } else if (activeStep.value.placement === 'left') {
            top = rect.top + scrollY + (rect.height / 2) - 80;
            left = rect.left + scrollX - 330 - margin; // 330 là width tooltip ước lượng
        }

        // Tránh tooltip văng ra ngoài viewport
        if (left < 10) {
left = 10;
}

        if (left + 320 > window.innerWidth) {
left = window.innerWidth - 340;
}

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

// Khởi chạy Tour
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
    // Ghi nhớ người dùng đã dismiss tour ngày này trong session — không tự bật lại khi reload
    sessionStorage.setItem(`aventura_tour_day${currentDay.value}_dismissed`, '1');
};

// Xử lý bước kế tiếp
const nextStep = () => {
    if (activeStepIndex.value < currentSteps.value.length - 1) {
        activeStepIndex.value++;
        
        // Gửi API lưu tiến độ bước
        router.post('/api/onboarding/update', {
            current_day: currentDay.value,
            step: `step_${activeStepIndex.value}`,
            completed: true
        }, { preserveScroll: true });

        nextTick(() => {
            updateTargetPosition();
        });
    } else {
        // Hoàn thành tour của Ngày
        completeDay();
    }
};

// Hoàn thành cả ngày
const completeDay = () => {
    isTourActive.value = false;
    stopTargetPolling();
    targetRect.value = null;
    isSuccessOpen.value = true;
    // Xóa flag dismissed để ngày tiếp theo có thể auto-start bình thường
    sessionStorage.removeItem(`aventura_tour_day${currentDay.value}_dismissed`);

    // Gọi API lưu trạng thái hoàn thành ngày
    router.post('/api/onboarding/update', {
        current_day: currentDay.value,
        completed_day: currentDay.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Tải lại dữ liệu trang
        }
    });
};

// Chuyển sang ngày kế tiếp
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

// Theo dõi sự thay đổi URL để tự động kích hoạt/cập nhật vị trí
watch(() => page.url, () => {
    nextTick(() => {
        updateTargetPosition();
    });
});

// Lắng nghe trạng thái onboarding của User từ Backend để tự động kích hoạt
onMounted(() => {
    window.addEventListener('resize', updateTargetPosition);
    window.addEventListener('scroll', updateTargetPosition);

    // Kích hoạt tour tự động nếu user đăng nhập lần đầu và chưa hoàn thành Day 1
    // Dùng sessionStorage để tránh auto-start lại mỗi lần reload trang
    setTimeout(() => {
        if (user.value && !isSuperAdmin.value) {
            const status = onboardingStatus.value;

            const wasDismissed = (day: number) =>
                sessionStorage.getItem(`aventura_tour_day${day}_dismissed`) === '1';

            if (!status) {
                if (!wasDismissed(1)) {
startTour(1);
}
            } else {
                const day1 = status.day_1;
                const day2 = status.day_2;
                const day3 = status.day_3;

                if (!day1 || !day1.completed_at) {
                    if (!wasDismissed(1)) {
startTour(1);
}
                } else if ((!day2 || !day2.completed_at) && status.current_day === 2) {
                    if (!wasDismissed(2)) {
startTour(2);
}
                } else if ((!day3 || !day3.completed_at) && status.current_day === 3) {
                    if (!wasDismissed(3)) {
startTour(3);
}
                }
            }
        }
    }, 2000);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateTargetPosition);
    window.removeEventListener('scroll', updateTargetPosition);
    stopTargetPolling();
});

// Xem có đang chạy đúng trang được cấu hình cho Step hay không
const isCorrectPage = computed(() => {
    if (!activeStep.value) {
return false;
}

    const currentUrl = page.url.toLowerCase();
    
    if (activeStep.value.page === 'dashboard') {
        return currentUrl.includes('/dashboard');
    }

    return currentUrl.includes('/' + activeStep.value.page);
});

// Điều hướng nhanh đến trang đúng nếu khách hàng đi lạc
const navigateToStepPage = () => {
    if (!activeStep.value) {
return;
}

    const dest = activeStep.value.page === 'dashboard' ? '/dashboard' : '/' + activeStep.value.page;
    router.visit(dest);
};

// Phục vụ việc reset tour thủ công từ bên ngoài (ví dụ từ Support Page)
defineExpose({
    startTour
});
</script>

<template>
    <div>
        <!-- Backdrop đè sáng đè lên phần tử được chọn -->
        <div
            v-if="isTourActive && targetRect && isCorrectPage"
            class="pointer-events-none fixed inset-0 transition-opacity duration-300"
            style="z-index: 9998;"
            :style="{
                background: `radial-gradient(circle 85px at ${targetRect.left + targetRect.width / 2}px ${targetRect.top + targetRect.height / 2}px, transparent 100%, rgba(15, 23, 42, 0.45) 100%)`
            }"
        />

        <!-- Pulse Highlight đè trực tiếp lên phần tử mục tiêu -->
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

            <!-- Cảnh báo đi sai trang -->
            <div v-if="!isCorrectPage" class="rounded-lg bg-amber-50 dark:bg-amber-950/40 p-3 text-xs text-amber-800 dark:text-amber-300 border border-amber-200/50">
                <p class="font-medium">Bạn đã chuyển trang!</p>
                <p class="mt-1">Để tiếp tục phần hướng dẫn, vui lòng nhấp vào nút bên dưới để quay lại đúng trang.</p>
                <button
                    @click="navigateToStepPage"
                    class="mt-2.5 flex items-center gap-1 font-semibold text-amber-900 dark:text-amber-200 hover:underline"
                >
                    Đến trang hướng dẫn <ChevronRight class="size-3" />
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
                    {{ activeStepIndex === currentSteps.length - 1 ? 'Hoàn thành' : 'Tiếp tục' }}
                    <ArrowRight class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Success Modal (Hoàn thành cả ngày) -->
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
                        Chúc mừng bạn đã hoàn thành Ngày {{ currentDay }}! 🎉
                    </h2>
                    <p class="mt-2.5 text-sm text-slate-500 dark:text-slate-400 leading-relaxed px-2">
                        <span v-if="currentDay === 1">
                            Tuyệt vời! Bạn đã nắm vững các bước tạo nhóm thực đơn và thêm món ăn thực tế. Cửa hàng của bạn đã sẵn sàng bán sản phẩm đầu tiên!
                        </span>
                        <span v-else-if="currentDay === 2">
                            Xuất sắc! Việc thiết lập công thức và định lượng nguyên liệu sẽ giúp phần mềm tự động tính toán tồn kho của bạn chính xác sau mỗi hóa đơn bán hàng.
                        </span>
                        <span v-else>
                            Hoàn hảo! Bạn đã hoàn tất chuỗi Guided Tours chuẩn hóa F&B. Bạn hiện đã làm chủ được nhân sự, quản lý lịch làm việc và kho nguyên liệu!
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
                        Tiến đến Ngày {{ currentDay + 1 }}
                        <ChevronRight class="size-4" />
                    </button>
                    <button
                        @click="isSuccessOpen = false"
                        class="w-full inline-flex items-center justify-center rounded-xl border border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/60 text-slate-600 dark:text-slate-300 font-semibold text-sm py-3 transition-colors"
                    >
                        Để sau / Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>



