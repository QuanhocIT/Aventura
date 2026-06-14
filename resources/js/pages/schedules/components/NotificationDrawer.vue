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
            notifications.value = notifications.value.filter(n => n.id !== id);
            import('vue-sonner').then(m => m.toast.success('Đã đánh dấu đã đọc.'));
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
            class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 text-slate-650 dark:text-slate-400 cursor-pointer shadow-xs active:scale-95 transition-all"
            title="Thông báo hệ thống"
        >
            <Bell class="size-5" />
            <span 
                v-if="unreadNotificationsCount > 0" 
                class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white border-2 border-white dark:border-slate-900 animate-bounce"
            >
                {{ unreadNotificationsCount }}
            </span>
        </button>
    </div>

    <!-- Notifications Drawer Overlay -->
    <div v-if="showNotificationDrawer" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex justify-end print:hidden" @click="showNotificationDrawer = false">
        <div 
            class="w-full max-w-sm h-full bg-white dark:bg-slate-900 shadow-2xl flex flex-col animate-in slide-in-from-right duration-200"
            @click.stop
        >
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="font-bold text-sm text-slate-850 dark:text-slate-200 flex items-center gap-1.5">
                    <Bell class="size-4 text-indigo-650" />
                    Thông báo hệ thống
                </h3>
                <button @click="showNotificationDrawer = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                    <X class="size-4" />
                </button>
            </div>
            
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                <div v-if="notifications.length > 0" class="space-y-3">
                    <div 
                        v-for="item in notifications" 
                        :key="item.id"
                        class="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-100 dark:border-slate-800/80 hover:border-slate-200 transition-all flex flex-col gap-1.5 text-xs animate-in fade-in"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium text-slate-700 dark:text-slate-350 leading-tight">{{ item.message }}</span>
                            <button 
                                @click="markAsRead(item.id)" 
                                class="text-indigo-600 hover:text-indigo-700 font-bold shrink-0 text-[10px] cursor-pointer"
                                title="Đánh dấu đã đọc"
                            >
                                Đọc
                            </button>
                        </div>
                        <span class="text-[9px] text-slate-450 font-mono">{{ item.created_at }}</span>
                    </div>
                </div>
                <div v-else class="py-24 text-center text-slate-400">
                    <Bell class="size-8 text-slate-300 dark:text-slate-700 mx-auto mb-2" />
                    <p class="text-xs font-semibold">Không có thông báo mới</p>
                    <p class="text-[10px] text-slate-400 mt-1">Hệ thống sẽ cập nhật thông báo khi có thay đổi ca trực.</p>
                </div>
            </div>
        </div>
    </div>
</template>
