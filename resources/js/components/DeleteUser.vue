<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2, AlertTriangle } from 'lucide-vue-next';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
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
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="space-y-6">
        <Card class="border border-red-200/50 dark:border-red-900/30 rounded-2xl overflow-hidden bg-red-500/[0.01] dark:bg-red-950/[0.01]">
            <CardHeader class="flex flex-row items-center gap-4 border-b border-red-100/50 dark:border-red-950/20 pb-5 px-6 pt-6">
                <div class="p-2.5 bg-red-500/10 text-red-600 dark:bg-red-500/20 dark:text-red-400 rounded-xl shrink-0">
                    <Trash2 class="w-5 h-5" />
                </div>
                <div class="space-y-0.5">
                    <CardTitle class="text-lg font-black text-red-600 dark:text-red-400">Khu vực nguy hiểm</CardTitle>
                    <CardDescription class="text-xs text-neutral-500 dark:text-neutral-400">Xóa vĩnh viễn tài khoản và toàn bộ tài nguyên của bạn</CardDescription>
                </div>
            </CardHeader>
            <CardContent class="p-6 space-y-6">
                <div class="flex items-start gap-3.5 p-4 rounded-xl border border-red-200 bg-red-50/60 dark:border-red-950/40 dark:bg-red-950/20">
                    <AlertTriangle class="size-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                    <div class="space-y-1">
                        <h5 class="text-xs font-bold text-red-800 dark:text-red-300 uppercase tracking-wider">Cảnh báo quan trọng</h5>
                        <p class="text-xs text-red-700/90 dark:text-red-400/95 leading-relaxed font-medium">
                            Hành động này sẽ xóa vĩnh viễn mọi dữ liệu liên kết và không thể hoàn tác. Xin vui lòng cân nhắc kỹ trước khi tiếp tục.
                        </p>
                    </div>
                </div>

                <div>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button 
                                variant="destructive" 
                                data-test="delete-user-button"
                                class="bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider px-5 py-2.5 transition-all shadow-sm active:scale-95 cursor-pointer"
                            >
                                Xóa tài khoản
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="rounded-2xl border border-neutral-200 dark:border-neutral-800">
                            <Form
                                v-bind="ProfileController.destroy.form()"
                                reset-on-success
                                @error="() => passwordInput?.focus()"
                                :options="{
                                    preserveScroll: true,
                                }"
                                class="space-y-6"
                                v-slot="{ errors, processing, reset, clearErrors }"
                            >
                                <DialogHeader class="space-y-3">
                                    <DialogTitle class="text-lg font-black text-neutral-900 dark:text-neutral-50">
                                        Xác nhận xóa tài khoản của bạn?
                                    </DialogTitle>
                                    <DialogDescription class="text-xs text-neutral-500 dark:text-neutral-400 leading-relaxed">
                                        Sau khi tài khoản bị xóa, toàn bộ tài nguyên và dữ liệu sẽ mất vĩnh viễn. Hãy nhập mật khẩu của bạn để xác thực yêu cầu này.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="grid gap-2">
                                    <Label for="password" class="sr-only">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        ref="passwordInput"
                                        placeholder="Mật khẩu tài khoản"
                                        class="rounded-xl border-neutral-200 focus:ring-2 focus:ring-neutral-950 focus:border-neutral-950 dark:border-neutral-800"
                                    />
                                    <InputError :message="errors.password" />
                                </div>

                                <DialogFooter class="gap-2 sm:gap-0 sm:justify-end">
                                    <DialogClose as-child>
                                        <Button
                                            variant="secondary"
                                            class="rounded-xl font-bold text-xs uppercase tracking-wider px-5 py-2.5 cursor-pointer"
                                            @click="
                                                () => {
                                                    clearErrors();
                                                    reset();
                                                }
                                            "
                                        >
                                            Hủy bỏ
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                        data-test="confirm-delete-user-button"
                                        class="bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider px-5 py-2.5 transition-all shadow-sm active:scale-95 cursor-pointer"
                                    >
                                        Xác nhận xóa
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
