<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, Check, FileWarning, RefreshCw, RotateCcw, Send, Trash2, X } from 'lucide-vue-next';
import { onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Option = { id: number; name: string; is_central_warehouse?: boolean };
type Row = Record<string, any>;

const props = defineProps<{ canOperate: boolean; canApprove: boolean; canComplete: boolean; canDispose: boolean; canResolve: boolean; branches: Option[]; suppliers: Option[] }>();
const quarantines = ref<Row[]>([]);
const returns = ref<Row[]>([]);
const claims = ref<Row[]>([]);
const loading = ref(false);
const returnModal = ref(false);
const destroyModal = ref(false);
const completeModal = ref(false);
const claimModal = ref(false);
const resolveModal = ref(false);
const claimFiles = ref<File[]>([]);
const resolveClaimId = ref<number | null>(null);
const resolveNotes = ref('');

const returnForm = reactive<{ row: Row | null; quantity: string; to_branch_id: string; supplier_id: string; reason: string; notes: string; evidence: File | null }>({ row: null, quantity: '', to_branch_id: '', supplier_id: '', reason: '', notes: '', evidence: null });
const destroyForm = reactive<{ row: Row | null; reason: string; evidence: File | null }>({ row: null, reason: '', evidence: null });
const completeForm = reactive<{ row: Row | null; disposition: string; notes: string; evidence: File | null }>({ row: null, disposition: 'supplier_confirmed', notes: '', evidence: null });
const claimForm = reactive<{ supplier_id: string; source_type: string; source_id: string; carrier_name: string; reason: string; loss_amount: string; requested_action: string; due_at: string }>({ supplier_id: '', source_type: '', source_id: '', carrier_name: '', reason: '', loss_amount: '0', requested_action: 'replacement', due_at: '' });

const statusLabel = (status: string) => ({ open: 'Đang cách ly', return_requested: 'Đã yêu cầu hoàn', requested: 'Chờ duyệt', in_transit: 'Đang vận chuyển', received: 'Đã nhận hoàn', destroyed: 'Đã tiêu hủy', returned: 'Đã hoàn tất', resolved: 'Đã đóng', investigating: 'Đang xác minh' }[status] ?? status);
const statusClass = (status: string) => ({ open: 'bg-amber-500/10 text-amber-600', return_requested: 'bg-blue-500/10 text-blue-600', requested: 'bg-amber-500/10 text-amber-600', in_transit: 'bg-indigo-500/10 text-indigo-600', received: 'bg-emerald-500/10 text-emerald-600', returned: 'bg-emerald-500/10 text-emerald-600', destroyed: 'bg-rose-500/10 text-rose-600', resolved: 'bg-emerald-500/10 text-emerald-600', investigating: 'bg-purple-500/10 text-purple-600' }[status] ?? 'bg-muted text-muted-foreground');
const completedReturnQuantity = (row: Row) => (row.return_items ?? []).filter((item: Row) => ['received', 'destroyed'].includes(item.return_order?.status)).reduce((total: number, item: Row) => total + Number(item.quantity ?? 0), 0);
const availableQuantity = (row: Row) => Math.max(0, Number(row.quantity ?? 0) - completedReturnQuantity(row));
const returnItemsLabel = (row: Row) => (row.items ?? []).map((item: Row) => `${item.ingredient?.name ?? 'Nguyên liệu'}: ${item.quantity}`).join(' · ');

const load = async () => {
    loading.value = true;

    try {
        const [q, r, c] = await Promise.all([
            axios.get('/api/warehouse/reverse-logistics/quarantines', { params: { status: ['open', 'return_requested'] } }),
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

const openReturn = (row: Row) => {
    Object.assign(returnForm, { row, quantity: availableQuantity(row).toFixed(3).replace(/\.000$/, ''), to_branch_id: '', supplier_id: row.batch?.supplier_id ? String(row.batch.supplier_id) : '', reason: row.reason ?? 'Hàng không đạt chất lượng, đề nghị hoàn trả.', notes: '', evidence: null });
    returnModal.value = true;
};
const submitReturn = async () => {
    if (!returnForm.row || !returnForm.quantity || Number(returnForm.quantity) <= 0 || returnForm.reason.trim().length < 5) {
 toast.error('Cần nhập số lượng hoàn trả và lý do hợp lệ.');

 return; 
}

    const form = new FormData(); form.append('quantity', returnForm.quantity); form.append('reason', returnForm.reason);

    if (returnForm.to_branch_id) {
form.append('to_branch_id', returnForm.to_branch_id);
}

 if (returnForm.supplier_id) {
form.append('supplier_id', returnForm.supplier_id);
}

 if (returnForm.notes) {
form.append('notes', returnForm.notes);
}

 if (returnForm.evidence) {
form.append('evidence', returnForm.evidence);
}

    try {
 await axios.post(`/api/warehouse/reverse-logistics/quarantines/${returnForm.row.id}/return`, form); toast.success('Đã lập phiếu hoàn trả và khóa số lượng tương ứng.'); returnModal.value = false; await load(); 
} catch (error: any) {
 toast.error(error.response?.data?.message ?? 'Không thể lập phiếu hoàn trả.'); 
}
};

const openDestroy = (row: Row) => {
 Object.assign(destroyForm, { row, reason: row.reason ?? 'Hàng không thể sử dụng.', evidence: null }); destroyModal.value = true; 
};
const submitDestroy = async () => {
    if (!destroyForm.row || !destroyForm.evidence || destroyForm.reason.trim().length < 5) {
 toast.error('Tiêu hủy bắt buộc có lý do và ảnh/biên bản bằng chứng.');

 return; 
}

    const form = new FormData(); form.append('reason', destroyForm.reason); form.append('evidence', destroyForm.evidence);

    try {
 await axios.post(`/api/warehouse/reverse-logistics/quarantines/${destroyForm.row.id}/destroy`, form); toast.success('Đã ghi nhận tiêu hủy và khóa hồ sơ cách ly.'); destroyModal.value = false; await load(); 
} catch (error: any) {
 toast.error(error.response?.data?.message ?? 'Không thể ghi nhận tiêu hủy.'); 
}
};

const approveReturn = async (row: Row) => {
    if (!window.confirm(`Duyệt phiếu ${row.return_code}? Sau khi duyệt, số hàng sẽ chuyển sang trạng thái đang vận chuyển.`)) {
return;
}

    try {
 await axios.post(`/api/warehouse/reverse-logistics/returns/${row.id}/approve`); toast.success('Đã duyệt phiếu hoàn trả.'); await load(); 
} catch (error: any) {
 toast.error(error.response?.data?.message ?? 'Không thể duyệt phiếu hoàn trả.'); 
}
};
const openComplete = (row: Row) => {
 Object.assign(completeForm, { row, disposition: row.supplier_id ? 'supplier_confirmed' : 'supplier_confirmed', notes: '', evidence: null }); completeModal.value = true; 
};
const submitComplete = async () => {
    if (!completeForm.row || completeForm.notes.trim().length < 5) {
 toast.error('Cần ghi nhận biên bản đối chiếu tối thiểu 5 ký tự.');

 return; 
}

    if (completeForm.disposition === 'destroyed' && !completeForm.evidence) {
 toast.error('Tiêu hủy hàng hoàn trả bắt buộc có ảnh/biên bản.');

 return; 
}

    const form = new FormData(); form.append('disposition', completeForm.disposition); form.append('notes', completeForm.notes);

 if (completeForm.evidence) {
form.append('evidence', completeForm.evidence);
}

    try {
 await axios.post(`/api/warehouse/reverse-logistics/returns/${completeForm.row.id}/complete`, form); toast.success('Đã chốt kết quả phiếu hoàn trả.'); completeModal.value = false; await load(); 
} catch (error: any) {
 toast.error(error.response?.data?.message ?? 'Không thể chốt phiếu hoàn trả.'); 
}
};

const openClaim = (source?: { type?: string; id?: number; supplierId?: number }) => {
    Object.assign(claimForm, { supplier_id: source?.supplierId ? String(source.supplierId) : '', source_type: source?.type ?? '', source_id: source?.id ? String(source.id) : '', carrier_name: '', reason: '', loss_amount: '0', requested_action: 'replacement', due_at: '' });
    claimFiles.value = []; claimModal.value = true;
};
const submitClaim = async () => {
    if ((!claimForm.supplier_id && !claimForm.carrier_name.trim()) || claimForm.reason.trim().length < 5) {
 toast.error('Khiếu nại phải có nhà cung cấp/đơn vị vận chuyển và lý do.');

 return; 
}

    const form = new FormData(); Object.entries(claimForm).forEach(([key, value]) => {
 if (value) {
form.append(key, value);
} 
}); claimFiles.value.forEach((file) => form.append('evidence[]', file));

    try {
 await axios.post('/api/warehouse/reverse-logistics/claims', form); toast.success('Đã lập hồ sơ khiếu nại.'); claimModal.value = false; await load(); 
} catch (error: any) {
 toast.error(error.response?.data?.message ?? 'Không thể lập hồ sơ khiếu nại.'); 
}
};
const openResolve = (row: Row) => {
 resolveClaimId.value = row.id; resolveNotes.value = ''; resolveModal.value = true; 
};
const submitResolve = async () => {
    if (!resolveClaimId.value || resolveNotes.value.trim().length < 5) {
 toast.error('Cần ghi nhận kết quả xử lý khiếu nại.');

 return; 
}

    try {
 await axios.post(`/api/warehouse/reverse-logistics/claims/${resolveClaimId.value}/resolve`, { response_notes: resolveNotes.value }); toast.success('Đã đóng hồ sơ khiếu nại.'); resolveModal.value = false; await load(); 
} catch (error: any) {
 toast.error(error.response?.data?.message ?? 'Không thể đóng hồ sơ khiếu nại.'); 
}
};
const setFile = (target: { evidence: File | null }, event: Event) => {
 target.evidence = (event.target as HTMLInputElement).files?.[0] ?? null; 
};
const setClaimFiles = (event: Event) => {
 claimFiles.value = Array.from((event.target as HTMLInputElement).files ?? []); 
};
onMounted(load);
</script>

<template>
    <Head title="Cách ly & hoàn trả kho" />
    <div class="space-y-6 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-amber-500">Reverse logistics</p><h1 class="text-2xl font-black">Cách ly, hoàn trả & khiếu nại</h1><p class="mt-1 text-sm text-muted-foreground">Hàng lỗi không được đưa vào tồn khả dụng; mọi hoàn trả và tiêu hủy đều phải có người duyệt và bằng chứng.</p></div><div class="flex gap-2"><Button v-if="canOperate" variant="outline" @click="openClaim()"><FileWarning class="mr-2 size-4" /> Lập khiếu nại</Button><Button variant="outline" :disabled="loading" @click="load"><RefreshCw class="mr-2 size-4" /> Làm mới</Button></div></div>
        <Card><CardHeader><CardTitle>Lô đang cách ly ({{ quarantines.length }})</CardTitle></CardHeader><CardContent><div v-if="!quarantines.length" class="py-8 text-center text-sm text-muted-foreground">Không có lô đang chờ xử lý.</div><div v-for="row in quarantines" :key="row.id" class="mb-3 grid gap-4 rounded-lg border p-4 md:grid-cols-[1fr_auto]"><div><div class="flex flex-wrap items-center gap-2 font-bold">{{ row.ingredient?.name ?? `Nguyên liệu #${row.ingredient_id}` }} · {{ row.quantity }}<span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span></div><div class="mt-1 text-xs text-muted-foreground">Lô {{ row.batch?.batch_code ?? row.inventory_batch_id ?? '-' }} · {{ row.branch?.name ?? '-' }} · Tình trạng: {{ row.condition }}</div><div class="mt-2 text-sm">{{ row.reason }}</div><div class="mt-2 flex flex-wrap gap-3 text-xs text-muted-foreground"><span>Đã xử lý: {{ completedReturnQuantity(row).toFixed(3) }}</span><span>Còn cách ly: {{ availableQuantity(row).toFixed(3) }}</span></div></div><div v-if="canOperate && row.status !== 'returned' && row.status !== 'destroyed'" class="flex flex-wrap items-center gap-2 md:justify-end"><Button size="sm" variant="outline" :disabled="availableQuantity(row) <= 0" @click="openReturn(row)"><RotateCcw class="mr-1 size-4" /> Lập phiếu hoàn</Button><Button v-if="canDispose" size="sm" variant="destructive" @click="openDestroy(row)"><Trash2 class="mr-1 size-4" /> Tiêu hủy</Button><Button size="sm" variant="ghost" @click="openClaim({ type: 'inventory_quarantine', id: row.id, supplierId: row.batch?.supplier_id })"><FileWarning class="mr-1 size-4" /> Khiếu nại</Button></div></div></CardContent></Card>
        <Card><CardHeader><CardTitle>Phiếu hoàn trả ({{ returns.length }})</CardTitle></CardHeader><CardContent><div v-if="!returns.length" class="py-8 text-center text-sm text-muted-foreground">Chưa có phiếu hoàn trả.</div><div v-for="row in returns" :key="row.id" class="mb-3 rounded-lg border p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2 font-bold">{{ row.return_code }} <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span></div><div class="mt-1 text-xs text-muted-foreground">{{ row.from_branch?.name ?? '-' }} → {{ row.to_branch?.name ?? (row.supplier?.name ?? 'Bên nhận bên ngoài') }} · Lập bởi {{ row.created_by?.name ?? '-' }}</div><div class="mt-2 text-sm">{{ row.reason }}</div><div class="mt-1 text-xs text-muted-foreground">{{ returnItemsLabel(row) }}</div></div><div v-if="canOperate" class="flex flex-wrap gap-2"><Button v-if="canApprove && row.status === 'requested'" size="sm" variant="outline" @click="approveReturn(row)"><Check class="mr-1 size-4" /> Duyệt</Button><Button v-if="canComplete && row.status === 'in_transit'" size="sm" @click="openComplete(row)"><Send class="mr-1 size-4" /> Chốt giao nhận</Button><Button size="sm" variant="ghost" @click="openClaim({ type: 'inventory_return', id: row.id, supplierId: row.supplier_id })"><FileWarning class="mr-1 size-4" /> Khiếu nại</Button></div></div></div></CardContent></Card>
        <Card><CardHeader><CardTitle>Khiếu nại nhà cung cấp / vận chuyển ({{ claims.length }})</CardTitle></CardHeader><CardContent><div v-if="!claims.length" class="py-8 text-center text-sm text-muted-foreground">Chưa có hồ sơ khiếu nại.</div><div v-for="row in claims" :key="row.id" class="mb-3 rounded-lg border p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2 font-bold">#{{ row.id }} <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusClass(row.status)">{{ statusLabel(row.status) }}</span></div><div class="mt-1 text-xs text-muted-foreground">{{ row.supplier?.name ?? row.carrier_name ?? 'Chưa xác định bên chịu trách nhiệm' }} · Yêu cầu: {{ row.requested_action ?? '-' }} · Hạn: {{ row.due_at ?? '-' }}</div><p class="mt-2 text-sm">{{ row.reason }}</p></div><div class="flex items-center gap-2"><span class="font-semibold">{{ row.loss_amount }} VND</span><Button v-if="canResolve && ['open', 'investigating'].includes(row.status)" size="sm" variant="outline" @click="openResolve(row)">Đóng hồ sơ</Button></div></div></div></CardContent></Card>
    </div>

    <div v-if="returnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="returnModal = false"><Card class="max-h-[90vh] w-full max-w-xl overflow-auto"><CardHeader><div class="flex items-center justify-between"><CardTitle>Lập phiếu hoàn trả</CardTitle><Button variant="ghost" size="icon" @click="returnModal = false"><X class="size-4" /></Button></div></CardHeader><CardContent class="space-y-4"><div class="rounded-md bg-amber-500/10 p-3 text-sm"><AlertTriangle class="mr-1 inline size-4" /> Chỉ số lượng trong lô cách ly được phép hoàn; tồn khả dụng không bị trừ lần nữa.</div><label class="block text-sm font-medium">Số lượng hoàn<input v-model="returnForm.quantity" type="number" min="0.001" step="0.001" class="mt-1 w-full rounded-md border bg-background p-2" /></label><div class="grid gap-3 md:grid-cols-2"><label class="block text-sm font-medium">Nhà cung cấp<select v-model="returnForm.supplier_id" class="mt-1 w-full rounded-md border bg-background p-2"><option value="">Theo dõi nội bộ / chưa xác định</option><option v-for="supplier in suppliers" :key="supplier.id" :value="String(supplier.id)">{{ supplier.name }}</option></select></label><label class="block text-sm font-medium">Kho đích<select v-model="returnForm.to_branch_id" class="mt-1 w-full rounded-md border bg-background p-2"><option value="">Chưa chọn</option><option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select></label></div><label class="block text-sm font-medium">Lý do<input v-model="returnForm.reason" class="mt-1 w-full rounded-md border bg-background p-2" /></label><label class="block text-sm font-medium">Ghi chú<input v-model="returnForm.notes" class="mt-1 w-full rounded-md border bg-background p-2" /></label><label class="block text-sm font-medium">Bằng chứng<input type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm" @change="setFile(returnForm, $event)" /></label><div class="flex justify-end gap-2"><Button variant="outline" @click="returnModal = false">Hủy</Button><Button @click="submitReturn">Lập phiếu</Button></div></CardContent></Card></div>
    <div v-if="destroyModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="destroyModal = false"><Card class="w-full max-w-xl"><CardHeader><div class="flex items-center justify-between"><CardTitle>Tiêu hủy lô cách ly</CardTitle><Button variant="ghost" size="icon" @click="destroyModal = false"><X class="size-4" /></Button></div></CardHeader><CardContent class="space-y-4"><div class="rounded-md bg-rose-500/10 p-3 text-sm">Thao tác này không thể hoàn tác. Hệ thống sẽ khóa toàn bộ số lượng còn lại và lưu bằng chứng vào nhật ký.</div><label class="block text-sm font-medium">Lý do<input v-model="destroyForm.reason" class="mt-1 w-full rounded-md border bg-background p-2" /></label><label class="block text-sm font-medium">Ảnh/biên bản bắt buộc<input type="file" required accept="image/*,.pdf" class="mt-1 block w-full text-sm" @change="setFile(destroyForm, $event)" /></label><div class="flex justify-end gap-2"><Button variant="outline" @click="destroyModal = false">Hủy</Button><Button variant="destructive" @click="submitDestroy">Xác nhận tiêu hủy</Button></div></CardContent></Card></div>
    <div v-if="completeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="completeModal = false"><Card class="w-full max-w-xl"><CardHeader><div class="flex items-center justify-between"><CardTitle>Chốt giao nhận phiếu hoàn</CardTitle><Button variant="ghost" size="icon" @click="completeModal = false"><X class="size-4" /></Button></div></CardHeader><CardContent class="space-y-4"><label class="block text-sm font-medium">Kết quả<select v-model="completeForm.disposition" class="mt-1 w-full rounded-md border bg-background p-2"><option value="supplier_confirmed">Đã giao và bên nhận xác nhận</option><option value="central_quarantine">Chuyển sang cách ly Kho Tổng</option><option value="destroyed">Tiêu hủy</option></select></label><p v-if="completeForm.disposition === 'central_quarantine'" class="text-xs text-amber-600">Phiếu phải có kho đích là Kho Tổng; nếu chưa chọn khi lập phiếu, hệ thống sẽ từ chối chốt.</p><label class="block text-sm font-medium">Biên bản đối chiếu<input v-model="completeForm.notes" class="mt-1 w-full rounded-md border bg-background p-2" placeholder="Mã biên bản, người nhận, thời điểm..." /></label><label class="block text-sm font-medium">Bằng chứng <span v-if="completeForm.disposition === 'destroyed'">(bắt buộc khi tiêu hủy)</span><input type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm" @change="setFile(completeForm, $event)" /></label><div class="flex justify-end gap-2"><Button variant="outline" @click="completeModal = false">Hủy</Button><Button @click="submitComplete">Chốt phiếu</Button></div></CardContent></Card></div>
    <div v-if="claimModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="claimModal = false"><Card class="max-h-[90vh] w-full max-w-xl overflow-auto"><CardHeader><div class="flex items-center justify-between"><CardTitle>Lập hồ sơ khiếu nại</CardTitle><Button variant="ghost" size="icon" @click="claimModal = false"><X class="size-4" /></Button></div></CardHeader><CardContent class="space-y-4"><div class="grid gap-3 md:grid-cols-2"><label class="block text-sm font-medium">Nhà cung cấp<select v-model="claimForm.supplier_id" class="mt-1 w-full rounded-md border bg-background p-2"><option value="">Không chọn</option><option v-for="supplier in suppliers" :key="supplier.id" :value="String(supplier.id)">{{ supplier.name }}</option></select></label><label class="block text-sm font-medium">Đơn vị vận chuyển<input v-model="claimForm.carrier_name" class="mt-1 w-full rounded-md border bg-background p-2" /></label></div><div class="grid gap-3 md:grid-cols-2"><label class="block text-sm font-medium">Nguồn chứng từ<select v-model="claimForm.source_type" class="mt-1 w-full rounded-md border bg-background p-2"><option value="">Không liên kết</option><option value="inventory_return">Phiếu hoàn trả</option><option value="inventory_quarantine">Lô cách ly</option><option value="stock_transfer">Phiếu điều chuyển</option><option value="supply_request">Đơn cấp phát Kho Tổng</option><option value="warehouse_receiving_voucher">Phiếu nhập hàng</option></select></label><label class="block text-sm font-medium">Mã nguồn<input v-model="claimForm.source_id" type="number" min="1" class="mt-1 w-full rounded-md border bg-background p-2" /></label></div><div class="grid gap-3 md:grid-cols-2"><label class="block text-sm font-medium">Số tiền tổn thất<input v-model="claimForm.loss_amount" type="number" min="0" step="0.01" class="mt-1 w-full rounded-md border bg-background p-2" /></label><label class="block text-sm font-medium">Yêu cầu xử lý<select v-model="claimForm.requested_action" class="mt-1 w-full rounded-md border bg-background p-2"><option value="replacement">Đổi hàng</option><option value="credit">Cấn trừ công nợ</option><option value="refund">Hoàn tiền</option><option value="penalty">Phạt</option><option value="investigate">Xác minh</option></select></label></div><label class="block text-sm font-medium">Lý do<textarea v-model="claimForm.reason" rows="3" class="mt-1 w-full rounded-md border bg-background p-2" /></label><label class="block text-sm font-medium">Hạn phản hồi<input v-model="claimForm.due_at" type="datetime-local" class="mt-1 w-full rounded-md border bg-background p-2" /></label><label class="block text-sm font-medium">Bằng chứng<input type="file" multiple accept="image/*,.pdf" class="mt-1 block w-full text-sm" @change="setClaimFiles" /></label><div class="flex justify-end gap-2"><Button variant="outline" @click="claimModal = false">Hủy</Button><Button @click="submitClaim">Lập khiếu nại</Button></div></CardContent></Card></div>
    <div v-if="resolveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="resolveModal = false"><Card class="w-full max-w-xl"><CardHeader><div class="flex items-center justify-between"><CardTitle>Đóng hồ sơ khiếu nại</CardTitle><Button variant="ghost" size="icon" @click="resolveModal = false"><X class="size-4" /></Button></div></CardHeader><CardContent class="space-y-4"><label class="block text-sm font-medium">Kết quả xử lý<textarea v-model="resolveNotes" rows="4" class="mt-1 w-full rounded-md border bg-background p-2" placeholder="Kết quả làm việc, chứng từ bù trừ/đổi hàng..." /></label><div class="flex justify-end gap-2"><Button variant="outline" @click="resolveModal = false">Hủy</Button><Button @click="submitResolve">Đóng hồ sơ</Button></div></CardContent></Card></div>
</template>
