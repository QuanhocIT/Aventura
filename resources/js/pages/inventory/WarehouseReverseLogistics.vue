<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { RefreshCw, RotateCcw, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ canOperate: boolean }>();
const quarantines = ref<any[]>([]);
const returns = ref<any[]>([]);
const claims = ref<any[]>([]);
const loading = ref(false);
const evidence = ref<Record<number, File | null>>({});

const load = async () => {
    loading.value = true;
    try {
        const [q, r, c] = await Promise.all([
            axios.get('/api/warehouse/reverse-logistics/quarantines', { params: { status: 'open' } }),
            axios.get('/api/warehouse/reverse-logistics/returns'),
            axios.get('/api/warehouse/reverse-logistics/claims'),
        ]);
        quarantines.value = q.data.quarantines ?? [];
        returns.value = r.data.returns ?? [];
        claims.value = c.data.claims ?? [];
    } catch (error: any) {
        toast.error(error.response?.data?.message ?? 'Không thể tải danh sách xử lý kho.');
    } finally {
        loading.value = false;
    }
};

const requestReturn = async (row: any) => {
    if (!props.canOperate) return;
    const reason = window.prompt('Lý do hoàn trả:', row.reason ?? 'Hàng không đạt chất lượng');
    if (!reason) return;
    const form = new FormData();
    form.append('reason', reason);
    if (evidence.value[row.id]) form.append('evidence', evidence.value[row.id] as File);
    try {
        await axios.post(`/api/warehouse/reverse-logistics/quarantines/${row.id}/return`, form);
        toast.success('Đã lập phiếu hoàn trả.');
        await load();
    } catch (error: any) {
        toast.error(error.response?.data?.message ?? 'Không thể lập phiếu hoàn trả.');
    }
};

const destroy = async (row: any) => {
    if (!props.canOperate || !evidence.value[row.id]) {
        toast.error('Tiêu hủy bắt buộc có ảnh hoặc biên bản.');
        return;
    }
    const reason = window.prompt('Lý do tiêu hủy:', row.reason ?? 'Hàng không đạt');
    if (!reason) return;
    const form = new FormData();
    form.append('reason', reason);
    form.append('evidence', evidence.value[row.id] as File);
    try {
        await axios.post(`/api/warehouse/reverse-logistics/quarantines/${row.id}/destroy`, form);
        toast.success('Đã ghi nhận tiêu hủy.');
        await load();
    } catch (error: any) {
        toast.error(error.response?.data?.message ?? 'Không thể ghi nhận tiêu hủy.');
    }
};

const approveReturn = async (row: any) => {
    try {
        await axios.post(`/api/warehouse/reverse-logistics/returns/${row.id}/approve`);
        toast.success('Đã duyệt hoàn trả.');
        await load();
    } catch (error: any) {
        toast.error(error.response?.data?.message ?? 'Không thể duyệt hoàn trả.');
    }
};

const completeReturn = async (row: any) => {
    const disposition = window.prompt('Kết quả: central_quarantine / destroyed / supplier_confirmed', 'supplier_confirmed');
    if (!disposition || !['central_quarantine', 'destroyed', 'supplier_confirmed'].includes(disposition)) return;
    try {
        await axios.post(`/api/warehouse/reverse-logistics/returns/${row.id}/complete`, { disposition, notes: 'Đã đối chiếu bằng chứng giao nhận.' });
        toast.success('Đã chốt phiếu hoàn trả.');
        await load();
    } catch (error: any) {
        toast.error(error.response?.data?.message ?? 'Không thể chốt hoàn trả.');
    }
};

const setEvidence = (id: number, event: Event) => {
    evidence.value[id] = (event.target as HTMLInputElement).files?.[0] ?? null;
};

onMounted(load);
</script>

<template>
    <Head title="Cách ly & hoàn trả kho" />
    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-amber-500">Reverse logistics</p>
                <h1 class="text-2xl font-black">Cách ly, hoàn trả & khiếu nại</h1>
                <p class="mt-1 text-sm text-muted-foreground">Hàng lỗi không được đưa vào tồn khả dụng cho đến khi có kết luận.</p>
            </div>
            <Button variant="outline" :disabled="loading" @click="load"><RefreshCw class="mr-2 size-4" /> Làm mới</Button>
        </div>

        <Card>
            <CardHeader><CardTitle>Lô đang cách ly ({{ quarantines.length }})</CardTitle></CardHeader>
            <CardContent>
                <div v-if="!quarantines.length" class="py-8 text-center text-sm text-muted-foreground">Không có lô đang chờ xử lý.</div>
                <div v-for="row in quarantines" :key="row.id" class="mb-3 grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_auto]">
                    <div>
                        <div class="font-bold">{{ row.ingredient?.name ?? `Nguyên liệu #${row.ingredient_id}` }} · {{ row.quantity }}</div>
                        <div class="text-xs text-muted-foreground">Lô {{ row.batch?.batch_code ?? row.inventory_batch_id ?? '-' }} · {{ row.branch?.name ?? '-' }} · {{ row.condition }}</div>
                        <p class="mt-2 text-sm">{{ row.reason }}</p>
                        <input type="file" accept="image/*,.pdf" class="mt-2 text-xs" @change="setEvidence(row.id, $event)" />
                    </div>
                    <div v-if="canOperate" class="flex flex-wrap items-center gap-2 md:justify-end">
                        <Button size="sm" variant="outline" @click="requestReturn(row)"><RotateCcw class="mr-1 size-4" /> Hoàn trả</Button>
                        <Button size="sm" variant="destructive" @click="destroy(row)"><Trash2 class="mr-1 size-4" /> Tiêu hủy</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Phiếu hoàn trả</CardTitle></CardHeader>
            <CardContent>
                <div v-if="!returns.length" class="py-8 text-center text-sm text-muted-foreground">Chưa có phiếu hoàn trả.</div>
                <div v-for="row in returns" :key="row.id" class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
                    <div><div class="font-bold">{{ row.return_code }} · {{ row.status }}</div><div class="text-xs text-muted-foreground">{{ row.reason }}</div></div>
                    <div v-if="canOperate" class="flex gap-2">
                        <Button v-if="row.status === 'requested'" size="sm" variant="outline" @click="approveReturn(row)">Duyệt</Button>
                        <Button v-if="['requested', 'in_transit'].includes(row.status)" size="sm" @click="completeReturn(row)">Chốt nhận/trả</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Khiếu nại nhà cung cấp / vận chuyển</CardTitle></CardHeader>
            <CardContent>
                <div v-if="!claims.length" class="py-8 text-center text-sm text-muted-foreground">Chưa có hồ sơ khiếu nại.</div>
                <div v-for="row in claims" :key="row.id" class="mb-3 rounded-lg border p-4">
                    <div class="flex justify-between gap-3"><span class="font-bold">#{{ row.id }} · {{ row.status }}</span><span>{{ row.loss_amount }} VND</span></div>
                    <p class="mt-1 text-sm">{{ row.reason }}</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
