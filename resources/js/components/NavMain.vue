<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

// Nhóm định nghĩa phân loại tính năng
const groupDefinitions = [
    {
        label: 'Tổng quan & Phân tích',
        matches: (title: string) => [
            'Tổng quan', 'Trang chủ', 'BI Dashboard', 'Phân tích địa lý', 
            'Báo cáo & AI', 'Báo cáo doanh thu', 'Mục tiêu & OKR', 'Audit Log', 
            'Dự đoán rời bỏ', 'Strategic AI Advisor', 'Trợ lý AI Chiến lược'
        ].some(p => title.toLowerCase().includes(p.toLowerCase()))
    },
    {
        label: 'Bán hàng & Phục vụ',
        matches: (title: string) => [
            'đơn hàng', 'đơn', 'sơ đồ bàn', 'phục vụ', 'giao hàng', 'online'
        ].some(p => title.toLowerCase().includes(p.toLowerCase()))
    },
    {
        label: 'Thực đơn & Nhà bếp',
        matches: (title: string) => [
            'thực đơn', 'món', 'menu', 'niêm yết'
        ].some(p => title.toLowerCase().includes(p.toLowerCase()))
    },
    {
        label: 'Kho & Nhà cung cấp',
        matches: (title: string) => [
            'kho', 'tồn', 'nhập', 'hao hụt', 'lãng phí', 'nhà cung cấp', 'rfp', 'chốt ca', 'doanh thu ca'
        ].some(p => title.toLowerCase().includes(p.toLowerCase()))
    },
    {
        label: 'Tài chính & Gói cước',
        matches: (title: string) => [
            'dòng tiền', 'chi phí', 'công nợ', 'billing', 'gói dịch vụ', 'giảm giá', 'hoa hồng', 'hóa đơn'
        ].some(p => title.toLowerCase().includes(p.toLowerCase()))
    },
    {
        label: 'Nhân sự & Ca làm',
        matches: (title: string) => [
            'nhân sự', 'nhân viên', 'chấm công', 'lịch làm', 'lịch làm việc', 'bảng lương', 'lương', 'đào tạo', 'kpi'
        ].some(p => title.toLowerCase().includes(p.toLowerCase()))
    },
    {
        label: 'Khách hàng & Marketing',
        matches: (title: string) => [
            'khách hàng', 'thân thiết', 'khuyến mãi', 'phản hồi', 'quảng bá'
        ].some(p => title.toLowerCase().includes(p.toLowerCase()))
    },
    {
        label: 'Hệ thống & Vận hành',
        matches: (title: string) => [
            'phân quyền', 'checklist', 'thiết bị', 'kiểm toán', 'vi phạm', 'tố cáo', 
            'phê duyệt', 'tin tức', 'hỗ trợ', 'devops', 'chatbot', 'tài khoản', 
            'cấu hình', 'giám sát', 'bảo trì', 'sao lưu', 'dọn dẹp', 'meilisearch', 'banner'
        ].some(p => title.toLowerCase().includes(p.toLowerCase()))
    }
];

// Phân nhóm menu nếu tổng số lượng menu lớn hơn 8 để tránh quá tải thị giác
const shouldGroup = computed(() => props.items.length > 8);

const groupedSections = computed(() => {
    if (!shouldGroup.value) {
        return [];
    }

    const sections: { label: string; items: NavItem[] }[] = groupDefinitions.map(def => ({
        label: def.label,
        items: []
    }));

    const unmatchedSection: { label: string; items: NavItem[] } = { label: 'Chức năng khác', items: [] };

    props.items.forEach(item => {
        let matched = false;

        for (let i = 0; i < groupDefinitions.length; i++) {
            if (groupDefinitions[i].matches(item.title)) {
                sections[i].items.push(item);
                matched = true;
                break;
            }
        }

        if (!matched) {
            unmatchedSection.items.push(item);
        }
    });

    if (unmatchedSection.items.length > 0) {
        sections.unshift(unmatchedSection);
    }

    return sections.filter(sec => sec.items.length > 0);
});
</script>

<template>
    <template v-if="shouldGroup">
        <SidebarGroup v-for="group in groupedSections" :key="group.label" class="px-2 py-1 select-none">
            <SidebarGroupLabel class="text-[10px] font-bold text-slate-450 dark:text-slate-500 uppercase tracking-widest px-2.5 py-1">
                {{ group.label }}
            </SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in group.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                        class="transition-all duration-200 hover:translate-x-0.5 active:translate-x-0 group relative"
                    >
                        <Link :id="'sidebar-link-' + item.href.replace('/', '').replace('?', '').replace('=', '')" :href="item.href" prefetch class="relative w-full flex items-center gap-2 pl-3">
                            <!-- Active left border marker -->
                            <span 
                                v-if="isCurrentUrl(item.href)" 
                                class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r bg-primary animate-fade-in"
                            />
                            <component :is="item.icon" class="size-4 shrink-0 transition-transform duration-250 group-hover:scale-110" />
                            <span class="flex-1 font-medium">{{ item.title }}</span>
                            <span
                                v-if="item.badge && item.badge > 0"
                                class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-slate-900"
                            >
                                {{ item.badge > 99 ? '99+' : item.badge }}
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </template>
    <template v-else>
        <SidebarGroup class="px-2 py-0 select-none">
            <SidebarGroupLabel class="text-[10px] font-bold text-slate-450 dark:text-slate-500 uppercase tracking-widest px-2.5 py-1">
                Quản trị hệ thống
            </SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                        class="transition-all duration-200 hover:translate-x-0.5 active:translate-x-0 group relative"
                    >
                        <Link :id="'sidebar-link-' + item.href.replace('/', '').replace('?', '').replace('=', '')" :href="item.href" prefetch class="relative w-full flex items-center gap-2 pl-3">
                            <!-- Active left border marker -->
                            <span 
                                v-if="isCurrentUrl(item.href)" 
                                class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r bg-primary animate-fade-in"
                            />
                            <component :is="item.icon" class="size-4 shrink-0 transition-transform duration-250 group-hover:scale-110" />
                            <span class="flex-1 font-medium">{{ item.title }}</span>
                            <span
                                v-if="item.badge && item.badge > 0"
                                class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-slate-900"
                            >
                                {{ item.badge > 99 ? '99+' : item.badge }}
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </template>
</template>
