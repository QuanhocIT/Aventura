<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    KeyRound, Search, ShieldCheck, ShieldOff, ShieldX,
    UserCheck, UserX, Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogFooter,
    DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    accounts: {
        data: Array<{
            id: number; name: string; email: string; phone: string | null;
            status: string; roles: string[]; restaurant: string;
            restaurant_id: number | null; has_2fa: boolean;
            last_login_at: string | null; email_verified: boolean; created_at: string;
        }>;
        links: any[]; meta: any;
    };
    filters: { search?: string; role?: string; status?: string };
}>();

const { getInitials } = useInitials();
const page = usePage();

// Flash notifications
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
toast.success(flash.success);
}

    if (flash?.error)   {
toast.error(flash.error);
}

    if (flash?.temp_password) {
        tempPassword.value = flash.temp_password;
        showTempPassword.value = true;
    }
}, { immediate: true, deep: true });

// Filters
const search     = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');
const statusFilter = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => {
 clearTimeout(timer); timer = setTimeout(applyFilter, 400); 
});

function applyFilter() {
    router.get('/super-admin/accounts', {
        search: search.value || undefined,
        role:   roleFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

// Temp password dialog
const tempPassword     = ref('');
const showTempPassword = ref(false);
const copied           = ref(false);
function copyPassword() {
    navigator.clipboard.writeText(tempPassword.value);
    copied.value = true;
    setTimeout(() => {
 copied.value = false; 
}, 2000);
}

// Reset password
const processingReset = ref<number | null>(null);
function resetPassword(account: { id: number; name: string }) {
    if (!confirm(`Reset máº­t kháº©u cho "${account.name}"? Máº­t kháº©u má»›i sáº½ hiá»ƒn thá»‹ ngay sau thao tÃ¡c nÃ y.`)) {
return;
}

    processingReset.value = account.id;
    router.post(`/super-admin/accounts/${account.id}/reset-password`, {}, {
        onFinish: () => {
 processingReset.value = null; 
},
    });
}

// Disable 2FA
const processingDisable2FA = ref<number | null>(null);
function disable2FA(account: { id: number; name: string }) {
    if (!confirm(`Táº¯t xÃ¡c thá»±c 2FA cho "${account.name}"?`)) {
return;
}

    processingDisable2FA.value = account.id;
    router.post(`/super-admin/accounts/${account.id}/disable-2fa`, {}, {
        onFinish: () => {
 processingDisable2FA.value = null; 
},
    });
}

// Toggle status
const statusForm = useForm({ status: '' });
const showStatusDialog = ref(false);
const selectedAccount  = ref<{ id: number; name: string; status: string } | null>(null);

function openStatusDialog(account: any) {
    selectedAccount.value = account;
    statusForm.status = account.status === 'active' ? 'suspended' : 'active';
    showStatusDialog.value = true;
}
function submitStatus() {
    if (!selectedAccount.value) {
return;
}

    statusForm.patch(`/super-admin/accounts/${selectedAccount.value.id}/status`, {
        onSuccess: () => {
 showStatusDialog.value = false; 
},
    });
}

// Helpers
const roleLabel: Record<string, string> = {
    owner: 'Chá»§ sá»Ÿ há»¯u', manager: 'Quáº£n lÃ½', cashier: 'Thu ngÃ¢n',
    kitchen: 'Báº¿p', inventory_staff: 'Kho', staff: 'NhÃ¢n viÃªn',
};
const roleColor: Record<string, string> = {
    owner:           'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    manager:         'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    cashier:         'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    kitchen:         'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
    inventory_staff: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
};

const totalWith2FA   = computed(() => props.accounts.data.filter(a => a.has_2fa).length);
</script>

<template>
    <Head title="Quáº£n lÃ½ tÃ i khoáº£n" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Quáº£n lÃ½ tÃ i khoáº£n</h1>
                <p class="text-sm text-muted-foreground">
                    Tá»•ng cá»™ng {{ accounts.meta?.total ?? 0 }} tÃ i khoáº£n chá»§ doanh nghiá»‡p
                </p>
            </div>
            <a
                href="/super-admin/audit-logs"
                class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted transition-colors"
            >
                <ShieldCheck class="size-4" />
                Xem Audit Log
            </a>
        </div>

        <!-- Stat mini cards -->
        <div class="grid grid-cols-3 gap-4">
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <UserCheck class="size-8 text-green-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Äang hoáº¡t Ä‘á»™ng</p>
                        <p class="text-2xl font-bold text-green-600">{{ accounts.meta?.total ?? 0 }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <ShieldCheck class="size-8 text-blue-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">ÄÃ£ báº­t 2FA (trang nÃ y)</p>
                        <p class="text-2xl font-bold text-blue-600">{{ totalWith2FA }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <Users class="size-8 text-purple-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Tá»•ng trang nÃ y</p>
                        <p class="text-2xl font-bold">{{ accounts.data.length }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Bá»™ lá»c -->
        <Card>
            <CardContent class="flex flex-wrap gap-3 p-4">
                <div class="relative min-w-64 flex-1">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="TÃ¬m tÃªn, email..." class="pl-9" />
                </div>
                <select
                    v-model="roleFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    @change="applyFilter"
                >
                    <option value="">Táº¥t cáº£ vai trÃ²</option>
                    <option value="owner">Chá»§ sá»Ÿ há»¯u</option>
                    <option value="manager">Quáº£n lÃ½</option>
                    <option value="cashier">Thu ngÃ¢n</option>
                    <option value="kitchen">Báº¿p</option>
                    <option value="inventory_staff">Kho</option>
                </select>
                <select
                    v-model="statusFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    @change="applyFilter"
                >
                    <option value="">Táº¥t cáº£ tráº¡ng thÃ¡i</option>
                    <option value="active">Hoáº¡t Ä‘á»™ng</option>
                    <option value="suspended">Bá»‹ khoÃ¡</option>
                </select>
            </CardContent>
        </Card>

        <!-- Báº£ng tÃ i khoáº£n -->
        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="px-6 py-3 font-medium">TÃ i khoáº£n</th>
                            <th class="px-4 py-3 font-medium">NhÃ  hÃ ng</th>
                            <th class="px-4 py-3 font-medium">Vai trÃ²</th>
                            <th class="px-4 py-3 font-medium">Báº£o máº­t</th>
                            <th class="px-4 py-3 font-medium">Tráº¡ng thÃ¡i</th>
                            <th class="px-4 py-3 font-medium">ÄÄƒng nháº­p cuá»‘i</th>
                            <th class="px-4 py-3 font-medium text-right">Thao tÃ¡c</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="a in accounts.data"
                            :key="a.id"
                            class="border-b last:border-0 hover:bg-muted/30 transition-colors"
                        >
                            <!-- TÃ i khoáº£n -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <Avatar class="h-8 w-8 shrink-0">
                                        <AvatarFallback class="bg-primary/10 text-primary text-xs font-semibold">
                                            {{ getInitials(a.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <p class="font-medium">{{ a.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ a.email }}</p>
                                        <p v-if="a.phone" class="text-xs text-muted-foreground">{{ a.phone }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- NhÃ  hÃ ng -->
                            <td class="px-4 py-4 text-sm">{{ a.restaurant }}</td>

                            <!-- Vai trÃ² -->
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in a.roles"
                                        :key="role"
                                        :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', roleColor[role] ?? 'bg-gray-100 text-gray-700']"
                                    >
                                        {{ roleLabel[role] ?? role }}
                                    </span>
                                </div>
                            </td>

                            <!-- Báº£o máº­t -->
                            <td class="px-4 py-4">
                                <div class="flex flex-col gap-1">
                                    <span
                                        :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium w-fit', a.has_2fa ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400']"
                                    >
                                        <ShieldCheck v-if="a.has_2fa" class="size-3" />
                                        <ShieldOff v-else class="size-3" />
                                        {{ a.has_2fa ? '2FA báº­t' : '2FA táº¯t' }}
                                    </span>
                                    <span
                                        :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs w-fit', a.email_verified ? 'text-green-600' : 'text-orange-500']"
                                    >
                                        {{ a.email_verified ? 'âœ“ Email xÃ¡c thá»±c' : 'âš  ChÆ°a xÃ¡c thá»±c' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Tráº¡ng thÃ¡i -->
                            <td class="px-4 py-4">
                                <span
                                    :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', a.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300']"
                                >
                                    {{ a.status === 'active' ? 'Hoáº¡t Ä‘á»™ng' : 'Bá»‹ khoÃ¡' }}
                                </span>
                            </td>

                            <!-- ÄÄƒng nháº­p cuá»‘i -->
                            <td class="px-4 py-4 text-xs text-muted-foreground">
                                {{ a.last_login_at ?? 'ChÆ°a Ä‘Äƒng nháº­p' }}
                            </td>

                            <!-- Thao tÃ¡c -->
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- Reset máº­t kháº©u -->
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1.5 text-xs"
                                        :disabled="processingReset === a.id"
                                        title="Reset máº­t kháº©u"
                                        @click="resetPassword(a)"
                                    >
                                        <KeyRound class="size-3.5" />
                                        Reset
                                    </Button>

                                    <!-- Táº¯t 2FA -->
                                    <Button
                                        v-if="a.has_2fa"
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1.5 text-xs text-orange-600 hover:text-orange-700"
                                        :disabled="processingDisable2FA === a.id"
                                        title="Táº¯t 2FA"
                                        @click="disable2FA(a)"
                                    >
                                        <ShieldX class="size-3.5" />
                                        Táº¯t 2FA
                                    </Button>

                                    <!-- KhoÃ¡ / Má»Ÿ khoÃ¡ -->
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        :class="['gap-1.5 text-xs', a.status === 'active' ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700']"
                                        @click="openStatusDialog(a)"
                                    >
                                        <UserX v-if="a.status === 'active'" class="size-3.5" />
                                        <UserCheck v-else class="size-3.5" />
                                        {{ a.status === 'active' ? 'KhoÃ¡' : 'Má»Ÿ khoÃ¡' }}
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!accounts.data.length">
                            <td colspan="7" class="px-6 py-12 text-center text-muted-foreground">
                                KhÃ´ng tÃ¬m tháº¥y tÃ i khoáº£n nÃ o
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="accounts.meta?.last_page > 1" class="flex justify-center gap-1 border-t p-4">
                    <a
                        v-for="link in accounts.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="['px-3 py-1 rounded text-sm border', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted', !link.url ? 'opacity-40 pointer-events-none' : '']"
                    />
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Dialog: Máº­t kháº©u táº¡m thá»i -->
    <Dialog v-model:open="showTempPassword">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <KeyRound class="size-5 text-primary" />
                    Máº­t kháº©u táº¡m thá»i
                </DialogTitle>
            </DialogHeader>
            <div class="space-y-3 py-2">
                <p class="text-sm text-muted-foreground">
                    Cung cáº¥p máº­t kháº©u nÃ y cho ngÆ°á»i dÃ¹ng. Há»‡ thá»‘ng khÃ´ng lÆ°u máº­t kháº©u dáº¡ng vÄƒn báº£n â€” Ä‘Ã¢y lÃ  láº§n duy nháº¥t báº¡n tháº¥y nÃ³.
                </p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 rounded bg-muted px-4 py-3 font-mono text-lg font-bold tracking-widest select-all">
                        {{ tempPassword }}
                    </code>
                </div>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="showTempPassword = false">ÄÃ³ng</Button>
                <Button @click="copyPassword">
                    {{ copied ? 'âœ“ ÄÃ£ sao chÃ©p' : 'Sao chÃ©p' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Dialog: Äá»•i tráº¡ng thÃ¡i -->
    <Dialog v-model:open="showStatusDialog">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>
                    {{ statusForm.status === 'suspended' ? 'KhoÃ¡ tÃ i khoáº£n' : 'Má»Ÿ khoÃ¡ tÃ i khoáº£n' }}
                </DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground py-2">
                Báº¡n cÃ³ cháº¯c muá»‘n
                <strong>{{ statusForm.status === 'suspended' ? 'khoÃ¡' : 'má»Ÿ khoÃ¡' }}</strong>
                tÃ i khoáº£n <strong>{{ selectedAccount?.name }}</strong>?
                <span v-if="statusForm.status === 'suspended'" class="block mt-1 text-red-600">
                    NgÆ°á»i dÃ¹ng sáº½ khÃ´ng thá»ƒ Ä‘Äƒng nháº­p vÃ o há»‡ thá»‘ng.
                </span>
            </p>
            <DialogFooter>
                <Button variant="outline" @click="showStatusDialog = false">Há»§y</Button>
                <Button
                    :variant="statusForm.status === 'suspended' ? 'destructive' : 'default'"
                    :disabled="statusForm.processing"
                    @click="submitStatus"
                >
                    {{ statusForm.processing ? 'Äang xá»­ lÃ½...' : 'XÃ¡c nháº­n' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

