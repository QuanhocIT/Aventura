<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2, Pencil, Plus, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import BackButton from '@/components/BackButton.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Branch = {
    id: number;
    code: string;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    status: 'active' | 'inactive';
    manager_user_id: number | null;
    manager_name: string | null;
    is_central_warehouse?: boolean;
    warehouse_type?: string;
    employees_count: number;
    tables_count: number;
};

type ManagerCandidate = {
    id: number;
    name: string;
    assigned_branch_name: string | null;
};

const props = defineProps<{
    branches: Branch[];
    managerCandidates?: ManagerCandidate[];
    limit: number | null;
    canAddMore: boolean;
}>();

const showForm = ref(false);
const editingBranch = ref<Branch | null>(null);

function openCreate() {
    editingBranch.value = null;
    showForm.value = true;
}

function openEdit(branch: Branch) {
    editingBranch.value = branch;
    showForm.value = true;
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Chi nhánh', href: '/settings/branches' }],
    },
});
</script>

<template>
    <Head title="Quản lý chi nhánh" />

    <div class="w-full space-y-6">
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="flex flex-row items-center justify-between gap-4 border-b border-neutral-100 px-6 pt-6 pb-5 dark:border-neutral-800"
            >
                <div class="flex items-center gap-3">
                    <BackButton
                        fallback-href="/settings/profile"
                        label="Cài đặt"
                    />
                    <div
                        class="shrink-0 rounded-xl bg-neutral-100 p-2.5 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                    >
                        <Building2 class="h-5 w-5" />
                    </div>
                    <div class="space-y-0.5">
                        <CardTitle
                            class="text-lg font-black text-neutral-900 dark:text-neutral-50"
                            >Chi nhánh</CardTitle
                        >
                        <CardDescription
                            class="text-xs text-neutral-500 dark:text-neutral-400"
                            >Quản lý các chi nhánh của nhà hàng
                            {{
                                limit !== null
                                    ? `(${branches.length}/${limit} theo gói cước)`
                                    : `(${branches.length} chi nhánh)`
                            }}</CardDescription
                        >
                    </div>
                </div>
                <Button
                    :disabled="!canAddMore"
                    class="cursor-pointer gap-1.5 rounded-xl bg-neutral-900 px-4 text-xs font-bold tracking-wider text-white uppercase shadow-sm hover:bg-neutral-800 disabled:opacity-40 dark:bg-neutral-50 dark:text-neutral-950 dark:hover:bg-neutral-200"
                    @click="openCreate"
                >
                    <Plus class="size-4" /> Thêm chi nhánh
                </Button>
            </CardHeader>
            <CardContent class="p-6 lg:p-8">
                <p
                    v-if="!canAddMore"
                    class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-400"
                >
                    Bạn đã đạt giới hạn số chi nhánh của gói cước hiện tại. Vui
                    lòng nâng cấp gói để thêm chi nhánh mới.
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div
                        v-for="branch in branches"
                        :key="branch.id"
                        class="rounded-2xl border border-neutral-200 p-4 dark:border-neutral-800"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p
                                        class="font-mono text-[10px] font-bold tracking-wider text-neutral-400 uppercase"
                                    >
                                        {{ branch.code }}
                                    </p>
                                    <span
                                        v-if="
                                            branch.is_central_warehouse ||
                                            branch.warehouse_type === 'central'
                                        "
                                        class="rounded-md border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                                    >
                                        🏬 Kho Tổng
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-md border border-sky-500/30 bg-sky-500/10 px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-sky-600 uppercase dark:text-sky-400"
                                    >
                                        🍽️ Chi nhánh bán hàng
                                    </span>
                                </div>
                                <p
                                    class="mt-1 text-sm font-bold text-neutral-900 dark:text-neutral-50"
                                >
                                    {{ branch.name }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                                :class="
                                    branch.status === 'active'
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                        : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400'
                                "
                            >
                                {{
                                    branch.status === 'active'
                                        ? 'Hoạt động'
                                        : 'Tạm ngưng'
                                }}
                            </span>
                        </div>

                        <p
                            v-if="branch.address"
                            class="mt-2 text-xs text-neutral-500 dark:text-neutral-400"
                        >
                            {{ branch.address }}
                        </p>

                        <div
                            class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400"
                        >
                            <span class="flex items-center gap-1"
                                ><Users class="size-3.5" />
                                {{ branch.employees_count }} nhân viên</span
                            >
                            <span
                                v-if="branch.manager_name"
                                class="flex items-center gap-1 font-semibold text-neutral-800 dark:text-neutral-200"
                            >
                                👤 QL: {{ branch.manager_name }}
                            </span>
                            <span
                                v-else
                                class="flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400"
                            >
                                ⚠️ Chưa có quản lý
                            </span>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 cursor-pointer gap-1 rounded-lg text-xs"
                                @click="openEdit(branch)"
                            >
                                <Pencil class="size-3.5" /> Sửa thông tin
                            </Button>
                        </div>
                    </div>
                </div>

                <p
                    v-if="branches.length === 0"
                    class="py-10 text-center text-xs text-neutral-500"
                >
                    Chưa có chi nhánh nào.
                </p>
            </CardContent>
        </Card>

        <Dialog v-model:open="showForm">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editingBranch ? 'Sửa chi nhánh' : 'Thêm chi nhánh mới'
                    }}</DialogTitle>
                </DialogHeader>

                <Form
                    :method="editingBranch ? 'patch' : 'post'"
                    :action="
                        editingBranch
                            ? `/settings/branches/${editingBranch.id}`
                            : '/settings/branches'
                    "
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                    @success="showForm = false"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label
                                for="code"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Mã chi nhánh
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="code"
                                name="code"
                                :default-value="editingBranch?.code ?? ''"
                                required
                                placeholder="CN02"
                                class="rounded-xl"
                            />
                            <InputError :message="errors.code" />
                        </div>
                        <div v-if="editingBranch" class="grid gap-2">
                            <Label
                                for="status"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Trạng thái</Label
                            >
                            <Select
                                name="status"
                                :default-value="
                                    editingBranch?.status ?? 'active'
                                "
                            >
                                <SelectTrigger class="rounded-xl">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active"
                                        >Hoạt động</SelectItem
                                    >
                                    <SelectItem value="inactive"
                                        >Tạm ngưng</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.status" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="name"
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Tên chi nhánh
                            <span class="text-rose-500">*</span></Label
                        >
                        <Input
                            id="name"
                            name="name"
                            :default-value="editingBranch?.name ?? ''"
                            required
                            placeholder="Chi nhánh Quận 3"
                            class="rounded-xl"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <!-- Mô hình chi nhánh: Cho phép chọn khi tạo mới, Cố định khi sửa -->
                    <div v-if="!editingBranch" class="grid gap-2">
                        <Label
                            for="warehouse_type"
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Mô hình chi nhánh
                            <span class="text-rose-500">*</span></Label
                        >
                        <Select
                            name="warehouse_type"
                            default-value="business"
                        >
                            <SelectTrigger class="rounded-xl">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="business">
                                    🍽️ Chi nhánh bán hàng (Nhà hàng / Điểm bán)
                                </SelectItem>
                                <SelectItem value="central">
                                    🏬 Kho Tổng chuỗi (Trung tâm điều phối & lưu trữ)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-[11px] leading-relaxed text-neutral-500 dark:text-neutral-400">
                            • <b>Chi nhánh bán hàng:</b> Có bàn ăn, menu gọi món, POS thu ngân và gửi yêu cầu cấp hàng.<br />
                            • <b>Kho Tổng chuỗi:</b> Nhập hàng từ NCC, xuất kho cấp phát cho toàn chuỗi, điều phối logistics.
                        </p>
                        <InputError :message="errors.warehouse_type" />
                    </div>

                    <div v-else class="grid gap-2">
                        <Label
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Mô hình chi nhánh</Label
                        >
                        <div
                            class="flex items-center justify-between rounded-xl border border-neutral-200 bg-neutral-50/80 px-3.5 py-2.5 text-xs font-semibold text-neutral-800 dark:border-neutral-800 dark:bg-neutral-900/50 dark:text-neutral-200"
                        >
                            <span
                                v-if="
                                    editingBranch.is_central_warehouse ||
                                    editingBranch.warehouse_type === 'central'
                                "
                                class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400"
                            >
                                🏬 <span>Kho Tổng chuỗi (Trung tâm điều phối & lưu trữ)</span>
                            </span>
                            <span
                                v-else
                                class="flex items-center gap-1.5 text-sky-600 dark:text-sky-400"
                            >
                                🍽️ <span>Chi nhánh bán hàng (Nhà hàng / Điểm bán)</span>
                            </span>
                            <span
                                class="rounded bg-neutral-200/80 px-1.5 py-0.5 text-[10px] font-bold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
                                >Cố định</span
                            >
                        </div>
                        <p class="text-[11px] text-neutral-400 dark:text-neutral-500">
                            🔒 Mô hình chi nhánh được thiết lập cố định khi tạo để bảo toàn toàn vẹn dữ liệu vận hành và tồn kho.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label
                                for="phone"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Điện thoại</Label
                            >
                            <Input
                                id="phone"
                                name="phone"
                                :default-value="editingBranch?.phone ?? ''"
                                class="rounded-xl"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label
                                for="email"
                                class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                                >Email</Label
                            >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                :default-value="editingBranch?.email ?? ''"
                                class="rounded-xl"
                            />
                            <InputError :message="errors.email" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="address"
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Địa chỉ</Label
                        >
                        <Input
                            id="address"
                            name="address"
                            :default-value="editingBranch?.address ?? ''"
                            class="rounded-xl"
                        />
                        <InputError :message="errors.address" />
                    </div>

                    <div class="grid gap-2">
                        <Label
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Quản lý chi nhánh</Label
                        >
                        <div
                            v-if="editingBranch?.manager_name"
                            class="flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3.5 py-2.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300"
                        >
                            <span class="flex items-center gap-2">
                                <span class="text-base">👤</span>
                                <span>{{ editingBranch.manager_name }}</span>
                            </span>
                            <span
                                class="rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                Đang phụ trách
                            </span>
                        </div>
                        <div
                            v-else
                            class="flex items-center justify-between rounded-xl border border-amber-500/20 bg-amber-500/5 px-3.5 py-2.5 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
                        >
                            <span class="flex items-center gap-1.5">
                                <span>⚠️</span>
                                <span>Chưa có quản lý chi nhánh</span>
                            </span>
                            <span class="text-[11px] text-neutral-400">Chưa gán</span>
                        </div>
                        <p class="text-[11px] leading-relaxed text-neutral-400 dark:text-neutral-500">
                            💡 Quản lý chi nhánh được tạo và phân quyền trực tiếp tại mục <b>Nhân sự & Hiệu suất</b>.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            :disabled="processing"
                            class="cursor-pointer rounded-xl bg-neutral-900 px-6 text-xs font-bold tracking-wider text-white uppercase dark:bg-neutral-50 dark:text-neutral-950"
                        >
                            {{ processing ? 'Đang lưu...' : 'Lưu chi nhánh' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
