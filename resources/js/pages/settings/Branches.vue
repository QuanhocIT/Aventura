<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Building2, Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
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
import { confirmDialog } from '@/composables/useConfirm';

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
    managerCandidates: ManagerCandidate[];
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

async function destroyBranch(branch: Branch) {
    const ok = await confirmDialog({
        title: `Xoá chi nhánh "${branch.name}"?`,
        description:
            'Hành động này không thể hoàn tác. Chi nhánh phải hết nhân viên trước khi xoá được.',
        confirmText: 'Xoá chi nhánh',
    });

    if (!ok) {
        return;
    }

    router.delete(`/settings/branches/${branch.id}`, {
        preserveScroll: true,
    });
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
                    <BackButton fallback-href="/settings/profile" label="Cài đặt" />
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
                                <p
                                    class="font-mono text-[10px] font-bold tracking-wider text-neutral-400 uppercase"
                                >
                                    {{ branch.code }}
                                </p>
                                <p
                                    class="text-sm font-bold text-neutral-900 dark:text-neutral-50"
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
                            class="mt-3 flex items-center gap-4 text-xs text-neutral-500 dark:text-neutral-400"
                        >
                            <span class="flex items-center gap-1"
                                ><Users class="size-3.5" />
                                {{ branch.employees_count }} nhân viên</span
                            >
                            <span v-if="branch.manager_name"
                                >QL: {{ branch.manager_name }}</span
                            >
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 cursor-pointer gap-1 rounded-lg text-xs"
                                @click="openEdit(branch)"
                            >
                                <Pencil class="size-3.5" /> Sửa
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 cursor-pointer gap-1 rounded-lg text-xs text-rose-500 hover:bg-rose-500/10 hover:text-rose-600"
                                @click="destroyBranch(branch)"
                            >
                                <Trash2 class="size-3.5" /> Xoá
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
                            for="manager_user_id"
                            class="text-xs font-bold tracking-wider text-neutral-500 uppercase"
                            >Quản lý chi nhánh</Label
                        >
                        <Select
                            name="manager_user_id"
                            :default-value="
                                editingBranch?.manager_user_id
                                    ? String(editingBranch.manager_user_id)
                                    : '0'
                            "
                        >
                            <SelectTrigger class="rounded-xl">
                                <SelectValue placeholder="Chưa gán quản lý" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="0"
                                    >Chưa gán quản lý</SelectItem
                                >
                                <SelectItem
                                    v-for="manager in managerCandidates"
                                    :key="manager.id"
                                    :value="String(manager.id)"
                                >
                                    {{ manager.name }}
                                    <template
                                        v-if="manager.assigned_branch_name"
                                    >
                                        — {{ manager.assigned_branch_name }}
                                    </template>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.manager_user_id" />
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
