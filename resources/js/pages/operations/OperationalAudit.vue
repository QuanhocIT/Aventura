<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertOctagon,
    Clock,
    DollarSign,
    Gavel,
    Plus,
    ShieldAlert,
    UserCheck,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    reports: Array<any>;
    policies: Array<any>;
    branches: Array<any>;
    employees: Array<any>;
    isOwner: boolean;
    isInspector: boolean;
}>();

const isCreateModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const isProcessing = ref(false);
const selectedReport = ref<any>(null);
const ownerNotes = ref('');

const reportForm = ref({
    branch_id: props.branches[0]?.id || null,
    policy_id: null as number | null,
    offender_user_id: null as number | null,
    infringement_date: new Date().toISOString().split('T')[0],
    description: '',
    proof_photo_url: '',
    proof_photo: null as File | null,
    penalty_amount: 0,
});
const proofFileName = ref('');

const onPolicySelect = () => {
    if (!reportForm.value.policy_id) {
        return;
    }

    const found = props.policies.find(
        (p) => p.id === reportForm.value.policy_id,
    );

    if (found) {
        reportForm.value.penalty_amount = found.suggested_fine_amount || 0;
    }
};

const openCreateModal = () => {
    reportForm.value = {
        branch_id: props.branches[0]?.id || null,
        policy_id: null,
        offender_user_id: null,
        infringement_date: new Date().toISOString().split('T')[0],
        description: '',
        proof_photo_url: '',
        proof_photo: null,
        penalty_amount: 0,
    };
    proofFileName.value = '';
    isCreateModalOpen.value = true;
};

const openDetailModal = (r: any) => {
    selectedReport.value = r;
    ownerNotes.value = r.owner_notes || '';
    isDetailModalOpen.value = true;
};

const submitReport = async () => {
    if (!reportForm.value.branch_id || !reportForm.value.description.trim()) {
        toast.error(
            'Vui lòng chọn Chi nhánh và Nhập mô tả chi tiết hành vi vi phạm.',
        );

        return;
    }

    isProcessing.value = true;

    try {
        const payload = new FormData();
        payload.append('branch_id', String(reportForm.value.branch_id));
        payload.append('infringement_date', reportForm.value.infringement_date);
        payload.append('description', reportForm.value.description);
        payload.append(
            'penalty_amount',
            String(reportForm.value.penalty_amount || 0),
        );

        if (reportForm.value.policy_id) {
            payload.append('policy_id', String(reportForm.value.policy_id));
        }

        if (reportForm.value.offender_user_id) {
            payload.append(
                'offender_user_id',
                String(reportForm.value.offender_user_id),
            );
        }

        if (reportForm.value.proof_photo_url) {
            payload.append('proof_photo_url', reportForm.value.proof_photo_url);
        }

        if (reportForm.value.proof_photo) {
            payload.append('proof_photo', reportForm.value.proof_photo);
        }

        const res = await axios.post(
            '/api/operational-audit/reports',
            payload,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            },
        );

        if (res.data.success) {
            toast.success(
                'Đã lập Biên bản Vi phạm và gửi trình Chủ doanh nghiệp phê duyệt!',
            );
            isCreateModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi tạo biên bản.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const onProofPhotoChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    reportForm.value.proof_photo = file;
    proofFileName.value = file?.name ?? '';
};

const approveReport = async () => {
    if (!selectedReport.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/operational-audit/reports/${selectedReport.value.id}/approve`,
            {
                owner_notes: ownerNotes.value,
            },
        );

        if (res.data.success) {
            toast.success('Đã phê duyệt Biên bản phạt!');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi duyệt biên bản.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const rejectReport = async () => {
    if (!selectedReport.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/operational-audit/reports/${selectedReport.value.id}/reject`,
            {
                owner_notes: ownerNotes.value,
            },
        );

        if (res.data.success) {
            toast.success('Đã từ chối biên bản phạt.');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi từ chối.');
    } finally {
        isProcessing.value = false;
    }
};

const pendingCount = computed(() => {
    return props.reports.filter((r) => r.status === 'pending_owner_approval')
        .length;
});

const totalPenaltyApproved = computed(() => {
    return props.reports
        .filter((r) => r.status === 'approved')
        .reduce((sum, r) => sum + Number(r.penalty_amount || 0), 0);
});

const rejectedCount = computed(
    () => props.reports.filter((r) => r.status === 'rejected').length,
);

const getStatusMeta = (status: string) => {
    switch (status) {
        case 'pending_owner_approval':
            return {
                label: 'Chờ duyệt',
                className:
                    'border-amber-200/70 bg-amber-500/10 text-amber-700 dark:border-amber-500/20 dark:text-amber-300',
            };
        case 'approved':
            return {
                label: 'Đã phê duyệt',
                className:
                    'border-emerald-200/70 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/20 dark:text-emerald-300',
            };
        default:
            return {
                label: 'Đã từ chối',
                className: 'border-border bg-muted text-muted-foreground',
            };
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount || 0);
};

const formatDate = (dt: string) => {
    if (!dt) {
        return '-';
    }

    return new Date(dt).toLocaleDateString('vi-VN');
};
</script>

<template>
    <Head title="Giám Sát Vận Hành & Biên Bản Phạt" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <section
            class="relative overflow-hidden rounded-3xl border border-rose-200/70 bg-gradient-to-br from-rose-50 via-background to-indigo-50 px-5 py-5 shadow-sm md:px-7 md:py-6 dark:border-rose-500/20 dark:from-rose-950/50 dark:via-background dark:to-indigo-950/20"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-20 size-64 rounded-full bg-rose-500/10 blur-3xl dark:bg-rose-500/20"
            />
            <div
                class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-rose-600 text-white shadow-lg shadow-rose-600/20"
                    >
                        <ShieldAlert class="size-6" />
                    </div>
                    <div>
                        <p
                            class="mb-1 text-[10px] font-bold tracking-[0.18em] text-rose-600 uppercase dark:text-rose-300"
                        >
                            Kiểm soát tuân thủ
                        </p>
                        <h1
                            class="text-2xl font-black tracking-tight text-foreground md:text-3xl"
                        >
                            Giám Sát Vận Hành & Biên Bản Phạt
                        </h1>
                        <p
                            class="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground"
                        >
                            Theo dõi việc tuân thủ tại các chi nhánh và trình
                            chủ doanh nghiệp phê duyệt mức phạt.
                        </p>
                    </div>
                </div>

                <Button
                    v-if="isInspector"
                    @click="openCreateModal"
                    class="h-10 shrink-0 gap-2 rounded-xl bg-rose-600 px-4 text-xs font-bold text-white shadow-md shadow-rose-600/20 hover:bg-rose-700"
                >
                    <Plus class="size-4" /> Lập biên bản mới
                </Button>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="border-amber-200/60 dark:border-amber-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-amber-600 uppercase dark:text-amber-300"
                        >Chờ chủ duyệt</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300"
                    >
                        <Clock class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ pendingCount }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        cần được xử lý
                    </p></CardContent
                >
            </Card>
            <Card class="border-rose-200/60 dark:border-rose-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-rose-600 uppercase dark:text-rose-300"
                        >Tổng tiền phạt</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                    >
                        <DollarSign class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="truncate text-2xl font-black text-foreground">
                        {{ formatCurrency(totalPenaltyApproved) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        đã được phê duyệt
                    </p></CardContent
                >
            </Card>
            <Card class="border-indigo-200/60 dark:border-indigo-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-300"
                        >Tổng biên bản</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"
                    >
                        <Gavel class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ reports.length }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        đã ghi nhận trong hệ thống
                    </p></CardContent
                >
            </Card>
            <Card class="border-slate-200/70 dark:border-slate-700/50">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                        >Đã từ chối</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                    >
                        <XCircle class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ rejectedCount }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        biên bản không được duyệt
                    </p></CardContent
                >
            </Card>
        </div>

        <section
            class="overflow-hidden rounded-3xl border border-border/70 bg-card/90 shadow-sm"
        >
            <div
                class="flex flex-col gap-1 border-b border-border/60 bg-muted/15 px-5 py-4 sm:flex-row sm:items-end sm:justify-between md:px-6"
            >
                <div>
                    <h2
                        class="text-lg font-bold tracking-tight text-foreground"
                    >
                        Danh sách biên bản vi phạm
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Theo dõi tiến độ lập biên bản và trạng thái phê duyệt
                        của chủ doanh nghiệp.
                    </p>
                </div>
                <span class="text-xs font-semibold text-muted-foreground"
                    >{{ reports.length }} biên bản</span
                >
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-xs">
                    <thead
                        class="border-b border-border/60 bg-muted/35 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">Mã biên bản</th>
                            <th class="px-3 py-3">Chi nhánh</th>
                            <th class="px-3 py-3">Quy định vi phạm</th>
                            <th class="px-3 py-3">Đối tượng</th>
                            <th class="px-3 py-3">Ngày vi phạm</th>
                            <th class="px-3 py-3 text-right">Tiền phạt</th>
                            <th class="px-3 py-3">Trạng thái</th>
                            <th class="px-5 py-3 text-right">Hành động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/50">
                        <tr v-if="reports.length === 0">
                            <td colspan="8" class="px-5 py-16 text-center">
                                <div
                                    class="mx-auto flex max-w-sm flex-col items-center"
                                >
                                    <div
                                        class="mb-3 flex size-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                                    >
                                        <ShieldAlert class="size-6" />
                                    </div>
                                    <p class="font-semibold text-foreground">
                                        Chưa có biên bản vi phạm
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Các biên bản được lập tại chi nhánh sẽ
                                        hiển thị ở đây.
                                    </p>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="r in reports"
                            :key="r.id"
                            class="transition hover:bg-muted/25"
                        >
                            <td
                                class="px-5 py-4 font-mono font-bold text-rose-600 dark:text-rose-300"
                            >
                                {{ r.report_code }}
                            </td>
                            <td class="px-3 py-4 font-semibold text-foreground">
                                {{ r.branch?.name }}
                            </td>
                            <td
                                class="max-w-[220px] truncate px-3 py-4 text-muted-foreground"
                            >
                                {{
                                    r.policy
                                        ? r.policy.title
                                        : 'Vi phạm tổng hợp'
                                }}
                            </td>
                            <td class="px-3 py-4 text-muted-foreground">
                                {{
                                    r.offender
                                        ? r.offender.name
                                        : 'Tập thể chi nhánh'
                                }}
                            </td>
                            <td
                                class="px-3 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDate(r.infringement_date) }}
                            </td>
                            <td
                                class="px-3 py-4 text-right font-mono font-bold whitespace-nowrap text-rose-600 dark:text-rose-300"
                            >
                                {{ formatCurrency(r.penalty_amount) }}
                            </td>
                            <td class="p-3">
                                <span
                                    :class="[
                                        'inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                        getStatusMeta(r.status).className,
                                    ]"
                                    >{{ getStatusMeta(r.status).label }}</span
                                >
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Button
                                    @click="openDetailModal(r)"
                                    size="sm"
                                    variant="outline"
                                    class="h-8 gap-1 rounded-lg text-xs"
                                >
                                    Xem chi tiết
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Create Report Modal (Inspector) -->
        <div
            v-if="isCreateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-border bg-background text-foreground shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border bg-muted/30 p-5"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                        >
                            <AlertOctagon class="size-5" />
                        </div>
                        <h3 class="text-sm font-bold">
                            Lập biên bản vi phạm & phạt vận hành
                        </h3>
                    </div>
                    <button
                        @click="isCreateModalOpen = false"
                        class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="max-h-[75vh] space-y-5 overflow-y-auto p-6 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="mb-1.5 block font-semibold text-foreground"
                                >Chi nhánh vi phạm (*)</label
                            >
                            <select
                                v-model="reportForm.branch_id"
                                class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-rose-500/30"
                            >
                                <option
                                    v-for="b in branches"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block font-semibold text-foreground"
                                >Ngày phát hiện vi phạm</label
                            >
                            <Input
                                v-model="reportForm.infringement_date"
                                type="date"
                                class="text-xs"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Điều khoản / quy định vi phạm (nếu có)</label
                        >
                        <select
                            v-model="reportForm.policy_id"
                            @change="onPolicySelect"
                            class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-rose-500/30"
                        >
                            <option :value="null">
                                -- Lỗi vi phạm thực tế tự do --
                            </option>
                            <option
                                v-for="p in policies"
                                :key="p.id"
                                :value="p.id"
                            >
                                [{{ p.policy_code }}] {{ p.title }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Cá nhân vi phạm
                            <span class="font-normal text-muted-foreground"
                                >(để trống nếu phạt tập thể chi nhánh)</span
                            ></label
                        >
                        <select
                            v-model="reportForm.offender_user_id"
                            class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-rose-500/30"
                        >
                            <option :value="null">
                                -- Phạt Tập Thể Chi Nhánh --
                            </option>
                            <option
                                v-for="emp in employees"
                                :key="emp.id"
                                :value="emp.id"
                            >
                                {{ emp.name }} ({{ emp.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Mô tả chi tiết hành vi vi phạm (*)</label
                        >
                        <textarea
                            v-model="reportForm.description"
                            rows="4"
                            placeholder="Mô tả cụ thể diễn biến vi phạm, hình ảnh ghi nhận và các yếu tố liên quan..."
                            class="min-h-28 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"
                        ></textarea>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Ảnh bằng chứng vi phạm</label
                        >
                        <input
                            type="file"
                            accept="image/*"
                            @change="onProofPhotoChange"
                            class="block w-full cursor-pointer rounded-xl border border-input bg-background p-2.5 text-xs text-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-rose-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-rose-700"
                        />
                        <p
                            v-if="proofFileName"
                            class="mt-1.5 text-[11px] font-medium text-muted-foreground"
                        >
                            Đã chọn: {{ proofFileName }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Mức phạt đề xuất trình chủ doanh nghiệp
                            (VNĐ)</label
                        >
                        <Input
                            v-model.number="reportForm.penalty_amount"
                            type="number"
                            step="50000"
                            class="text-xs font-bold text-rose-600 dark:text-rose-300"
                        />
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-border bg-muted/20 p-4"
                >
                    <Button
                        @click="isCreateModalOpen = false"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        >Hủy</Button
                    >
                    <Button
                        @click="submitReport"
                        size="sm"
                        :disabled="isProcessing"
                        class="gap-1.5 rounded-xl bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700"
                    >
                        <Gavel class="size-4" /> Gửi trình duyệt
                    </Button>
                </div>
            </div>
        </div>

        <!-- Detail / Approve Report Modal -->
        <div
            v-if="isDetailModalOpen && selectedReport"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-border bg-background text-foreground shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border bg-muted/30 p-5"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-300"
                        >
                            <Gavel class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">
                                Chi tiết biên bản vi phạm
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Mã biên bản:
                                <span
                                    class="font-mono font-bold text-rose-600 dark:text-rose-300"
                                    >{{ selectedReport.report_code }}</span
                                >
                            </p>
                        </div>
                    </div>
                    <button
                        @click="isDetailModalOpen = false"
                        class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="max-h-[75vh] space-y-5 overflow-y-auto p-6 text-xs">
                    <div
                        class="space-y-2 rounded-2xl border border-border bg-muted/20 p-4 text-muted-foreground"
                    >
                        <div>
                            <strong>Chi nhánh vi phạm:</strong>
                            {{ selectedReport.branch?.name }}
                        </div>
                        <div>
                            <strong>Giám sát viên lập:</strong>
                            {{ selectedReport.inspector?.name }}
                        </div>
                        <div>
                            <strong>Đối tượng vi phạm:</strong>
                            {{
                                selectedReport.offender
                                    ? selectedReport.offender.name
                                    : 'Tập thể Chi nhánh'
                            }}
                        </div>
                        <div>
                            <strong>Điều khoản vi phạm:</strong>
                            {{
                                selectedReport.policy
                                    ? selectedReport.policy.title
                                    : 'Lỗi vận hành thực tế'
                            }}
                        </div>
                        <div>
                            <strong>Mức phạt đề xuất:</strong>
                            <strong
                                class="ml-1 font-mono text-sm font-bold text-rose-600 dark:text-rose-300"
                                >{{
                                    formatCurrency(
                                        selectedReport.penalty_amount,
                                    )
                                }}</strong
                            >
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Mô tả diễn biến vi phạm</label
                        >
                        <div
                            class="rounded-xl border border-border bg-muted/30 p-3 leading-relaxed whitespace-pre-line text-foreground"
                        >
                            {{ selectedReport.description }}
                        </div>
                    </div>

                    <div v-if="selectedReport.proof_photo_url">
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Bằng chứng hình ảnh</label
                        >
                        <a
                            :href="selectedReport.proof_photo_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex rounded-xl border border-rose-200/70 bg-rose-500/10 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-500/15 dark:border-rose-500/20 dark:text-rose-300"
                        >
                            Xem ảnh bằng chứng
                        </a>
                    </div>

                    <div
                        v-if="
                            isOwner &&
                            selectedReport.status === 'pending_owner_approval'
                        "
                    >
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Ý kiến & chỉ đạo của chủ doanh nghiệp</label
                        >
                        <textarea
                            v-model="ownerNotes"
                            rows="3"
                            placeholder="Nhập ghi chú chỉ đạo hoặc lý do từ chối biên bản phạt..."
                            class="min-h-24 w-full rounded-xl border border-input bg-background p-3 text-xs text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-rose-500/30"
                        ></textarea>
                    </div>

                    <div
                        v-else-if="selectedReport.owner_notes"
                        class="rounded-xl border border-indigo-200/60 bg-indigo-500/5 p-3 text-indigo-700 dark:border-indigo-500/20 dark:text-indigo-300"
                    >
                        <strong>Ý kiến Chủ doanh nghiệp:</strong>
                        {{ selectedReport.owner_notes }}
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-border bg-muted/20 p-4"
                >
                    <Button
                        @click="isDetailModalOpen = false"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        >Đóng</Button
                    >

                    <div
                        v-if="
                            isOwner &&
                            selectedReport.status === 'pending_owner_approval'
                        "
                        class="flex gap-2"
                    >
                        <Button
                            @click="rejectReport"
                            size="sm"
                            variant="outline"
                            :disabled="isProcessing"
                            class="gap-1 rounded-xl border-rose-200/70 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700 dark:border-rose-500/20 dark:text-rose-300"
                        >
                            <XCircle class="size-4" /> Từ chối
                        </Button>
                        <Button
                            @click="approveReport"
                            size="sm"
                            :disabled="isProcessing"
                            class="gap-1.5 rounded-xl bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                        >
                            <UserCheck class="size-4" /> Phê duyệt
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
