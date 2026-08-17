<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    ChevronDown,
    ClipboardCheck,
    Gift,
    LayoutGrid,
    Package,
    Search,
    Settings,
    ShieldCheck,
    ShoppingCart,
    UtensilsCrossed,
    Users,
    Wallet,
    X,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = withDefaults(
    defineProps<{
        items: NavItem[];
        collapsibleGroups?: boolean;
        enableSearch?: boolean;
    }>(),
    {
        collapsibleGroups: false,
        enableSearch: false,
    },
);

const { currentUrl, isCurrentUrl } = useCurrentUrl();
const { state } = useSidebar();
const isSidebarCollapsed = computed(() => state.value === 'collapsed');
const searchQuery = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

// Nhóm định nghĩa phân loại tính năng. Các menu mới có thể truyền `section`
// để được xếp nhóm chính xác; bộ lọc theo tiêu đề bên dưới chỉ là cơ chế dự phòng
// cho các vai trò chưa khai báo metadata.
const groupDefinitions: {
    key: string;
    label: string;
    icon: LucideIcon;
    matches: (title: string, href: string) => boolean;
}[] = [
    {
        key: 'overview',
        label: 'Tổng quan & Phân tích',
        icon: BarChart3,
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
        icon: ShoppingCart,
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
        icon: UtensilsCrossed,
        matches: (title) =>
            ['thực đơn', 'món', 'menu', 'niêm yết', 'bếp'].some((p) =>
                title.toLowerCase().includes(p.toLowerCase()),
            ),
    },
    {
        key: 'supply',
        label: 'Kho & Cung ứng',
        icon: Package,
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
        icon: Wallet,
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
        icon: Users,
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
        icon: Gift,
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
        icon: ClipboardCheck,
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
        icon: ShieldCheck,
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
        icon: Settings,
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

const shouldGroup = computed(() => props.items.length > 8);

type NavigationGroup = {
    key: string;
    label: string;
    icon: LucideIcon;
    items: NavItem[];
};

const expandedGroupKeys = ref<Set<string>>(new Set());

const toggleGroup = (groupKey: string) => {
    const nextExpandedGroupKeys = new Set(expandedGroupKeys.value);

    if (nextExpandedGroupKeys.has(groupKey)) {
        nextExpandedGroupKeys.delete(groupKey);
    } else {
        nextExpandedGroupKeys.add(groupKey);
    }

    expandedGroupKeys.value = nextExpandedGroupKeys;
};

const isGroupExpanded = (group: NavigationGroup) =>
    searchQuery.value.trim().length > 0 ||
    expandedGroupKeys.value.has(group.key);

const isGroupActive = (group: NavigationGroup) =>
    group.items.some((item) => isCurrentUrl(item.href));

const isGroupContentVisible = (group: NavigationGroup) =>
    !props.collapsibleGroups ||
    isSidebarCollapsed.value ||
    isGroupExpanded(group);

const normalizedSearchQuery = computed(() =>
    searchQuery.value.trim().toLocaleLowerCase('vi-VN'),
);

const filteredItems = computed(() => {
    if (!normalizedSearchQuery.value) {
        return props.items;
    }

    return props.items.filter((item) =>
        `${item.title} ${String(item.href)}`
            .toLocaleLowerCase('vi-VN')
            .includes(normalizedSearchQuery.value),
    );
});

const groupedSections = computed(() => {
    if (!shouldGroup.value) {
        return [];
    }

    const sections: NavigationGroup[] = groupDefinitions.map((def) => ({
        key: def.key,
        label: def.label,
        icon: def.icon,
        items: [],
    }));

    const unmatchedSection: NavigationGroup = {
        key: 'other',
        label: 'Chức năng khác',
        icon: LayoutGrid,
        items: [],
    };

    filteredItems.value.forEach((item) => {
        const matchedSection = item.section
            ? groupDefinitions.find((def) => def.key === item.section)
            : groupDefinitions.find((def) =>
                  def.matches(item.title, String(item.href)),
              );

        if (matchedSection) {
            const section = sections.find(
                (candidate) => candidate.key === matchedSection.key,
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

const clearSearch = () => {
    searchQuery.value = '';
    searchInput.value?.focus();
};

const handleGlobalKeydown = (event: KeyboardEvent) => {
    if (
        !props.enableSearch ||
        !(event.metaKey || event.ctrlKey) ||
        event.key.toLowerCase() !== 'k'
    ) {
        return;
    }

    event.preventDefault();
    searchInput.value?.focus();
};

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown));
onBeforeUnmount(() =>
    window.removeEventListener('keydown', handleGlobalKeydown),
);

watch(
    currentUrl,
    () => {
        if (!props.collapsibleGroups) {
            return;
        }

        const activeGroup = groupedSections.value.find(isGroupActive);

        if (!activeGroup || expandedGroupKeys.value.has(activeGroup.key)) {
            return;
        }

        expandedGroupKeys.value = new Set([
            ...expandedGroupKeys.value,
            activeGroup.key,
        ]);
    },
    { immediate: true },
);
</script>

<template>
    <div
        v-if="props.enableSearch"
        class="px-3 pb-2 group-data-[collapsible=icon]:hidden"
    >
        <div
            class="flex h-10 items-center gap-2 rounded-xl border border-sidebar-border/70 bg-sidebar-accent/30 px-3 text-sidebar-foreground/60 transition-colors focus-within:border-primary/50 focus-within:bg-sidebar-accent/50"
        >
            <Search class="size-4 shrink-0" aria-hidden="true" />
            <input
                ref="searchInput"
                v-model="searchQuery"
                type="search"
                placeholder="Tìm kiếm..."
                aria-label="Tìm kiếm chức năng"
                class="min-w-0 flex-1 bg-transparent text-sm text-sidebar-foreground outline-none placeholder:text-sidebar-foreground/40"
                @keydown.esc="clearSearch"
            />
            <button
                v-if="searchQuery"
                type="button"
                aria-label="Xóa tìm kiếm"
                class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-sidebar-foreground/50 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground"
                @click="clearSearch"
            >
                <X class="size-3.5" aria-hidden="true" />
            </button>
            <kbd
                v-else
                class="hidden shrink-0 rounded-md border border-sidebar-border/80 px-1.5 py-0.5 text-[10px] font-medium text-sidebar-foreground/45 sm:inline-flex"
            >
                Ctrl K
            </kbd>
        </div>
    </div>

    <template v-if="shouldGroup">
        <SidebarGroup
            v-for="group in groupedSections"
            :key="group.key"
            class="px-2 py-1 select-none"
        >
            <SidebarGroupLabel
                :as="props.collapsibleGroups ? 'button' : 'div'"
                :type="props.collapsibleGroups ? 'button' : undefined"
                :aria-expanded="
                    props.collapsibleGroups ? isGroupExpanded(group) : undefined
                "
                :aria-controls="
                    props.collapsibleGroups
                        ? `sidebar-group-${group.key}`
                        : undefined
                "
                :aria-label="props.collapsibleGroups ? group.label : undefined"
                :class="[
                    'group/header relative flex h-11 w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-semibold tracking-[0.02em] text-sidebar-foreground/75 transition-all duration-200',
                    props.collapsibleGroups
                        ? 'cursor-pointer hover:bg-sidebar-accent/60 hover:text-sidebar-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring'
                        : 'text-slate-450 dark:text-slate-500',
                    isGroupActive(group)
                        ? 'bg-primary/10 text-primary ring-1 ring-primary/20'
                        : '',
                    isGroupExpanded(group) && !isGroupActive(group)
                        ? 'bg-sidebar-accent/40 text-sidebar-foreground'
                        : '',
                ]"
                @click="props.collapsibleGroups && toggleGroup(group.key)"
            >
                <span
                    v-if="isGroupActive(group)"
                    class="absolute top-2 bottom-2 left-0 w-0.5 rounded-r-full bg-primary"
                    aria-hidden="true"
                />
                <component
                    :is="group.icon"
                    class="size-[18px] shrink-0 opacity-75 transition-opacity duration-200"
                    :class="isGroupActive(group) ? 'opacity-100' : ''"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 truncate leading-none">
                    {{ group.label }}
                </span>
                <ChevronDown
                    v-if="props.collapsibleGroups"
                    class="size-4 shrink-0 opacity-60 transition-transform duration-200"
                    :class="
                        isGroupExpanded(group) ? 'rotate-180 opacity-100' : ''
                    "
                    aria-hidden="true"
                />
            </SidebarGroupLabel>
            <SidebarMenu
                :id="`sidebar-group-${group.key}`"
                :class="[
                    isGroupContentVisible(group) ? 'mt-1.5' : '',
                    props.collapsibleGroups
                        ? 'ml-2 border-l border-sidebar-border/60 pl-2'
                        : '',
                ]"
                v-show="isGroupContentVisible(group)"
            >
                <SidebarMenuItem v-for="item in group.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                        class="group relative h-9 rounded-lg text-[13px] transition-all duration-200 hover:translate-x-0.5 hover:bg-sidebar-accent/70 active:translate-x-0 data-[active=true]:bg-sidebar-accent/80 data-[active=true]:text-primary"
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
                            class="relative flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 pl-3"
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
                                class="size-4 shrink-0 transition-transform duration-300 ease-out group-hover:scale-110 group-hover:rotate-6 group-active:scale-90"
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
        <div
            v-if="
                props.enableSearch &&
                searchQuery.trim() &&
                groupedSections.length === 0
            "
            class="mx-3 mt-3 rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 px-3 py-4 text-center text-xs text-sidebar-foreground/55 group-data-[collapsible=icon]:hidden"
        >
            Không tìm thấy chức năng phù hợp.
        </div>
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
