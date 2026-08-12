<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CheckCircle2,
    ClipboardList,
    Plus,
    Route,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    manifests: Array<any>;
    approvedRequests: Array<any>;
}>();
const isProcessing = ref(false);
const form = ref({
    route_name: 'Tuyến nội thành TP.HCM',
    driver_name: '',
    driver_phone: '',
    vehicle_number: '',
    scheduled_dispatch_at: '',
    notes: '',
    supply_request_ids: [] as number[],
});

const selectedCount = computed(() => form.value.supply_request_ids.length);
const totalRequests = computed(() => props.approvedRequests.length);

const toggleRequest = (id: number) => {
    const index = form.value.supply_request_ids.indexOf(id);
    if (index >= 0) form.value.supply_request_ids.splice(index, 1);
    else form.value.supply_request_ids.push(id);
};

const createManifest = async () => {
    if (!form.value.supply_request_ids.length) {
        toast.error('Hãy chọn ít nhất một đơn đã duyệt để gom chuyến xe.');
        return;
    }
    isProcessing.value = true;
    try {
        await axios.post('/api/delivery-manifests', form.value);
        toast.success('Đã tạo chuyến xe và master packing list.');
        router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể tạo chuyến xe.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const dispatchManifest = async (manifest: any) => {
    const sealCode = prompt(
        'Nhập mã niêm phong của chuyến xe:',
        manifest.seal_code || 'SEAL-',
    );
    if (!sealCode) return;
    isProcessing.value = true;
    try {
        await axios.post(`/api/delivery-manifests/${manifest.id}/dispatch`, {
            seal_code: sealCode,
        });
        toast.success('Đã xuất bến chuyến xe.');
        router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể xuất bến chuyến xe.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const formatDate = (value: string | null | undefined) =>
    value ? new Date(value).toLocaleString('vi-VN') : '-';
</script>

<template>
    <Head title="Chuyến xe Logistics" />
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <div
            class="flex flex-col justify-between gap-4 rounded-2xl bg-gradient-to-r from-violet-950 via-purple-950 to-slate-950 p-6 text-white shadow-xl md:flex-row md:items-center"
        >
            <div>
                <p
                    class="flex items-center gap-2 text-xs font-bold tracking-[0.18em] text-violet-300 uppercase"
                >
                    <Route class="h-4 w-4" /> Vận chuyển liên chi nhánh
                </p>
                <h1 class="mt-2 text-2xl font-bold">Chuyến xe Logistics</h1>
                <p class="mt-1 text-sm text-violet-100/75">
                    Gom đơn, kiểm soát niêm phong và bàn giao hàng từ Kho Tổng
                    đến các chi nhánh.
                </p>
            </div>
            <div
                class="rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm backdrop-blur"
            >
                <div class="text-violet-200/70">Đơn chờ gom</div>
                <div class="mt-1 text-2xl font-bold">{{ totalRequests }}</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[390px_1fr]">
            <Card class="border-violet-500/20">
                <CardHeader
                    class="border-b border-violet-500/10 bg-violet-950/10"
                    ><CardTitle class="flex items-center gap-2 text-base"
                        ><Plus class="h-4 w-4 text-violet-400" /> Tạo chuyến
                        xe</CardTitle
                    ><CardDescription class="text-xs"
                        >Chọn các đơn đã duyệt để tạo một tuyến giao
                        chung.</CardDescription
                    ></CardHeader
                >
                <CardContent class="space-y-3 p-4 text-xs">
                    <Input v-model="form.route_name" placeholder="Tên tuyến" />
                    <div class="grid grid-cols-2 gap-2">
                        <Input
                            v-model="form.driver_name"
                            placeholder="Tên tài xế"
                        /><Input
                            v-model="form.driver_phone"
                            placeholder="Số điện thoại"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <Input
                            v-model="form.vehicle_number"
                            placeholder="Biển số xe"
                        /><Input
                            v-model="form.scheduled_dispatch_at"
                            type="datetime-local"
                        />
                    </div>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="Ghi chú bàn giao..."
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-xs text-foreground outline-none focus:border-violet-500"
                    ></textarea>
                    <div
                        class="rounded-lg border border-violet-500/20 bg-violet-500/5 p-3 text-muted-foreground"
                    >
                        Đã chọn
                        <strong class="text-violet-300">{{
                            selectedCount
                        }}</strong>
                        đơn
                    </div>
                    <Button
                        :disabled="isProcessing"
                        class="w-full gap-2 bg-violet-600 text-white hover:bg-violet-700"
                        @click="createManifest"
                        ><ClipboardList class="h-4 w-4" /> Tạo chuyến xe</Button
                    >
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="border-b bg-muted/20"
                    ><CardTitle class="flex items-center gap-2 text-base"
                        ><Truck class="h-4 w-4 text-violet-400" /> Đơn chờ gom
                        chuyến</CardTitle
                    ><CardDescription class="text-xs"
                        >Chỉ hiển thị đơn đã duyệt hoặc đã soạn
                        hàng.</CardDescription
                    ></CardHeader
                >
                <CardContent class="max-h-[430px] overflow-y-auto p-0">
                    <div
                        v-if="!approvedRequests.length"
                        class="p-8 text-center text-xs text-muted-foreground"
                    >
                        Không có đơn phù hợp để gom chuyến xe.
                    </div>
                    <label
                        v-for="request in approvedRequests"
                        :key="request.id"
                        class="flex cursor-pointer items-center gap-3 border-b border-border p-3 transition hover:bg-violet-500/5"
                    >
                        <input
                            type="checkbox"
                            :checked="
                                form.supply_request_ids.includes(request.id)
                            "
                            class="h-4 w-4 accent-violet-600"
                            @change="toggleRequest(request.id)"
                        />
                        <span class="min-w-0 flex-1"
                            ><span
                                class="font-mono font-bold text-violet-300"
                                >{{ request.request_code }}</span
                            ><span
                                class="mt-0.5 block truncate text-[10px] text-muted-foreground"
                                >{{ request.to_branch?.name }} ·
                                {{ request.items?.length || 0 }} mặt hàng</span
                            ></span
                        >
                        <span
                            class="rounded-full bg-amber-500/10 px-2 py-1 text-[10px] font-semibold text-amber-300"
                            >{{
                                request.status === 'preparing'
                                    ? 'Đã soạn'
                                    : 'Đã duyệt'
                            }}</span
                        >
                    </label>
                </CardContent>
            </Card>
        </div>

        <Card
            ><CardHeader class="border-b bg-muted/20"
                ><CardTitle class="flex items-center gap-2 text-base"
                    ><Truck class="h-4 w-4 text-violet-400" /> Lịch sử chuyến
                    xe</CardTitle
                ></CardHeader
            ><CardContent class="p-0"
                ><div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-xs">
                        <thead
                            class="border-b bg-muted/50 font-semibold text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 pl-4">Mã chuyến</th>
                                <th class="p-3">Tuyến</th>
                                <th class="p-3">Tài xế / Xe</th>
                                <th class="p-3">Niêm phong</th>
                                <th class="p-3">Số đơn</th>
                                <th class="p-3">Trạng thái</th>
                                <th class="p-3 pr-4 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="!manifests.length">
                                <td
                                    colspan="7"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    Chưa có chuyến xe.
                                </td>
                            </tr>
                            <tr
                                v-for="manifest in manifests"
                                :key="manifest.id"
                            >
                                <td
                                    class="p-3 pl-4 font-mono font-bold text-violet-300"
                                >
                                    {{ manifest.manifest_code }}
                                </td>
                                <td class="p-3">
                                    {{ manifest.route_name || '-' }}
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{ manifest.driver_name || '-'
                                    }}<span class="block text-[10px]">{{
                                        manifest.vehicle_number || 'Chưa gán xe'
                                    }}</span>
                                </td>
                                <td class="p-3 font-mono text-violet-300">
                                    {{ manifest.seal_code || '-' }}
                                </td>
                                <td class="p-3">
                                    {{ manifest.items?.length || 0 }}
                                </td>
                                <td class="p-3">
                                    <span
                                        :class="
                                            manifest.status === 'dispatched'
                                                ? 'bg-violet-500/10 text-violet-300'
                                                : 'bg-amber-500/10 text-amber-300'
                                        "
                                        class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                        >{{
                                            manifest.status === 'dispatched'
                                                ? 'Đã xuất bến'
                                                : 'Chờ xuất bến'
                                        }}</span
                                    >
                                </td>
                                <td class="p-3 pr-4 text-right">
                                    <Button
                                        v-if="manifest.status !== 'dispatched'"
                                        size="sm"
                                        :disabled="isProcessing"
                                        class="h-7 bg-violet-600 text-[10px] text-white hover:bg-violet-700"
                                        @click="dispatchManifest(manifest)"
                                        >Xuất bến</Button
                                    ><span
                                        v-else
                                        class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-400"
                                        ><CheckCircle2 class="h-3.5 w-3.5" />
                                        Hoàn tất</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div></CardContent
            ></Card
        >
    </div>
</template>
