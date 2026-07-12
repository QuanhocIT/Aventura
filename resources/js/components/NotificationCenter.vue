<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
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

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────
function formatRelative(date: Date): string {
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 60) return 'Vừa xong';
    if (diff < 3600) return `${Math.floor(diff / 60)} phút trước`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} giờ trước`;
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
    if (notif) notif.read = true;
}

function markAllRead() {
    items.value.forEach((n) => (n.read = true));
}

function removeNotif(id: number) {
    items.value = items.value.filter((n) => n.id !== id);
}

function clearAll() {
    items.value = [];
}

// ──────────────────────────────────────────────────────────────────────────────
// Watch flash props từ Inertia (server-side flash messages)
// ──────────────────────────────────────────────────────────────────────────────
import { watch } from 'vue';

watch(
    () => (page.props as any).flash,
    (flash) => {
        if (flash?.success)
            addNotification('success', 'Thành công', flash.success);
        if (flash?.error) addNotification('error', 'Lỗi', flash.error);
        if (flash?.info) addNotification('info', 'Thông tin', flash.info);
        if (flash?.warning)
            addNotification('warning', 'Cảnh báo', flash.warning);
    },
    { deep: true },
);

// ──────────────────────────────────────────────────────────────────────────────
// Đóng khi click bên ngoài
// ──────────────────────────────────────────────────────────────────────────────
const panelRef = ref<HTMLElement | null>(null);

function onClickOutside(e: MouseEvent) {
    if (panelRef.value && !panelRef.value.contains(e.target as Node)) {
        isOpen.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));

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
            @click="isOpen = !isOpen"
        >
            <Bell class="size-4 text-muted-foreground" />

            <!-- Unread badge -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="scale-0 opacity-0"
                enter-to-class="scale-100 opacity-100"
                leave-active-class="transition-all duration-150"
                leave-to-class="scale-0 opacity-0"
            >
                <span
                    v-if="hasUnread"
                    class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[9px] font-bold text-white"
                >
                    {{ unread > 9 ? '9+' : unread }}
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
                                @click="markRead(notif.id)"
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
