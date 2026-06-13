<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Megaphone, X } from 'lucide-vue-next';

interface CampaignBroadcastData {
    id: number;
    title: string;
    content: string;
    target_type: string; // 'all', 'plan', 'trial'
    target_plan_id: number | null;
    target_role: string; // 'owner', 'all_staff'
    sent_at: string;
}

const page = usePage();
const activeCampaign = ref<CampaignBroadcastData | null>(null);
const isVisible = ref(false);

const user = computed(() => (page.props.auth?.user as any) ?? null);
const roles = computed(() => {
    const raw = page.props.roles ?? [];
    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});
const tenant = computed(() => (page.props as any).tenant ?? null);

const isSuperAdmin = computed(() => roles.value.includes('super_admin') || roles.value.includes('admin'));

// Plays a premium double-beeping synthesizer sound
function playNotificationChime() {
    try {
        const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
        if (!AudioContextClass) return;
        
        const ctx = new AudioContextClass();
        
        // First beep
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        gain1.gain.setValueAtTime(0.1, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start();
        osc1.stop(ctx.currentTime + 0.3);
        
        // Second beep (slightly offset)
        setTimeout(() => {
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880, ctx.currentTime); // A5
            gain2.gain.setValueAtTime(0.1, ctx.currentTime);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start();
            osc2.stop(ctx.currentTime + 0.4);
        }, 120);
    } catch (e) {
        console.warn('Audio chime could not be played:', e);
    }
}

function handleBroadcast(data: CampaignBroadcastData) {
    if (!user.value) return; // Ignore if not logged in

    // Superadmin always sees campaign alerts for testing and monitoring
    if (isSuperAdmin.value) {
        triggerNotification(data);
        return;
    }

    // Check targeting group
    let matchesGroup = false;
    if (data.target_type === 'all') {
        matchesGroup = true;
    } else if (data.target_type === 'plan' && tenant.value) {
        matchesGroup = tenant.value.plan_id == data.target_plan_id;
    } else if (data.target_type === 'trial' && tenant.value) {
        if (tenant.value.trial_ends_at) {
            matchesGroup = new Date(tenant.value.trial_ends_at) >= new Date();
        }
    }

    // Check targeting role
    let matchesRole = false;
    if (data.target_role === 'all_staff') {
        matchesRole = true;
    } else if (data.target_role === 'owner') {
        matchesRole = roles.value.includes('owner');
    }

    if (matchesGroup && matchesRole) {
        triggerNotification(data);
    }
}

function triggerNotification(data: CampaignBroadcastData) {
    activeCampaign.value = data;
    isVisible.value = true;
    playNotificationChime();

    // Auto dismiss after 15 seconds
    setTimeout(() => {
        if (activeCampaign.value?.id === data.id) {
            closeNotification();
        }
    }, 15000);
}

function closeNotification() {
    isVisible.value = false;
    setTimeout(() => {
        activeCampaign.value = null;
    }, 300);
}

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('global.campaigns')
            .listen('.campaign.broadcasted', (e: CampaignBroadcastData) => {
                handleBroadcast(e);
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel('global.campaigns');
    }
});
</script>

<template>
    <Transition name="slide-fade">
        <div 
            v-if="isVisible && activeCampaign"
            class="fixed bottom-4 right-4 z-50 max-w-sm w-full bg-slate-900/95 dark:bg-slate-950/95 text-white border border-indigo-500/40 rounded-xl shadow-2xl shadow-indigo-500/10 overflow-hidden backdrop-blur"
        >
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Megaphone class="size-4 shrink-0 animate-bounce text-amber-300" />
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-100">Thông báo hệ thống</span>
                </div>
                <button 
                    @click="closeNotification"
                    class="text-indigo-200 hover:text-white hover:bg-white/10 p-1 rounded-md transition-colors"
                >
                    <X class="size-4" />
                </button>
            </div>
            
            <div class="p-4">
                <h4 class="font-bold text-base text-slate-100 mb-1 leading-snug">
                    {{ activeCampaign.title }}
                </h4>
                <p class="text-xs text-slate-300 line-clamp-4 whitespace-pre-wrap leading-relaxed">
                    {{ activeCampaign.content }}
                </p>
                
                <div class="mt-3 flex justify-end">
                    <button 
                        @click="closeNotification"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-all shadow-md cursor-pointer"
                    >
                        Đã hiểu
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.slide-fade-enter-active {
    transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
    transition: all 0.2s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateY(20px) scale(0.95);
    opacity: 0;
}
</style>
