<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, Eye, ShieldCheck, ShieldOff, Crown } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurants: {
        data: Array<{
            id: number; name: string; code: string; status: string;
            plan: string; plan_code: string; owner: string; owner_email: string;
            branches_count: number; employees_count: number; tables_count: number;
            created_at: string;
        }>;
        links: any[]; meta: any;
    };
    plans: Array<{ id: number; code: string; name: string }>;
    filters: { status?: string; plan?: string; search?: string };
}>();

const search   = ref(props.filters.search ?? '');
const status   = ref(props.filters.status ?? '');
const planFilter = ref(props.filters.plan ?? '');

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilter(), 400);
});

function applyFilter() {
    router.get('/super-admin/restaurants', {
        search: search.value || undefined,
        status: status.value || undefined,
        plan:   planFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

// Dialog táº¡o nhÃ  hÃ ng
const showCreate = ref(false);
const createForm = useForm({
    name: '', tax_code: '', phone: '', email: '', address: '',
    plan_id: '', owner_name: '', owner_email: '',
    timezone: 'Asia/Ho_Chi_Minh', currency: 'VND',
});
function submitCreate() {
    createForm.post('/super-admin/restaurants', {
        onSuccess: () => {
 showCreate.value = false; createForm.reset(); 
},
    });
}

// Dialog Ä‘á»•i tráº¡ng thÃ¡i
const showStatus = ref(false);
const selectedRestaurant = ref<{ id: number; name: string; status: string } | null>(null);
const statusForm = useForm({ status: '', reason: '' });
function openStatus(r: any) {
    selectedRestaurant.value = r;
    statusForm.status = r.status;
    showStatus.value = true;
}
function submitStatus() {
    if (!selectedRestaurant.value) {
return;
}

    statusForm.patch(`/super-admin/restaurants/${selectedRestaurant.value.id}/status`, {
        onSuccess: () => {
 showStatus.value = false; 
},
    });
}

const statusColor: Record<string, string> = {
    active:    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    suspended: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    expired:   'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
const statusLabel: Record<string, string> = {
    active: 'Hoáº¡t Ä‘á»™ng', suspended: 'Táº¡m ngÆ°ng', expired: 'Háº¿t háº¡n',
};
</script>

<template>
    <Head title="Quáº£n lÃ½ nhÃ  hÃ ng" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Quáº£n lÃ½ nhÃ  hÃ ng</h1>
                <p class="text-sm text-muted-foreground">
                    Tá»•ng cá»™ng {{ restaurants.meta?.total ?? 0 }} nhÃ  hÃ ng
                </p>
            </div>
            <Button @click="showCreate = true" class="gap-2">
                <Plus class="size-4" /> ThÃªm nhÃ  hÃ ng
            </Button>
        </div>

        <!-- Bá»™ lá»c -->
        <Card>
            <CardContent class="flex flex-wrap gap-3 p-4">
                <div class="relative flex-1 min-w-48">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="TÃ¬m tÃªn, mÃ£, mÃ£ thuáº¿..." class="pl-9" />
                </div>
                <select
                    v-model="status"
                    @change="applyFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Táº¥t cáº£ tráº¡ng thÃ¡i</option>
                    <option value="active">Hoáº¡t Ä‘á»™ng</option>
                    <option value="suspended">Táº¡m ngÆ°ng</option>
                    <option value="expired">Háº¿t háº¡n</option>
                </select>
                <select
                    v-model="planFilter"
                    @change="applyFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Táº¥t cáº£ gÃ³i</option>
                    <option v-for="p in plans" :key="p.code" :value="p.code">{{ p.name }}</option>
                </select>
            </CardContent>
        </Card>

        <!-- Báº£ng danh sÃ¡ch -->
        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="px-6 py-3 font-medium">NhÃ  hÃ ng</th>
                            <th class="px-4 py-3 font-medium">GÃ³i</th>
                            <th class="px-4 py-3 font-medium">TÃ i nguyÃªn</th>
                            <th class="px-4 py-3 font-medium">Tráº¡ng thÃ¡i</th>
                            <th class="px-4 py-3 font-medium">NgÃ y táº¡o</th>
                            <th class="px-4 py-3 font-medium text-right">Thao tÃ¡c</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in restaurants.data"
                            :key="r.id"
                            class="border-b last:border-0 hover:bg-muted/30 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <p class="font-medium">{{ r.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ r.owner_email }}</p>
                                <p class="text-xs text-muted-foreground font-mono">{{ r.code }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <span :class="['flex items-center gap-1 font-medium text-xs', r.plan_code === 'PRO' ? 'text-purple-600' : 'text-muted-foreground']">
                                    <Crown v-if="r.plan_code === 'PRO'" class="size-3" />
                                    {{ r.plan }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-muted-foreground">
                                <span>{{ r.branches_count }} chi nhÃ¡nh</span> Â·
                                <span>{{ r.employees_count }} NV</span> Â·
                                <span>{{ r.tables_count }} bÃ n</span>
                            </td>
                            <td class="px-4 py-4">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusColor[r.status]]">
                                    {{ statusLabel[r.status] ?? r.status }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-muted-foreground">{{ r.created_at }}</td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/super-admin/restaurants/${r.id}`">
                                        <Button variant="ghost" size="icon-sm" title="Xem chi tiáº¿t">
                                            <Eye class="size-4" />
                                        </Button>
                                    </Link>
                                    <Button
                                        variant="ghost" size="icon-sm"
                                        :title="r.status === 'active' ? 'Táº¡m ngÆ°ng' : 'KÃ­ch hoáº¡t'"
                                        @click="openStatus(r)"
                                    >
                                        <ShieldOff v-if="r.status === 'active'" class="size-4 text-yellow-600" />
                                        <ShieldCheck v-else class="size-4 text-green-600" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!restaurants.data.length">
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                KhÃ´ng tÃ¬m tháº¥y nhÃ  hÃ ng nÃ o
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="restaurants.meta?.last_page > 1" class="flex justify-center gap-1 border-t p-4">
                    <Link
                        v-for="link in restaurants.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-sm border', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted', !link.url ? 'opacity-40 pointer-events-none' : '']"
                    >
                        <span v-html="link.label" />
                    </Link>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Dialog Táº¡o nhÃ  hÃ ng -->
    <Dialog v-model:open="showCreate">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>ThÃªm nhÃ  hÃ ng má»›i</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="grid gap-4 py-2">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 grid gap-1.5">
                        <Label>TÃªn nhÃ  hÃ ng *</Label>
                        <Input v-model="createForm.name" placeholder="NhÃ  hÃ ng ABC" required />
                        <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>MÃ£ sá»‘ thuáº¿</Label>
                        <Input v-model="createForm.tax_code" placeholder="0123456789" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Sá»‘ Ä‘iá»‡n thoáº¡i</Label>
                        <Input v-model="createForm.phone" placeholder="0901234567" />
                    </div>
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Email nhÃ  hÃ ng</Label>
                        <Input v-model="createForm.email" type="email" placeholder="contact@restaurant.com" />
                    </div>
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Äá»‹a chá»‰</Label>
                        <Input v-model="createForm.address" placeholder="123 ÄÆ°á»ng ABC, Quáº­n 1..." />
                    </div>
                    <div class="col-span-2 grid gap-1.5">
                        <Label>GÃ³i dá»‹ch vá»¥ *</Label>
                        <select v-model="createForm.plan_id" required class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                            <option value="">Chá»n gÃ³i...</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="createForm.errors.plan_id" class="text-xs text-destructive">{{ createForm.errors.plan_id }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>TÃªn chá»§ sá»Ÿ há»¯u *</Label>
                        <Input v-model="createForm.owner_name" placeholder="Nguyá»…n VÄƒn A" required />
                        <p v-if="createForm.errors.owner_name" class="text-xs text-destructive">{{ createForm.errors.owner_name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Email chá»§ sá»Ÿ há»¯u *</Label>
                        <Input v-model="createForm.owner_email" type="email" placeholder="owner@email.com" required />
                        <p v-if="createForm.errors.owner_email" class="text-xs text-destructive">{{ createForm.errors.owner_email }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showCreate = false">Há»§y</Button>
                    <Button type="submit" :disabled="createForm.processing">
                        {{ createForm.processing ? 'Äang táº¡o...' : 'Táº¡o nhÃ  hÃ ng' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Dialog Äá»•i tráº¡ng thÃ¡i -->
    <Dialog v-model:open="showStatus">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Äá»•i tráº¡ng thÃ¡i: {{ selectedRestaurant?.name }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitStatus" class="grid gap-4 py-2">
                <div class="grid gap-1.5">
                    <Label>Tráº¡ng thÃ¡i má»›i</Label>
                    <select v-model="statusForm.status" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                        <option value="active">âœ… KÃ­ch hoáº¡t</option>
                        <option value="suspended">â¸ Táº¡m ngÆ°ng</option>
                        <option value="expired">âŒ Háº¿t háº¡n</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>LÃ½ do (tuá»³ chá»n)</Label>
                    <Input v-model="statusForm.reason" placeholder="Ghi chÃº lÃ½ do..." />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showStatus = false">Há»§y</Button>
                    <Button type="submit" :disabled="statusForm.processing">XÃ¡c nháº­n</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>


