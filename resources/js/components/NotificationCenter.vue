<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Bell,
    X,
    CheckCircle2,
    AlertTriangle,
    Info,
    ShoppingCart,
    Package,
    Zap,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';

// ──────────────────────────────────────────────────────────────────────────────
// Types
// ──────────────────────────────────────────────────────────────────────────────
type NotifType =
    | 'success'
    | 'error'
    | 'warning'
    | 'info'
    | 'order'
    | 'stock'
    | 'flash_promo';

interface Notification {
    id: number;
    serverId?: string;
    type: NotifType;
    title: string;
    message: string;
    time: string;
    read: boolean;
    href?: string;
}

// ──────────────────────────────────────────────────────────────────────────────
// State
// ──────────────────────────────────────────────────────────────────────────────
const page = usePage();
const isOpen = ref(false);
const items = ref<Notification[]>([]);
let nextId = 0;

const unread = computed(() => items.value.filter((n) => !n.read).length);
const hasUnread = computed(() => unread.value > 0);
let notificationPollTimer: ReturnType<typeof setInterval> | null = null;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────
function formatRelative(date: Date): string {
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);

    if (diff < 60) {
        return 'Vừa xong';
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)} phút trước`;
    }

    if (diff < 86400) {
        return `${Math.floor(diff / 3600)} giờ trước`;
    }

    return `${Math.floor(diff / 86400)} ngày trước`;
}

function addNotification(
    type: NotifType,
    title: string,
    message: string,
    href?: string,
) {
    const id = nextId++;
    items.value.unshift({
        id,
        type,
        title,
        message,
        time: formatRelative(new Date()),
        read: false,
        href,
    });

    // Giữ tối đa 30 thông báo
    if (items.value.length > 30) {
        items.value = items.value.slice(0, 30);
    }
}

function markRead(id: number) {
    const notif = items.value.find((n) => n.id === id);

    if (notif) {
        notif.read = true;

        if (notif.serverId) {
            void axios
                .post(`/notifications/${notif.serverId}/read`)
                .catch(() => undefined);
        }
    }
}

function openNotification(id: number) {
    const notif = items.value.find((n) => n.id === id);
    markRead(id);

    if (
        notif?.href &&
        notif.href !== '/notifications' &&
        notif.href !== '#' &&
        notif.href !== ''
    ) {
        isOpen.value = false;
        router.visit(notif.href);
    }
}

function handleBellClick() {
    const auditIssue = items.value.find(
        (notification) =>
            !notification.read && notification.href === '/audit-logs',
    );

    if (auditIssue) {
        openNotification(auditIssue.id);

        return;
    }

    isOpen.value = !isOpen.value;
}

function markAllRead() {
    const serverIds = items.value
        .filter((notification) => notification.serverId && !notification.read)
        .map((notification) => notification.serverId as string);

    items.value.forEach((n) => (n.read = true));
    serverIds.forEach((serverId) => {
        void axios
            .post(`/notifications/${serverId}/read`)
            .catch(() => undefined);
    });
}

function removeNotif(id: number) {
    items.value = items.value.filter((n) => n.id !== id);
}

function clearAll() {
    markAllRead();
    items.value = [];
}

async function loadDatabaseNotifications() {
    if (document.hidden) {
        return;
    }

    try {
        const response = await axios.get('/notifications');
        const notifications = response.data?.notifications ?? [];

        notifications.reverse().forEach((notification: any) => {
            if (
                items.value.some(
                    (item) => item.serverId === String(notification.id),
                )
            ) {
                return;
            }

            let notifType: NotifType = 'info';

            if (
                notification.type === 'inventory_product_sold_out' ||
                notification.type === 'kitchen_menu_unavailable'
            ) {
                notifType = 'stock';
            } else if (notification.type === 'order') {
                notifType = 'order';
            } else if (
                notification.action === 'warning' ||
                notification.type === 'warning'
            ) {
                notifType = 'warning';
            } else if (
                notification.action === 'error' ||
                notification.type === 'error'
            ) {
                notifType = 'error';
            } else if (
                notification.action === 'success' ||
                notification.type === 'success'
            ) {
                notifType = 'success';
            }

            let defaultTitle = 'Thông báo hệ thống';

            if (notification.type === 'shift_swap') {
                defaultTitle = 'Đổi ca trực';
            } else if (notification.type === 'schedule') {
                defaultTitle = 'Lịch làm việc';
            } else if (notification.type === 'inventory_product_sold_out') {
                defaultTitle = 'Món đã hết';
            } else if (notification.type === 'kitchen_menu_unavailable') {
                defaultTitle = 'Nguyên liệu tạm hết';
            }

            let targetUrl = notification.url;
            if (
                notification.type === 'supply_request_created' ||
                notification.title?.includes('Yêu cầu cấp hàng') ||
                targetUrl === '/inventory/central-warehouse'
            ) {
                targetUrl = targetUrl?.includes('/inventory/central-warehouse/requests')
                    ? targetUrl
                    : '/inventory/central-warehouse/requests';
            }

            addNotification(
                notifType,
                notification.title || defaultTitle,
                notification.message || '',
                targetUrl && targetUrl !== '/notifications'
                    ? targetUrl
                    : undefined,
            );
            const created = items.value[0];

            if (created) {
                created.serverId = String(notification.id);
                created.read = Boolean(notification.read_at);

                if (notification.created_at) {
                    created.time = notification.created_at;
                }
            }
        });
    } catch {
        // Tránh log lỗi console liên tục khi mất mạng
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Watch flash props từ Inertia (server-side flash messages)
// ──────────────────────────────────────────────────────────────────────────────
import { watch } from 'vue';

let lastNotifFlashMsg = '';

watch(
    () => (page.props as any).flash,
    (flash) => {
        const currentMsg =
            flash?.success ||
            flash?.error ||
            flash?.info ||
            flash?.warning ||
            '';

        if (!currentMsg) {
            lastNotifFlashMsg = '';

            return;
        }

        if (currentMsg === lastNotifFlashMsg) {
            return;
        }

        lastNotifFlashMsg = currentMsg;

        if (flash?.success) {
            addNotification('success', 'Thành công', flash.success);
        }

        if (flash?.error) {
            addNotification('error', 'Lỗi', flash.error);
        }

        if (flash?.info) {
            addNotification('info', 'Thông tin', flash.info);
        }

        if (flash?.warning) {
            addNotification('warning', 'Cảnh báo', flash.warning);
        }
    },
    { deep: true },
);

// ──────────────────────────────────────────────────────────────────────────────
// Đóng khi click bên ngoài
// ──────────────────────────────────────────────────────────────────────────────
const panelRef = ref<HTMLElement | null>(null);

function handleRealtimeStaffCall(event: Event) {
    const payload = (event as CustomEvent).detail ?? {};
    const tableName = payload.table_name || 'Bàn';
    const areaName = payload.area_name ? ` (${payload.area_name})` : '';
    const message = payload.message || 'Khách hàng yêu cầu phục vụ';

    addNotification(
        'info',
        'Yêu cầu phục vụ',
        `${tableName}${areaName}: ${message}`,
    );
}

function onClickOutside(e: MouseEvent) {
    if (panelRef.value && !panelRef.value.contains(e.target as Node)) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', onClickOutside);
    window.addEventListener('staff-called', handleRealtimeStaffCall);
    void loadDatabaseNotifications();
    notificationPollTimer = setInterval(() => {
        void loadDatabaseNotifications();
    }, 30000);

    // Chỉ hiển thị thông báo thật từ server, không tạo dữ liệu mẫu.
    if (false && items.value.length === 0) {
        addNotification(
            'stock',
            'Cảnh báo Tồn kho',
            '⚠️ Hàng tồn kho chi nhánh Quận 1 sắp hết (dưới mức tồn tối thiểu).',
            '/inventory',
        );
        addNotification(
            'error',
            'Đối soát PO chênh lệch',
            '🚨 Đơn PO #102 phát hiện chênh lệch đối soát >10% chờ Owner phê duyệt.',
            '/suppliers',
        );
        addNotification(
            'warning',
            'Sắp đến hạn Công nợ',
            '⏰ Đơn nợ Công nợ Net 7 của NCC Rau Tươi Đà Lạt sẽ đến hạn trong 24 giờ nữa.',
            '/suppliers',
        );
    }
});
onUnmounted(() => {
    document.removeEventListener('mousedown', onClickOutside);
    window.removeEventListener('staff-called', handleRealtimeStaffCall);

    if (notificationPollTimer) {
        clearInterval(notificationPollTimer);
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// Icon mapping
// ──────────────────────────────────────────────────────────────────────────────
const iconMap: Record<NotifType, any> = {
    success: CheckCircle2,
    error: X,
    warning: AlertTriangle,
    info: Info,
    order: ShoppingCart,
    stock: Package,
    flash_promo: Zap,
};

const colorMap: Record<NotifType, string> = {
    success: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-950/40',
    error: 'text-rose-600 bg-rose-100 dark:bg-rose-950/40',
    warning: 'text-amber-600 bg-amber-100 dark:bg-amber-950/40',
    info: 'text-sky-600 bg-sky-100 dark:bg-sky-950/40',
    order: 'text-violet-600 bg-violet-100 dark:bg-violet-950/40',
    stock: 'text-orange-600 bg-orange-100 dark:bg-orange-950/40',
    flash_promo: 'text-pink-600 bg-pink-100 dark:bg-pink-950/40',
};

// Expose addNotification globally so other components can use it
defineExpose({ addNotification });
</script>

<template>
    <div ref="panelRef" class="relative">
        <!-- Bell Button -->
        <button
            id="notification-bell-btn"
            class="relative rounded-md p-2 transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="isOpen ? 'bg-muted' : ''"
            aria-label="Thông báo"
            @click="handleBellClick"
        >
            <Bell class="size-4 text-muted-foreground" />

            <!-- Unread badge with pulsating ring animation -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="scale-0 opacity-0"
                enter-to-class="scale-100 opacity-100"
                leave-active-class="transition-all duration-150"
                leave-to-class="scale-0 opacity-0"
            >
                <span
                    v-if="hasUnread"
                    class="absolute top-1 right-1 flex h-4 w-4"
                >
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[9px] font-bold text-white shadow-xs"
                    >
                        {{ unread > 9 ? '9+' : unread }}
                    </span>
                </span>
            </Transition>

            <!-- Pulse ring khi có thông báo mới -->
            <span
                v-if="hasUnread"
                class="absolute top-1 right-1 h-4 w-4 animate-ping rounded-full bg-rose-400 opacity-40"
            />
        </button>

        <!-- Dropdown Panel -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out origin-top-right"
            enter-from-class="opacity-0 scale-95 translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition-all duration-150 ease-in origin-top-right"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute top-full right-0 z-50 mt-2 w-80 rounded-xl border border-border bg-background shadow-xl ring-1 ring-black/5"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b border-border px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <Bell class="size-4 text-muted-foreground" />
                        <span class="text-sm font-semibold">Thông báo</span>
                        <span
                            v-if="hasUnread"
                            class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                        >
                            {{ unread }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            v-if="hasUnread"
                            class="rounded px-2 py-1 text-[11px] text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            @click="markAllRead"
                        >
                            Đọc hết
                        </button>
                        <button
                            v-if="items.length > 0"
                            class="rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-rose-500"
                            title="Xóa tất cả"
                            @click="clearAll"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- List -->
                <div class="max-h-[380px] overflow-y-auto">
                    <TransitionGroup
                        tag="div"
                        enter-active-class="transition-all duration-200"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-150"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <template v-if="items.length > 0">
                            <div
                                v-for="notif in items"
                                :key="notif.id"
                                class="group relative flex cursor-pointer items-start gap-3 border-b border-border/50 px-4 py-3 transition-colors last:border-0 hover:bg-muted/50"
                                :class="notif.read ? 'opacity-70' : ''"
                                @click="openNotification(notif.id)"
                            >
                                <!-- Icon -->
                                <div
                                    class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                                    :class="colorMap[notif.type]"
                                >
                                    <component
                                        :is="iconMap[notif.type]"
                                        class="size-3.5"
                                    />
                                </div>

                                <!-- Content -->
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <p
                                            class="text-xs leading-snug font-semibold"
                                            :class="
                                                notif.read
                                                    ? ''
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{ notif.title }}
                                        </p>
                                        <div
                                            class="flex shrink-0 items-center gap-1"
                                        >
                                            <span
                                                class="text-[10px] text-muted-foreground"
                                                >{{ notif.time }}</span
                                            >
                                            <!-- Unread dot -->
                                            <span
                                                v-if="!notif.read"
                                                class="h-1.5 w-1.5 rounded-full bg-rose-500"
                                            />
                                        </div>
                                    </div>
                                    <p
                                        class="mt-0.5 text-[11px] leading-snug text-muted-foreground"
                                    >
                                        {{ notif.message }}
                                    </p>
                                </div>

                                <!-- Remove button (visible on hover) -->
                                <button
                                    class="absolute top-2 right-2 rounded p-0.5 text-muted-foreground opacity-0 transition group-hover:opacity-100 hover:bg-muted"
                                    @click.stop="removeNotif(notif.id)"
                                >
                                    <X class="size-3" />
                                </button>
                            </div>
                        </template>

                        <!-- Empty state -->
                        <div
                            v-else
                            key="empty"
                            class="flex flex-col items-center gap-2 py-10 text-muted-foreground"
                        >
                            <Bell class="size-8 opacity-20" />
                            <p class="text-xs">Chưa có thông báo nào</p>
                        </div>
                    </TransitionGroup>
                </div>
            </div>
        </Transition>
    </div>
</template>
