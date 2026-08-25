<script setup lang="ts">
import axios from 'axios';
import { Bell, X } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';

// Notifications State
const showNotificationDrawer = ref(false);
const notifications = ref<any[]>([]);

// Notification Methods
const fetchNotifications = async () => {
    try {
        const res = await axios.get('/notifications');

        if (res.data && res.data.success) {
            notifications.value = res.data.notifications;
        }
    } catch (error) {
        console.error('Lỗi khi lấy thông báo:', error);
    }
};

const toggleNotificationDrawer = () => {
    showNotificationDrawer.value = !showNotificationDrawer.value;

    if (showNotificationDrawer.value) {
        fetchNotifications();
    }
};

const markAsRead = async (id: string) => {
    try {
        const res = await axios.post(`/notifications/${id}/read`);

        if (res.data && res.data.success) {
            notifications.value = notifications.value.filter(
                (n) => n.id !== id,
            );
            import('vue-sonner').then((m) =>
                m.toast.success('Đã đánh dấu đã đọc.'),
            );
        }
    } catch (error) {
        console.error('Lỗi khi đánh dấu thông báo đã đọc:', error);
    }
};

const unreadNotificationsCount = computed(() => notifications.value.length);

onMounted(() => {
    fetchNotifications();
});
</script>

<template>
    <!-- Notifications Bell -->
    <div class="relative print:hidden">
        <button
            type="button"
            @click="toggleNotificationDrawer"
            class="text-slate-650 relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-slate-200 bg-white shadow-xs transition-all hover:bg-slate-50 active:scale-95 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
            title="Thông báo hệ thống"
        >
            <Bell class="size-5" />
            <span
                v-if="unreadNotificationsCount > 0"
                class="absolute -top-1.5 -right-1.5 flex h-5 w-5 animate-bounce items-center justify-center rounded-full border-2 border-white bg-rose-500 text-[10px] font-bold text-white dark:border-slate-900"
            >
                {{ unreadNotificationsCount }}
            </span>
        </button>
    </div>

    <!-- Notifications Drawer Overlay -->
    <Teleport to="body">
    <div
        v-if="showNotificationDrawer"
        class="fixed inset-0 z-50 flex justify-end bg-black/40 backdrop-blur-xs print:hidden"
        @click="showNotificationDrawer = false"
    >
        <div
            class="flex h-full w-full max-w-sm animate-in flex-col bg-white shadow-2xl duration-200 slide-in-from-right dark:bg-slate-900"
            @click.stop
        >
            <div class="flex items-center justify-between border-b p-4">
                <h3
                    class="text-slate-850 flex items-center gap-1.5 text-sm font-bold dark:text-slate-200"
                >
                    <Bell class="text-indigo-650 size-4" />
                    Thông báo hệ thống
                </h3>
                <button
                    @click="showNotificationDrawer = false"
                    class="cursor-pointer rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto p-4">
                <div v-if="notifications.length > 0" class="space-y-3">
                    <div
                        v-for="item in notifications"
                        :key="item.id"
                        class="flex animate-in flex-col gap-1.5 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs transition-all fade-in hover:border-slate-200 dark:border-slate-800/80 dark:bg-slate-800/40"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span
                                class="dark:text-slate-350 leading-tight font-medium text-slate-700"
                                >{{ item.message }}</span
                            >
                            <button
                                @click="markAsRead(item.id)"
                                class="shrink-0 cursor-pointer text-[10px] font-bold text-indigo-600 hover:text-indigo-700"
                                title="Đánh dấu đã đọc"
                            >
                                Đọc
                            </button>
                        </div>
                        <span class="text-slate-450 font-mono text-[9px]">{{
                            item.created_at
                        }}</span>
                    </div>
                </div>
                <div v-else class="py-24 text-center text-slate-400">
                    <Bell
                        class="mx-auto mb-2 size-8 text-slate-300 dark:text-slate-700"
                    />
                    <p class="text-xs font-semibold">Không có thông báo mới</p>
                    <p class="mt-1 text-[10px] text-slate-400">
                        Hệ thống sẽ cập nhật thông báo khi có thay đổi ca trực.
                    </p>
                </div>
            </div>
        </div>
    </div>
    </Teleport>
</template>
