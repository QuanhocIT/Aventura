<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, FileText, Filter, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    logs: {
        data: Array<{
            id: number;
            restaurant: string | null;
            user_name: string;
            user_email: string | null;
            user_role: string;
            event: string;
            action: string;
            subject_type: string | null;
            subject_id: number | null;
            ip_address: string | null;
            old_values: Record<string, any> | null;
            new_values: Record<string, any> | null;
            created_at: string;
        }>;
        links: any[]; meta: any;
    };
    restaurants: Array<{ id: number; name: string }>;
    filters: { restaurant_id?: string; event?: string; action?: string; from?: string; to?: string };
    total: number;
}>();

const restaurantFilter = ref(props.filters.restaurant_id ?? '');
const eventFilter      = ref(props.filters.event ?? '');
const actionFilter     = ref(props.filters.action ?? '');
const fromFilter       = ref(props.filters.from ?? '');
const toFilter         = ref(props.filters.to ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(actionFilter, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilter, 500);
});

function applyFilter() {
    router.get('/super-admin/audit-logs', {
        restaurant_id: restaurantFilter.value || undefined,
        event:         eventFilter.value || undefined,
        action:        actionFilter.value || undefined,
        from:          fromFilter.value || undefined,
        to:            toFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    restaurantFilter.value = '';
    eventFilter.value = '';
    actionFilter.value = '';
    fromFilter.value = '';
    toFilter.value = '';
    applyFilter();
}

// Expand row to see values
const expandedRow = ref<number | null>(null);
function toggleExpand(id: number) {
    expandedRow.value = expandedRow.value === id ? null : id;
}

// Helpers
const eventColor: Record<string, string> = {
    created: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    updated: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    deleted: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
const eventLabel: Record<string, string> = {
    created: 'Táº¡o má»i', updated: 'Cáº­p nháº­t', deleted: 'XÃ³a',
};

const actionLabel: Record<string, string> = {
    reset_password:        'Reset máº­t kháº©u',
    disable_2fa:           'Táº¯t 2FA',
    toggle_account_status: 'Äá»i tráº¡ng thÃ¡i TK',
    seed_demo_order:       'Seed ÄÆ¡n demo',
};

function formatAction(action: string): string {
    return actionLabel[action] ?? action.replace(/_/g, ' ');
}

const hasActiveFilter = () =>
    restaurantFilter.value || eventFilter.value ||
    actionFilter.value || fromFilter.value || toFilter.value;
</script>

<template>
    <Head title="Audit Log há» thá»ng" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Audit Log há» thá»ng</h1>
                <p class="text-sm text-muted-foreground">
                    Nháº­t kÃ½ thao tÃ¡c cáº¥p há» thá»ng Â· Tá»ng {{ total.toLocaleString() }} báº£n ghi
                </p>
            </div>
            <a
                href="/super-admin/accounts"
                class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted transition-colors"
            >
                Quáº£n lÃ½ tÃ i khoáº£n â
            </a>
        </div>

        <!-- Bá» lá»c -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-medium">
                    <Filter class="size-4" /> Bá» lá»c
                    <button
                        v-if="hasActiveFilter()"
                        class="ml-auto text-xs text-muted-foreground hover:text-foreground"
                        @click="resetFilters"
                    >
                        XoÃ¡ lá»c
                    </button>
                </CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-3 pt-0 lg:grid-cols-5">
                <!-- NhÃ  hÃ ng -->
                <select
                    v-model="restaurantFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    @change="applyFilter"
                >
                    <option value="">Táº¥t cáº£ nhÃ  hÃ ng</option>
                    <option value="system">â Há» thá»ng â</option>
                    <option v-for="r in restaurants" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>

                <!-- Loáº¡i sá»± kiá»n -->
                <select
                    v-model="eventFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    @change="applyFilter"
                >
                    <option value="">Táº¥t cáº£ sá»± kiá»n</option>
                    <option value="created">Táº¡o má»i</option>
                    <option value="updated">Cáº­p nháº­t</option>
                    <option value="deleted">XÃ³a</option>
                </select>

                <!-- Action -->
                <div class="relative">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="actionFilter" placeholder="TÃ¬m hÃ nh Äá»ng..." class="pl-9" />
                </div>

                <!-- Tá»« ngÃ y -->
                <Input v-model="fromFilter" type="date" @change="applyFilter" />

                <!-- Äáº¿n ngÃ y -->
                <Input v-model="toFilter" type="date" @change="applyFilter" />
            </CardContent>
        </Card>

        <!-- Báº£ng log -->
        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="w-8 px-4 py-3" />
                            <th class="px-4 py-3 font-medium">Thá»i gian</th>
                            <th class="px-4 py-3 font-medium">NhÃ  hÃ ng</th>
                            <th class="px-4 py-3 font-medium">NgÆ°á»i thá»±c hiá»n</th>
                            <th class="px-4 py-3 font-medium">Sá»± kiá»n</th>
                            <th class="px-4 py-3 font-medium">HÃ nh Äá»ng</th>
                            <th class="px-4 py-3 font-medium">Äá»i tÆ°á»£ng</th>
                            <th class="px-4 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="log in logs.data" :key="log.id">
                            <!-- Row chÃ­nh -->
                            <tr
                                class="border-b hover:bg-muted/30 transition-colors cursor-pointer"
                                :class="expandedRow === log.id ? 'bg-muted/20' : ''"
                                @click="toggleExpand(log.id)"
                            >
                                <td class="px-4 py-3 text-muted-foreground">
                                    <ChevronDown v-if="expandedRow === log.id" class="size-4" />
                                    <ChevronRight v-else class="size-4" />
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">
                                    {{ log.created_at }}
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <span v-if="log.restaurant" class="font-medium">{{ log.restaurant }}</span>
                                    <span v-else class="italic text-muted-foreground">â Há» thá»ng â</span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-xs">{{ log.user_name }}</p>
                                    <p v-if="log.user_email" class="text-xs text-muted-foreground">{{ log.user_email }}</p>
                                    <span
                                        v-if="log.user_role"
                                        class="inline-block rounded-full bg-muted px-1.5 py-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ log.user_role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', eventColor[log.event] ?? 'bg-gray-100 text-gray-700']"
                                    >
                                        {{ eventLabel[log.event] ?? log.event }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs font-mono">
                                    {{ formatAction(log.action) }}
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">
                                    <span v-if="log.subject_type">
                                        {{ log.subject_type }}
                                        <span v-if="log.subject_id" class="font-mono">#{{ log.subject_id }}</span>
                                    </span>
                                    <span v-else>â</span>
                                </td>
                                <td class="px-4 py-3 text-xs font-mono text-muted-foreground">
                                    {{ log.ip_address ?? 'â' }}
                                </td>
                            </tr>

                            <!-- Row expand: chi tiáº¿t old/new values -->
                            <tr v-if="expandedRow === log.id" class="border-b bg-muted/10">
                                <td colspan="8" class="px-8 py-4">
                                    <div class="grid grid-cols-2 gap-6 text-xs">
                                        <div>
                                            <p class="mb-2 font-semibold text-muted-foreground uppercase tracking-wide">Dá»¯ liá»u cÅ©</p>
                                            <pre
                                                v-if="log.old_values"
                                                class="rounded bg-red-50 dark:bg-red-900/20 p-3 text-red-800 dark:text-red-300 overflow-auto max-h-40"
                                            >{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                                            <span v-else class="italic text-muted-foreground">â KhÃ´ng cÃ³ â</span>
                                        </div>
                                        <div>
                                            <p class="mb-2 font-semibold text-muted-foreground uppercase tracking-wide">Dá»¯ liá»u má»i</p>
                                            <pre
                                                v-if="log.new_values"
                                                class="rounded bg-green-50 dark:bg-green-900/20 p-3 text-green-800 dark:text-green-300 overflow-auto max-h-40"
                                            >{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                                            <span v-else class="italic text-muted-foreground">â KhÃ´ng cÃ³ â</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="!logs.data.length">
                            <td colspan="8" class="px-6 py-16 text-center">
                                <FileText class="mx-auto mb-3 size-10 text-muted-foreground/40" />
                                <p class="text-muted-foreground">Không có bản ghi nào phù hợp</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="logs.meta?.last_page > 1" class="flex justify-center gap-1 border-t p-4">
                    <a
                        v-for="link in logs.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="['px-3 py-1 rounded text-sm border', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted', !link.url ? 'opacity-40 pointer-events-none' : '']"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>



