<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Plus,
    Settings,
    Wrench,
    TrendingUp,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps<{
    equipment: any[];
    recentLogs: any[];
    stats: {
        total: number;
        active: number;
        broken: number;
        pending_maintenance: number;
        total_cost_ytd: number;
    };
}>();

const page = usePage();
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    },
);

const activeTab = ref<'list' | 'maintenance'>('list');

const catLabel: Record<string, string> = {
    kitchen: 'Bếp gas/Nồi',
    refrigeration: 'Tủ lạnh/Đông',
    cleaning: 'Máy rửa/Vệ sinh',
    pos: 'POS/Camera',
    hvac: 'Điều hòa/Quạt',
    furniture: 'Bàn ghế',
    other: 'Khác',
};
const statusLabel: Record<string, string> = {
    active: 'Hoạt động',
    maintenance: 'Đang bảo trì',
    broken: 'Hỏng',
    retired: 'Thanh lý',
};
const statusColor: Record<string, string> = {
    active: 'bg-green-100 text-green-800',
    maintenance: 'bg-amber-100 text-amber-800',
    broken: 'bg-red-100 text-red-800',
    retired: 'bg-gray-100 text-gray-600',
};
const logTypeLabel: Record<string, string> = {
    scheduled: 'Định kỳ',
    repair: 'Sửa chữa',
    inspection: 'Kiểm tra',
    replacement: 'Thay thế',
};

// Add equipment
const showAddDialog = ref(false);
const addForm = useForm({
    name: '',
    category: 'kitchen' as string,
    brand: '',
    model_number: '',
    serial_number: '',
    purchase_date: '',
    purchase_cost: 0,
    warranty_months: 12,
    location: '',
    notes: '',
});
function submitAdd() {
    addForm.post('/equipment', {
        onSuccess: () => {
            showAddDialog.value = false;
            addForm.reset();
        },
    });
}

// Report issue
const showReportDialog = ref(false);
const reportForm = useForm({
    equipment_id: null as number | null,
    title: '',
    description: '',
    type: 'repair' as string,
    cost: 0,
    scheduled_date: '',
});
function openReport(eqId: number) {
    reportForm.reset();
    reportForm.equipment_id = eqId;
    reportForm.type = 'repair';
    showReportDialog.value = true;
}
function submitReport() {
    reportForm.post('/equipment/report-issue', {
        onSuccess: () => {
            showReportDialog.value = false;
        },
    });
}

function completeLog(logId: number) {
    router.post(`/equipment/logs/${logId}/complete`, {});
}
</script>

<template>
    <Head title="Thiết bị & Bảo trì" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <Settings class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Quản Lý Thiết Bị & Bảo Trì
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Danh sách thiết bị, lịch bảo trì, báo hỏng, chi phí sửa
                        chữa.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    @click="showAddDialog = true"
                    class="flex h-10 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                >
                    <Plus class="size-4" />
                    Thêm thiết bị
                </Button>
            </div>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-5">
            <!-- Total Equipment -->
            <Card
                class="shadow-xs transition-transform hover:translate-y-[-2px]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Tổng thiết bị</CardDescription
                    >
                    <Settings class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-slate-800 dark:text-slate-100"
                        >{{ stats.total }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        thiết bị đã được đăng ký
                    </p>
                </CardContent>
            </Card>

            <!-- Active Equipment -->
            <Card
                class="border-emerald-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Hoạt động</CardDescription
                    >
                    <CheckCircle2
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-emerald-600 dark:text-emerald-400"
                        >{{ stats.active }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        đang vận hành ổn định
                    </p>
                </CardContent>
            </Card>

            <!-- Broken Equipment -->
            <Card
                class="border-rose-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-rose-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                        >Hỏng</CardDescription
                    >
                    <AlertTriangle
                        class="size-4 text-rose-600 dark:text-rose-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-rose-600 dark:text-rose-400"
                        >{{ stats.broken }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        gặp sự cố cần khắc phục
                    </p>
                </CardContent>
            </Card>

            <!-- Pending Maintenance -->
            <Card
                class="border-amber-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-amber-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-amber-500 uppercase"
                        >Chờ bảo trì</CardDescription
                    >
                    <Wrench class="size-4 text-amber-600 dark:text-amber-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-3xl font-black text-amber-600 dark:text-amber-400"
                        >{{ stats.pending_maintenance }}</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        yêu cầu kiểm tra định kỳ
                    </p>
                </CardContent>
            </Card>

            <!-- YTD Cost -->
            <Card
                class="border-indigo-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-indigo-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                        >Chi phí BT (năm)</CardDescription
                    >
                    <TrendingUp
                        class="size-4 text-indigo-600 dark:text-indigo-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-2xl font-black text-indigo-600 dark:text-indigo-400"
                        >{{ stats.total_cost_ytd.toLocaleString() }}đ</span
                    >
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        tổng chi phí sửa chữa YTD
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- CDP sub-navigation (Tab bar) -->
        <div class="flex items-center gap-2 border-b pb-2">
            <button
                v-for="tab in [
                    { key: 'list', label: 'Danh sách thiết bị', icon: '📋' },
                    {
                        key: 'maintenance',
                        label: 'Nhật ký bảo trì',
                        icon: '🔧',
                    },
                ]"
                :key="tab.key"
                type="button"
                @click="activeTab = tab.key as any"
                :class="[
                    'border-b-2 px-4 py-2 text-xs font-bold transition-all focus:outline-none',
                    activeTab === tab.key
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                        : 'hover:border-slate-350 border-transparent text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                ]"
            >
                {{ tab.icon }} {{ tab.label }}
            </button>
        </div>

        <!-- Equipment list -->
        <div v-if="activeTab === 'list'">
            <Card
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md dark:border-slate-800 dark:bg-slate-900/45"
            >
                <CardContent class="p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead
                            class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                        >
                            <tr class="border-b">
                                <th class="p-3.5">Thiết bị</th>
                                <th class="p-3.5">Loại</th>
                                <th class="p-3.5">Trạng thái</th>
                                <th class="p-3.5 text-right">Giá mua</th>
                                <th class="p-3.5 text-right">Chi phí BT</th>
                                <th class="p-3.5">Bảo hành</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="e in equipment"
                                :key="e.id"
                                class="border-b last:border-0 hover:bg-muted/30"
                            >
                                <td class="p-3.5">
                                    <p
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ e.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{ e.brand }} {{ e.model_number }} ·
                                        {{ e.location || '—' }}
                                    </p>
                                </td>
                                <td class="p-3.5">
                                    {{ catLabel[e.category] ?? e.category }}
                                </td>
                                <td class="p-3.5">
                                    <Badge
                                        :class="statusColor[e.status]"
                                        class="text-xs"
                                        >{{ statusLabel[e.status] }}</Badge
                                    >
                                </td>
                                <td class="p-3.5 text-right font-mono">
                                    {{
                                        Number(
                                            e.purchase_cost,
                                        ).toLocaleString()
                                    }}đ
                                </td>
                                <td
                                    class="p-3.5 text-right font-mono font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{
                                        e.total_maintenance_cost.toLocaleString()
                                    }}đ
                                </td>
                                <td class="p-3.5">
                                    <span
                                        v-if="e.under_warranty"
                                        class="font-medium text-green-600"
                                        >Còn BH</span
                                    >
                                    <span
                                        v-else-if="e.warranty_expiry"
                                        class="font-medium text-red-500"
                                        >Hết BH</span
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td class="p-3.5 text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="ml-auto flex h-8 items-center gap-1 text-xs font-semibold text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-950/20"
                                        @click="openReport(e.id)"
                                    >
                                        <Wrench class="size-3.5" /> Báo hỏng
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!equipment.length"
                        class="py-12 text-center text-muted-foreground"
                    >
                        Chưa có thiết bị nào.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Maintenance logs -->
        <div v-if="activeTab === 'maintenance'">
            <Card
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md dark:border-slate-800 dark:bg-slate-900/45"
            >
                <CardContent class="p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead
                            class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                        >
                            <tr class="border-b">
                                <th class="p-3.5">Thiết bị</th>
                                <th class="p-3.5">Loại</th>
                                <th class="p-3.5">Mô tả</th>
                                <th class="p-3.5">Trạng thái</th>
                                <th class="p-3.5 text-right">Chi phí</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="log in recentLogs"
                                :key="log.id"
                                class="border-b last:border-0 hover:bg-muted/30"
                            >
                                <td
                                    class="p-3.5 font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ log.equipment?.name ?? '—' }}
                                </td>
                                <td class="p-3.5">
                                    {{ logTypeLabel[log.type] }}
                                </td>
                                <td class="max-w-xs truncate p-3.5">
                                    {{ log.title }}
                                </td>
                                <td class="p-3.5">
                                    <Badge
                                        :variant="
                                            log.status === 'completed'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        class="text-xs"
                                    >
                                        {{
                                            log.status === 'completed'
                                                ? 'Xong'
                                                : log.status === 'pending'
                                                  ? 'Chờ'
                                                  : log.status
                                        }}
                                    </Badge>
                                </td>
                                <td
                                    class="p-3.5 text-right font-mono font-bold"
                                >
                                    {{ Number(log.cost).toLocaleString() }}đ
                                </td>
                                <td class="p-3.5 text-right">
                                    <Button
                                        v-if="log.status !== 'completed'"
                                        variant="ghost"
                                        size="sm"
                                        class="ml-auto flex h-8 items-center gap-1 text-xs font-semibold text-green-600 hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-950/20"
                                        @click="completeLog(log.id)"
                                    >
                                        <CheckCircle2 class="size-3.5" /> Xong
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!recentLogs.length"
                        class="py-12 text-center text-muted-foreground"
                    >
                        Chưa có nhật ký bảo trì.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Add equipment dialog -->
    <Dialog v-model:open="showAddDialog">
        <DialogContent class="max-h-[80vh] max-w-lg overflow-y-auto">
            <DialogHeader
                ><DialogTitle>Thêm Thiết Bị</DialogTitle></DialogHeader
            >
            <form @submit.prevent="submitAdd" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Tên thiết bị</Label
                        ><Input v-model="addForm.name" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select
                            v-model="addForm.category"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="kitchen">Bếp gas/Nồi</option>
                            <option value="refrigeration">Tủ lạnh/Đông</option>
                            <option value="cleaning">Máy rửa</option>
                            <option value="pos">POS/Camera</option>
                            <option value="hvac">Điều hòa</option>
                            <option value="furniture">Bàn ghế</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Hãng</Label><Input v-model="addForm.brand" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Model</Label
                        ><Input v-model="addForm.model_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Serial</Label
                        ><Input v-model="addForm.serial_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Vị trí</Label
                        ><Input
                            v-model="addForm.location"
                            placeholder="Bếp chính, Quầy bar..."
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Ngày mua</Label
                        ><Input type="date" v-model="addForm.purchase_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giá mua (VND)</Label
                        ><Input type="number" v-model="addForm.purchase_cost" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Bảo hành (tháng)</Label
                        ><Input
                            type="number"
                            v-model="addForm.warranty_months"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="showAddDialog = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="addForm.processing">{{
                        addForm.processing ? 'Đang thêm...' : 'Thêm thiết bị'
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Report issue dialog -->
    <Dialog v-model:open="showReportDialog">
        <DialogContent class="max-w-md">
            <DialogHeader
                ><DialogTitle class="flex items-center gap-2"
                    ><AlertTriangle class="size-5 text-amber-500" /> Báo Hỏng /
                    Bảo Trì</DialogTitle
                ></DialogHeader
            >
            <form @submit.prevent="submitReport" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Tiêu đề</Label
                    ><Input
                        v-model="reportForm.title"
                        placeholder="Tủ lạnh không lạnh..."
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>Mô tả chi tiết</Label
                    ><Input v-model="reportForm.description" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select
                            v-model="reportForm.type"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="repair">Sửa chữa</option>
                            <option value="scheduled">Bảo trì định kỳ</option>
                            <option value="inspection">Kiểm tra</option>
                            <option value="replacement">Thay thế</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Chi phí dự kiến</Label
                        ><Input type="number" v-model="reportForm.cost" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Ngày lên lịch</Label
                    ><Input type="date" v-model="reportForm.scheduled_date" />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="showReportDialog = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="reportForm.processing"
                        >Gửi báo cáo</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
