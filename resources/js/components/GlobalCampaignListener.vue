<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Megaphone, X } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, computed } from 'vue';

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

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});
const tenant = computed(() => (page.props as any).tenant ?? null);

const isSuperAdmin = computed(() =>
    roles.value.some((role) =>
        ['super_admin', 'system_admin', 'billing_admin', 'support_specialist'].includes(
            String(role),
        ),
    ),
);

const campaignChannels = computed(() => {
    if (isSuperAdmin.value) {
        return ['superadmin.campaigns'];
    }

    const restaurantId = user.value?.restaurant_id ?? tenant.value?.id;

    if (! restaurantId) {
        return [];
    }

    const channels = [`restaurant.${restaurantId}.campaigns.all_staff`];

    if (roles.value.includes('owner')) {
        channels.push(`restaurant.${restaurantId}.campaigns.owner`);
    }

    return channels;
});
let subscribedChannels: string[] = [];

// Plays a premium double-beeping synthesizer sound
function playNotificationChime() {
    try {
        const AudioContextClass =
            window.AudioContext || (window as any).webkitAudioContext;

        if (!AudioContextClass) {
            return;
        }

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
            gain2.gain.exponentialRampToValueAtTime(
                0.001,
                ctx.currentTime + 0.4,
            );
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
    if (!user.value) {
        return;
    } // Ignore if not logged in

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
        matchesGroup = tenant.value.plan?.id == data.target_plan_id;
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
        subscribedChannels = campaignChannels.value;
        subscribedChannels.forEach((channel) => {
            window.Echo.private(channel).listen(
                '.campaign.broadcasted',
                (e: CampaignBroadcastData) => {
                    handleBroadcast(e);
                },
            );
        });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        subscribedChannels.forEach((channel) => {
            window.Echo.leaveChannel(channel);
        });
    }
});
</script>

<template>
    <Transition name="slide-fade">
        <div
            v-if="isVisible && activeCampaign"
            class="fixed right-4 bottom-4 z-50 w-full max-w-sm overflow-hidden rounded-xl border border-indigo-500/40 bg-slate-900/95 text-white shadow-2xl shadow-indigo-500/10 backdrop-blur dark:bg-slate-950/95"
        >
            <div
                class="flex items-center justify-between bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <Megaphone
                        class="size-4 shrink-0 animate-bounce text-amber-300"
                    />
                    <span
                        class="text-xs font-bold tracking-wider text-indigo-100 uppercase"
                        >Thông báo hệ thống</span
                    >
                </div>
                <button
                    @click="closeNotification"
                    class="rounded-md p-1 text-indigo-200 transition-colors hover:bg-white/10 hover:text-white"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="p-4">
                <h4
                    class="mb-1 text-base leading-snug font-bold text-slate-100"
                >
                    {{ activeCampaign.title }}
                </h4>
                <p
                    class="line-clamp-4 text-xs leading-relaxed whitespace-pre-wrap text-slate-300"
                >
                    {{ activeCampaign.content }}
                </p>

                <div class="mt-3 flex justify-end">
                    <button
                        @click="closeNotification"
                        class="cursor-pointer rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow-md transition-all hover:bg-indigo-700"
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
