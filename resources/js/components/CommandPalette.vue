<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Search,
    Building,
    User,
    FileText,
    HelpCircle,
    Terminal,
    Eye,
    Percent,
    Wrench,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch } from 'vue';

function customDebounce<T extends (...args: any[]) => any>(
    fn: T,
    delay: number,
) {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    return function (...args: Parameters<T>) {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => {
            fn(...args);
        }, delay);
    };
}

const isOpen = ref(false);
const query = ref('');
const results = ref<{
    restaurants: Array<any>;
    users: Array<any>;
    tickets: Array<any>;
    invoices: Array<any>;
}>({
    restaurants: [],
    users: [],
    tickets: [],
    invoices: [],
});

const quickActions = ref([
    {
        label: 'Sắm vai Chủ nhà hàng (> imp [tên/email])',
        cmd: '> imp',
        icon: Eye,
    },
    {
        label: 'Tạo / Quản lý Coupon (> coupon [mã])',
        cmd: '> coupon',
        icon: Percent,
    },
    {
        label: 'Mở ticket hỗ trợ (> ticket #[số])',
        cmd: '> ticket',
        icon: HelpCircle,
    },
    {
        label: 'Quản lý lịch bảo trì (> maintenance)',
        cmd: '> maintenance',
        icon: Wrench,
    },
]);

const loading = ref(false);
const activeIndex = ref(0);
const allVisibleItems = ref<Array<any>>([]);

// Fetch results from controller
const performSearch = customDebounce(async (val: string) => {
    if (val.trim().startsWith('>')) {
        results.value = {
            restaurants: [],
            users: [],
            tickets: [],
            invoices: [],
        };
        loading.value = false;
        allVisibleItems.value = filterQuickActions(val);
        activeIndex.value = 0;

        return;
    }

    if (val.trim().length < 2) {
        results.value = {
            restaurants: [],
            users: [],
            tickets: [],
            invoices: [],
        };
        loading.value = false;
        allVisibleItems.value = [];

        return;
    }

    loading.value = true;

    try {
        const response = await fetch(
            `/super-admin/global-search?q=${encodeURIComponent(val)}`,
        );
        const data = await response.json();
        results.value = data;

        // Flatten for keyboard navigation
        const flat: Array<any> = [];
        data.restaurants.forEach((r: any) =>
            flat.push({ ...r, type: 'restaurant', icon: Building }),
        );
        data.users.forEach((u: any) =>
            flat.push({ ...u, type: 'user', icon: User }),
        );
        data.tickets.forEach((t: any) =>
            flat.push({ ...t, type: 'ticket', icon: HelpCircle }),
        );
        data.invoices.forEach((i: any) =>
            flat.push({ ...i, type: 'invoice', icon: FileText }),
        );
        allVisibleItems.value = flat;
        activeIndex.value = 0;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}, 300);

watch(query, (newVal) => {
    performSearch(newVal);
});

function filterQuickActions(val: string) {
    const term = val.trim().toLowerCase();

    return quickActions.value
        .filter((act) => act.cmd.startsWith(term) || term.startsWith(act.cmd))
        .map((act) => ({
            ...act,
            type: 'action',
            name: act.label,
            url: getActionUrl(act.cmd, val),
        }));
}

function getActionUrl(cmd: string, val: string): string {
    const arg = val.replace(cmd, '').trim();

    if (cmd === '> imp') {
        return `/super-admin/accounts?search=${encodeURIComponent(arg)}`;
    }

    if (cmd === '> coupon') {
        return `/super-admin/coupons?search=${encodeURIComponent(arg)}`;
    }

    if (cmd === '> ticket') {
        return `/super-admin/support?search=${encodeURIComponent(arg)}`;
    }

    if (cmd === '> maintenance') {
        return '/super-admin/maintenance-schedules';
    }

    return '#';
}

function executeAction(item: any) {
    if (item.type === 'action' && item.cmd === '> imp') {
        // Redirect to accounts page with search query filled
        router.visit(item.url);
        isOpen.value = false;

        return;
    }

    if (item.url && item.url !== '#') {
        router.visit(item.url);
        isOpen.value = false;
    }
}

// Handle Key Events
function handleKeyDown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        isOpen.value = !isOpen.value;

        if (isOpen.value) {
            query.value = '';
            results.value = {
                restaurants: [],
                users: [],
                tickets: [],
                invoices: [],
            };
            allVisibleItems.value = [];
        }
    }

    if (!isOpen.value) {
        return;
    }

    if (e.key === 'Escape') {
        isOpen.value = false;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex.value =
            (activeIndex.value + 1) % (allVisibleItems.value.length || 1);
    }

    if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value =
            (activeIndex.value - 1 + allVisibleItems.value.length) %
            (allVisibleItems.value.length || 1);
    }

    if (e.key === 'Enter') {
        e.preventDefault();
        const selected = allVisibleItems.value[activeIndex.value];

        if (selected) {
            executeAction(selected);
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <!-- Modal Backdrop -->
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-start justify-center bg-black/60 pt-[15vh] backdrop-blur-sm"
        @click="isOpen = false"
    >
        <!-- Palette Body -->
        <div
            class="flex max-h-[50vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-border/70 bg-background/95 shadow-2xl backdrop-blur"
            @click.stop
        >
            <!-- Input box -->
            <div
                class="flex shrink-0 items-center gap-3 border-b border-border/70 px-4 py-3"
            >
                <Search class="size-5 shrink-0 text-muted-foreground" />
                <input
                    type="text"
                    v-model="query"
                    placeholder="Tìm kiếm nhà hàng, email, hóa đơn hoặc gõ lệnh nhanh (> imp, > ticket...)..."
                    class="w-full border-none bg-transparent text-sm text-foreground placeholder-muted-foreground outline-none focus:ring-0"
                    ref="searchInput"
                    v-focus
                    @keydown.esc="isOpen = false"
                />
                <span
                    class="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold text-muted-foreground"
                    >ESC</span
                >
            </div>

            <!-- Loader -->
            <div
                v-if="loading"
                class="shrink-0 py-12 text-center text-sm text-muted-foreground"
            >
                Đang tìm kiếm dữ liệu...
            </div>

            <!-- Results feed -->
            <div v-else class="flex-1 space-y-4 overflow-y-auto p-2">
                <!-- If query is empty, show default quick actions -->
                <div v-if="!query" class="space-y-1.5">
                    <p
                        class="mb-1.5 px-2.5 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Lệnh nhanh SuperAdmin
                    </p>
                    <div
                        v-for="act in quickActions"
                        :key="act.cmd"
                        @click="query = act.cmd + ' '"
                        class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-foreground transition-all hover:bg-muted"
                    >
                        <component
                            :is="act.icon"
                            class="size-4.5 shrink-0 text-muted-foreground"
                        />
                        <span class="font-medium">{{ act.label }}</span>
                    </div>
                </div>

                <!-- If searching, show lists -->
                <div v-else-if="allVisibleItems.length > 0" class="space-y-1">
                    <div
                        v-for="(item, idx) in allVisibleItems"
                        :key="item.id || item.cmd"
                        @click="executeAction(item)"
                        :class="[
                            'flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-all',
                            idx === activeIndex
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'text-foreground hover:bg-muted',
                        ]"
                    >
                        <component
                            :is="item.icon || Terminal"
                            :class="[
                                'size-4.5 shrink-0',
                                idx === activeIndex
                                    ? 'text-white'
                                    : 'text-muted-foreground',
                            ]"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{
                                    item.name ||
                                    item.subject ||
                                    item.invoice_number
                                }}
                            </p>
                            <p
                                :class="[
                                    'truncate text-xs',
                                    idx === activeIndex
                                        ? 'text-indigo-200'
                                        : 'text-muted-foreground',
                                ]"
                            >
                                <span v-if="item.type === 'restaurant'"
                                    >Mã: {{ item.code }} · Trạng thái:
                                    {{ item.status }}</span
                                >
                                <span v-else-if="item.type === 'user'"
                                    >Email: {{ item.email }}</span
                                >
                                <span v-else-if="item.type === 'ticket'"
                                    >Mã vé: {{ item.ticket_number }} · Trạng
                                    thái: {{ item.status }}</span
                                >
                                <span v-else-if="item.type === 'invoice'"
                                    >Tổng tiền: {{ item.total }} VND · Trạng
                                    thái: {{ item.status }}</span
                                >
                                <span v-else-if="item.type === 'action'"
                                    >Lệnh nhanh SuperAdmin</span
                                >
                            </p>
                        </div>
                        <span
                            v-if="idx === activeIndex"
                            class="shrink-0 text-[10px] font-bold tracking-wider text-white/80 uppercase"
                            >Enter ↩</span
                        >
                    </div>
                </div>

                <!-- No results -->
                <div
                    v-else
                    class="shrink-0 py-12 text-center text-sm text-muted-foreground"
                >
                    Không tìm thấy dữ liệu hoặc lệnh nhanh tương ứng.
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
// Custom directive to focus input on show
const focusDirective = {
    mounted(el: HTMLElement) {
        setTimeout(() => el.focus(), 50);
    },
};

export default {
    directives: {
        focus: focusDirective,
    },
};
</script>
