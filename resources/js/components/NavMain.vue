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

// Nhóm định nghĩa phân loại tính năng. Các menu mới có thể truyền `section`
// để được xếp nhóm chính xác; bộ lọc theo tiêu đề bên dưới chỉ là cơ chế dự phòng
// cho các vai trò chưa khai báo metadata.
const groupDefinitions: {
    key: string;
    label: string;
    matches: (title: string, href: string) => boolean;
}[] = [
    {
        key: 'overview',
        label: 'Tổng quan & Phân tích',
        matches: (title, href) =>
            [
                'Tổng quan',
                'Trang chủ',
                'Dashboard',
                'BI Dashboard',
                'Phân tích địa lý',
                'Báo cáo & AI',
                'Báo cáo doanh thu',
                'Doanh thu hệ thống',
                'Revenue',
                'Mục tiêu & OKR',
                'Dự đoán rời bỏ',
                'Strategic AI Advisor',
                'Trợ lý AI Chiến lược',
                'Trợ lý AI',
                'Chẩn đoán trợ lý AI',
                'Trung tâm điều hành',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())) ||
            href.includes('bi-dashboard') ||
            href.includes('command-center'),
    },
    {
        key: 'sales',
        label: 'Bán hàng & Phục vụ',
        matches: (title) =>
            [
                'quản lý đơn hàng',
                'sơ đồ bàn',
                'phục vụ',
                'giao hàng',
                'đặt hàng online',
                'đặt bàn',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())),
    },
    {
        key: 'menu',
        label: 'Thực đơn & Nhà bếp',
        matches: (title) =>
            ['thực đơn', 'món', 'menu', 'niêm yết', 'bếp'].some((p) =>
                title.toLowerCase().includes(p.toLowerCase()),
            ),
    },
    {
        key: 'supply',
        label: 'Kho & Cung ứng',
        matches: (title) =>
            [
                'kho',
                'tồn',
                'nguyên vật liệu',
                'nhập',
                'hao hụt',
                'lãng phí',
                'nhà cung cấp',
                'rfp',
                'logistics',
                'thu hồi lô',
                'điều chuyển',
                'cấp phát',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())),
    },
    {
        key: 'finance',
        label: 'Tài chính & Đối soát',
        matches: (title) =>
            [
                'dòng tiền',
                'chi phí',
                'công nợ',
                'billing',
                'gói dịch vụ',
                'plans',
                'giảm giá',
                'coupons',
                'mã giảm giá',
                'hoa hồng',
                'referrals',
                'hóa đơn',
                'chốt ca',
                'doanh thu ca',
                'ngân sách',
                'campaign-templates',
                'chiến dịch theo mùa',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())),
    },
    {
        key: 'people',
        label: 'Nhân sự & Hiệu suất',
        matches: (title) =>
            [
                'nhân sự',
                'nhân viên',
                'chấm công',
                'lịch làm',
                'lịch làm việc',
                'bảng lương',
                'lương',
                'đào tạo',
                'kpi',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())),
    },
    {
        key: 'customers',
        label: 'Khách hàng & Tăng trưởng',
        matches: (title) =>
            [
                'khách hàng',
                'thân thiết',
                'khuyến mãi',
                'phản hồi',
                'feedback',
                'quảng bá',
                'campaigns',
                'chiến dịch quảng bá',
                'banners',
                'slideshow',
                'news',
                'tin tức',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())),
    },
    {
        key: 'operations',
        label: 'Vận hành & An toàn',
        matches: (title) =>
            [
                'checklist',
                'thiết bị',
                'bàn giao ca',
                'sự cố',
                'quy định',
                'tiêu chuẩn',
                'thanh tra',
                'biên bản',
                'trung tâm vận hành',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())),
    },
    {
        key: 'governance',
        label: 'Phê duyệt & Kiểm soát',
        matches: (title) =>
            [
                'phân quyền',
                'phê duyệt',
                'đã duyệt',
                'thẩm quyền',
                'kiểm toán',
                'gian lận',
                'vi phạm',
                'tố cáo',
                'nhật ký',
                'audit log',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())),
    },
    {
        key: 'settings',
        label: 'Cài đặt & Hỗ trợ',
        matches: (title) =>
            [
                'chi nhánh',
                'cài đặt',
                'tích hợp',
                'giới thiệu',
                'hoa hồng',
                'tin tức',
                'liên hệ',
                'hỗ trợ',
                'cấu hình',
                'settings',
                'nhà hàng',
                'tài khoản',
                'accounts',
                'devops',
                'chatbot',
                'giám sát',
                'monitor',
                'bảo trì',
                'sao lưu',
                'backup',
                'dọn dẹp',
                'garbage',
                'meilisearch',
                'firewall',
                'tường lửa',
                'trung tâm bảo mật',
            ].some((p) => title.toLowerCase().includes(p.toLowerCase())),
    },
];

// Phân nhóm menu nếu tổng số lượng menu lớn hơn 8 để tránh quá tải thị giác
const shouldGroup = computed(() => props.items.length > 8);

const groupedSections = computed(() => {
    if (!shouldGroup.value) {
        return [];
    }

    const sections: { label: string; items: NavItem[] }[] =
        groupDefinitions.map((def) => ({
            label: def.label,
            items: [],
        }));

    const unmatchedSection: { label: string; items: NavItem[] } = {
        label: 'Chức năng khác',
        items: [],
    };

    props.items.forEach((item) => {
        const matchedSection = item.section
            ? groupDefinitions.find((def) => def.key === item.section)
            : groupDefinitions.find((def) =>
                  def.matches(item.title, String(item.href)),
              );

        if (matchedSection) {
            const section = sections.find(
                (candidate) => candidate.label === matchedSection.label,
            );

            section?.items.push(item);
        } else {
            unmatchedSection.items.push(item);
        }
    });

    if (unmatchedSection.items.length > 0) {
        sections.push(unmatchedSection);
    }

    return sections.filter((sec) => sec.items.length > 0);
});
</script>

<template>
    <template v-if="shouldGroup">
        <SidebarGroup
            v-for="group in groupedSections"
            :key="group.label"
            class="px-2 py-1 select-none"
        >
            <SidebarGroupLabel
                class="text-slate-450 px-2.5 py-1 text-[10px] font-bold tracking-widest uppercase dark:text-slate-500"
            >
                {{ group.label }}
            </SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in group.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                        class="group relative transition-all duration-200 hover:translate-x-0.5 active:translate-x-0"
                    >
                        <Link
                            :id="
                                'sidebar-link-' +
                                item.href
                                    .replace('/', '')
                                    .replace('?', '')
                                    .replace('=', '')
                            "
                            :href="item.href"
                            :prefetch="item.prefetch ?? false"
                            class="relative flex w-full items-center gap-2 pl-3"
                        >
                            <!-- Active left border marker -->
                            <span
                                class="absolute left-0 w-[3px] origin-center rounded-r bg-primary transition-all duration-300"
                                :class="
                                    isCurrentUrl(item.href)
                                        ? 'top-1.5 bottom-1.5 scale-y-100 opacity-100'
                                        : 'top-1/2 bottom-1/2 scale-y-0 opacity-0'
                                "
                            />
                            <component
                                :is="item.icon"
                                class="size-4 shrink-0 transition-transform duration-300 ease-out group-hover:scale-115 group-hover:rotate-6 group-active:scale-90"
                            />
                            <span class="flex-1 font-medium">{{
                                item.title
                            }}</span>
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
            <SidebarGroupLabel
                class="text-slate-450 px-2.5 py-1 text-[10px] font-bold tracking-widest uppercase dark:text-slate-500"
            >
                Quản trị hệ thống
            </SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                        class="group relative transition-all duration-200 hover:translate-x-0.5 active:translate-x-0"
                    >
                        <Link
                            :id="
                                'sidebar-link-' +
                                item.href
                                    .replace('/', '')
                                    .replace('?', '')
                                    .replace('=', '')
                            "
                            :href="item.href"
                            :prefetch="item.prefetch ?? false"
                            class="relative flex w-full items-center gap-2 pl-3"
                        >
                            <!-- Active left border marker -->
                            <span
                                class="absolute left-0 w-[3px] origin-center rounded-r bg-primary transition-all duration-300"
                                :class="
                                    isCurrentUrl(item.href)
                                        ? 'top-1.5 bottom-1.5 scale-y-100 opacity-100'
                                        : 'top-1/2 bottom-1/2 scale-y-0 opacity-0'
                                "
                            />
                            <component
                                :is="item.icon"
                                class="size-4 shrink-0 transition-transform duration-300 ease-out group-hover:scale-115 group-hover:rotate-6 group-active:scale-90"
                            />
                            <span class="flex-1 font-medium">{{
                                item.title
                            }}</span>
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
