<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import { FEATURE_LABELS, type FeatureKey } from '@/composables/useFeatureGate';

const props = defineProps<{
    feature: FeatureKey;
    feature_label: string;
    plan_name: string;
    required_plan: string;
}>();

const PLAN_ORDER = ['Miễn Phí', 'Cơ Bản', 'Chuyên Nghiệp', 'Doanh Nghiệp'];
const PLAN_COLORS: Record<string, string> = {
    'Miễn Phí':      'bg-gray-100 text-gray-600',
    'Cơ Bản':        'bg-blue-100 text-blue-700',
    'Chuyên Nghiệp': 'bg-indigo-100 text-indigo-700',
    'Doanh Nghiệp':  'bg-violet-100 text-violet-700',
};
const PLAN_BADGE: Record<string, string> = {
    'Miễn Phí':      'border-gray-200',
    'Cơ Bản':        'border-blue-300',
    'Chuyên Nghiệp': 'border-indigo-400',
    'Doanh Nghiệp':  'border-violet-500',
};

const featureInfo = FEATURE_LABELS[props.feature] ?? { label: props.feature_label, required_plan: props.required_plan };

const PLAN_FEATURES: Record<string, string[]> = {
    'Miễn Phí': ['POS + Đơn hàng', 'Quản lý Menu', 'Báo cáo cơ bản', '1 chi nhánh, 15 bàn, 5 nhân viên'],
    'Cơ Bản': [
        'Tất cả Miễn Phí',
        'Màn hình Bếp (Kitchen Display)',
        'Đặt món qua QR',
        'Chấm công & Lịch làm việc',
        'Quản lý Tồn kho',
        'Cập nhật thời gian thực',
        '3 chi nhánh, 60 bàn, 20 nhân viên',
    ],
    'Chuyên Nghiệp': [
        'Tất cả Cơ Bản',
        'Phân tích & Báo cáo Nâng cao',
        'Quản lý Lương & Nhân sự đầy đủ',
        'Phát hiện Gian lận',
        'Email Báo cáo Tự động',
        'AI Tư vấn Chiến lược',
        '10 chi nhánh, 200 bàn, 60 nhân viên',
    ],
    'Doanh Nghiệp': [
        'Tất cả Chuyên Nghiệp',
        'Cổng Nhà Cung Cấp (Supplier Portal)',
        'AI Dự báo Tồn kho',
        'Quản lý Thầu & Đấu thầu (RFP)',
        'Truy cập API',
        'Chi nhánh & Bàn không giới hạn',
    ],
};

function goUpgrade() {
    router.visit('/billing/history');
}

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="`Nâng cấp gói — ${featureInfo.label}`" />
    <AppTopbarLayout>
        <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 to-indigo-50 p-4">
            <div class="w-full max-w-2xl">

                <!-- Lock icon + title -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-indigo-100 mb-4">
                        <svg class="w-10 h-10 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900">Tính năng chưa khả dụng</h1>
                    <p class="mt-2 text-gray-500 text-sm">
                        <span class="font-semibold text-gray-700">{{ featureInfo.label }}</span>
                        yêu cầu gói <span class="font-semibold text-indigo-600">{{ featureInfo.required_plan }}</span> trở lên.
                    </p>
                </div>

                <!-- Current plan vs required plan -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div :class="['rounded-xl border-2 p-4', PLAN_BADGE[props.plan_name] ?? 'border-gray-200']">
                        <div class="text-xs text-gray-400 mb-1">Gói hiện tại</div>
                        <div :class="['inline-block px-2 py-0.5 rounded-full text-xs font-semibold', PLAN_COLORS[props.plan_name] ?? 'bg-gray-100 text-gray-600']">
                            {{ props.plan_name }}
                        </div>
                        <ul class="mt-3 space-y-1">
                            <li v-for="f in PLAN_FEATURES[props.plan_name] ?? []" :key="f" class="flex items-center gap-1.5 text-xs text-gray-600">
                                <svg class="w-3.5 h-3.5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                {{ f }}
                            </li>
                        </ul>
                    </div>

                    <div :class="['rounded-xl border-2 p-4 bg-white shadow-sm', PLAN_BADGE[featureInfo.required_plan] ?? 'border-indigo-300']">
                        <div class="text-xs text-indigo-400 mb-1">Gói cần nâng cấp</div>
                        <div :class="['inline-block px-2 py-0.5 rounded-full text-xs font-semibold', PLAN_COLORS[featureInfo.required_plan] ?? 'bg-indigo-100 text-indigo-700']">
                            {{ featureInfo.required_plan }}
                        </div>
                        <ul class="mt-3 space-y-1">
                            <li v-for="f in PLAN_FEATURES[featureInfo.required_plan] ?? []" :key="f" class="flex items-center gap-1.5 text-xs text-gray-700">
                                <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                {{ f }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- CTA -->
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <button
                        @click="goUpgrade"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" />
                        </svg>
                        Nâng cấp ngay
                    </button>
                    <button
                        @click="goBack"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-xl border border-gray-200 transition-colors"
                    >
                        Quay lại
                    </button>
                </div>

            </div>
        </div>
    </AppTopbarLayout>
</template>
