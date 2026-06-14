<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Settings, Bot, Mail, UploadCloud, Check, Loader2,
    Shield, Activity, FileText, Lock, Globe, Server, CheckCircle2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    settings: {
        chatbot_similarity_threshold: number;
        chatbot_max_suggestions: number;
        chatbot_cache_ttl: number;
        mail_driver: string;
        mail_smtp_host: string;
        mail_smtp_port: number;
        mail_smtp_username: string;
        mail_smtp_password: string;
        mail_smtp_encryption: string;
        mail_ses_key: string;
        mail_ses_secret: string;
        mail_ses_region: string;
        mail_mailgun_domain: string;
        mail_mailgun_secret: string;
        mail_mailgun_endpoint: string;
        mail_from_address: string;
        mail_from_name: string;
        upload_menu_image_max: number;
        upload_invoice_image_max: number;
    };
}>();

const activeTab = ref<'chatbot' | 'mail' | 'upload'>('chatbot');

const form = useForm({
    chatbot_similarity_threshold: props.settings.chatbot_similarity_threshold,
    chatbot_max_suggestions: props.settings.chatbot_max_suggestions,
    chatbot_cache_ttl: props.settings.chatbot_cache_ttl,
    mail_driver: props.settings.mail_driver,
    mail_smtp_host: props.settings.mail_smtp_host,
    mail_smtp_port: props.settings.mail_smtp_port,
    mail_smtp_username: props.settings.mail_smtp_username,
    mail_smtp_password: props.settings.mail_smtp_password,
    mail_smtp_encryption: props.settings.mail_smtp_encryption,
    mail_ses_key: props.settings.mail_ses_key,
    mail_ses_secret: props.settings.mail_ses_secret,
    mail_ses_region: props.settings.mail_ses_region,
    mail_mailgun_domain: props.settings.mail_mailgun_domain,
    mail_mailgun_secret: props.settings.mail_mailgun_secret,
    mail_mailgun_endpoint: props.settings.mail_mailgun_endpoint,
    mail_from_address: props.settings.mail_from_address,
    mail_from_name: props.settings.mail_from_name,
    upload_menu_image_max: props.settings.upload_menu_image_max,
    upload_invoice_image_max: props.settings.upload_invoice_image_max,
});

const submit = () => {
    form.post('/super-admin/settings', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Cấu hình hệ thống" />

    <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-2 border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-650 dark:text-indigo-400">
                    <Settings class="size-6 animate-spin-slow" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Cấu hình Hệ thống Toàn cục</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Thiết lập tham số AI Chatbot, Email gửi đi, và giới hạn kích thước tệp upload toàn hệ thống.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Left tabs navigation -->
            <div class="flex flex-col gap-2">
                <button
                    @click="activeTab = 'chatbot'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all text-left border',
                        activeTab === 'chatbot'
                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-600/10'
                            : 'bg-background hover:bg-slate-50 dark:hover:bg-slate-900 border-slate-100 dark:border-slate-800 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <Bot class="size-4 shrink-0" />
                    <span>Cấu hình Chatbot AI</span>
                </button>

                <button
                    @click="activeTab = 'mail'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all text-left border',
                        activeTab === 'mail'
                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-600/10'
                            : 'bg-background hover:bg-slate-50 dark:hover:bg-slate-900 border-slate-100 dark:border-slate-800 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <Mail class="size-4 shrink-0" />
                    <span>Email & SMTP</span>
                </button>

                <button
                    @click="activeTab = 'upload'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all text-left border',
                        activeTab === 'upload'
                            ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-600/10'
                            : 'bg-background hover:bg-slate-50 dark:hover:bg-slate-900 border-slate-100 dark:border-slate-800 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <UploadCloud class="size-4 shrink-0" />
                    <span>Giới hạn Tải lên</span>
                </button>
            </div>

            <!-- Right content cards -->
            <div class="md:col-span-3">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Tab Content: Chatbot -->
                    <Card v-if="activeTab === 'chatbot'" class="border border-slate-200/80 shadow-xs">
                        <CardHeader class="border-b bg-muted/10">
                            <CardTitle class="text-base flex items-center gap-2 text-indigo-750 dark:text-indigo-400">
                                <Bot class="size-5" /> Cấu hình Matching & Cache Chatbot
                            </CardTitle>
                            <CardDescription>Các tham số này điều khiển hành vi của Python NLP Service.</CardDescription>
                        </CardHeader>
                        <CardContent class="p-6 space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="threshold" class="font-bold flex items-center gap-1.5">
                                        Độ tương đồng tối thiểu (Similarity Threshold)
                                        <span class="text-xs text-muted-foreground font-normal">(0.0 -> 1.0)</span>
                                    </Label>
                                    <Input
                                        id="threshold"
                                        type="number"
                                        step="0.01"
                                        v-model="form.chatbot_similarity_threshold"
                                        placeholder="Ví dụ: 0.28"
                                        required
                                    />
                                    <span class="text-xs text-slate-405 leading-relaxed">
                                        Ngưỡng càng cao yêu cầu câu hỏi khách hàng càng phải sát với ngân hàng câu hỏi. Mặc định là <strong class="font-semibold">0.28</strong>.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="max-sug" class="font-bold">Số gợi ý tối đa (Max Suggestions)</Label>
                                    <Input
                                        id="max-sug"
                                        type="number"
                                        v-model="form.chatbot_max_suggestions"
                                        placeholder="Ví dụ: 5"
                                        required
                                    />
                                    <span class="text-xs text-slate-405 leading-relaxed">
                                        Số lượng câu hỏi phổ biến gợi ý cho khách hàng khi chatbot không tìm được câu trả lời tốt nhất. Mặc định là <strong class="font-semibold">5</strong>.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="cache-ttl" class="font-bold">Thời gian sống của cache (Cache TTL - Giây)</Label>
                                    <Input
                                        id="cache-ttl"
                                        type="number"
                                        v-model="form.chatbot_cache_ttl"
                                        placeholder="Ví dụ: 300"
                                        required
                                    />
                                    <span class="text-xs text-slate-405 leading-relaxed">
                                        Thời gian lưu trữ cache in-memory của NLP. Sau thời gian này hoặc khi click reload cache, dữ liệu sẽ tự động tải lại từ DB. Mặc định <strong class="font-semibold">300</strong> giây.
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tab Content: Mail config -->
                    <Card v-if="activeTab === 'mail'" class="border border-slate-200/80 shadow-xs">
                        <CardHeader class="border-b bg-muted/10">
                            <CardTitle class="text-base flex items-center gap-2 text-indigo-750 dark:text-indigo-400">
                                <Mail class="size-5" /> Cấu hình Máy chủ Email gửi đi (SMTP)
                            </CardTitle>
                            <CardDescription>Chuyển đổi linh hoạt driver gửi email chính của hệ thống SaaS.</CardDescription>
                        </CardHeader>
                        <CardContent class="p-6 space-y-6">
                            <div class="grid gap-2">
                                <Label class="font-bold">Driver gửi mail chính (Mail Driver)</Label>
                                <div class="grid grid-cols-3 gap-3">
                                    <label
                                        :class="[
                                            'flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-all hover:bg-muted/10',
                                            form.mail_driver === 'smtp'
                                                ? 'border-indigo-650 bg-indigo-50/20 text-indigo-700 dark:border-indigo-500'
                                                : 'border-slate-100 dark:border-slate-800'
                                        ]"
                                    >
                                        <input type="radio" v-model="form.mail_driver" value="smtp" class="sr-only" />
                                        <Server class="size-5 shrink-0" />
                                        <span class="text-xs font-bold">SMTP Nội bộ</span>
                                    </label>

                                    <label
                                        :class="[
                                            'flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-all hover:bg-muted/10',
                                            form.mail_driver === 'ses'
                                                ? 'border-indigo-650 bg-indigo-50/20 text-indigo-700 dark:border-indigo-500'
                                                : 'border-slate-100 dark:border-slate-800'
                                        ]"
                                    >
                                        <input type="radio" v-model="form.mail_driver" value="ses" class="sr-only" />
                                        <Globe class="size-5 shrink-0" />
                                        <span class="text-xs font-bold">AWS SES (Amazon)</span>
                                    </label>

                                    <label
                                        :class="[
                                            'flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-all hover:bg-muted/10',
                                            form.mail_driver === 'mailgun'
                                                ? 'border-indigo-650 bg-indigo-50/20 text-indigo-700 dark:border-indigo-500'
                                                : 'border-slate-100 dark:border-slate-800'
                                        ]"
                                    >
                                        <input type="radio" v-model="form.mail_driver" value="mailgun" class="sr-only" />
                                        <Globe class="size-5 shrink-0" />
                                        <span class="text-xs font-bold">Mailgun Service</span>
                                    </label>
                                </div>
                            </div>

                            <!-- SMTP Config Options -->
                            <div v-if="form.mail_driver === 'smtp'" class="space-y-4 border-t pt-4">
                                <h4 class="text-xs font-extrabold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Thông số SMTP nội bộ</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-host">SMTP Host</Label>
                                        <Input id="smtp-host" v-model="form.mail_smtp_host" placeholder="smtp.gmail.com" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-port">SMTP Port</Label>
                                        <Input id="smtp-port" type="number" v-model="form.mail_smtp_port" placeholder="587" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-enc">Encryption</Label>
                                        <select id="smtp-enc" v-model="form.mail_smtp_encryption"
                                            class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                            <option value="tls">TLS</option>
                                            <option value="ssl">SSL</option>
                                            <option value="none">Không mã hóa (None)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-user">Username</Label>
                                        <Input id="smtp-user" v-model="form.mail_smtp_username" placeholder="admin@domain.com" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-pass">Password</Label>
                                        <Input id="smtp-pass" type="password" v-model="form.mail_smtp_password" placeholder="••••••••••••" />
                                    </div>
                                </div>
                            </div>

                            <!-- AWS SES Config Options -->
                            <div v-if="form.mail_driver === 'ses'" class="space-y-4 border-t pt-4">
                                <h4 class="text-xs font-extrabold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Thông số AWS SES credentials</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="ses-key">AWS Key ID</Label>
                                        <Input id="ses-key" v-model="form.mail_ses_key" placeholder="AKIAIOSFODNN7EXAMPLE" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="ses-secret">AWS Secret Access Key</Label>
                                        <Input id="ses-secret" type="password" v-model="form.mail_ses_secret" placeholder="••••••••••••" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="ses-region">Region</Label>
                                        <Input id="ses-region" v-model="form.mail_ses_region" placeholder="us-east-1" />
                                    </div>
                                </div>
                            </div>

                            <!-- Mailgun Config Options -->
                            <div v-if="form.mail_driver === 'mailgun'" class="space-y-4 border-t pt-4">
                                <h4 class="text-xs font-extrabold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Thông số Mailgun API</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="mg-dom">Domain</Label>
                                        <Input id="mg-dom" v-model="form.mail_mailgun_domain" placeholder="mg.yourdomain.com" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="mg-sec">Secret API Key</Label>
                                        <Input id="mg-sec" type="password" v-model="form.mail_mailgun_secret" placeholder="key-••••••••••••" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="mg-end">API Endpoint</Label>
                                        <Input id="mg-end" v-model="form.mail_mailgun_endpoint" placeholder="api.mailgun.net" />
                                    </div>
                                </div>
                            </div>

                            <!-- General Sender Settings -->
                            <div class="space-y-4 border-t pt-4">
                                <h4 class="text-xs font-extrabold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Thông tin địa chỉ gửi (Sender Info)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="mail-from">Email gửi đi (From Address)</Label>
                                        <Input id="mail-from" type="email" v-model="form.mail_from_address" placeholder="no-reply@aventura.com" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="mail-name">Tên hiển thị người gửi (From Name)</Label>
                                        <Input id="mail-name" v-model="form.mail_from_name" placeholder="Hệ thống Aventura" />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tab Content: Upload Size Limits -->
                    <Card v-if="activeTab === 'upload'" class="border border-slate-200/80 shadow-xs">
                        <CardHeader class="border-b bg-muted/10">
                            <CardTitle class="text-base flex items-center gap-2 text-indigo-750 dark:text-indigo-400">
                                <UploadCloud class="size-5" /> Giới hạn dung lượng tải lên (Upload limits)
                            </CardTitle>
                            <CardDescription>Thiết lập dung lượng tệp tối đa (đơn vị: KB) cho toàn bộ hệ thống để bảo vệ ổ đĩa máy chủ.</CardDescription>
                        </CardHeader>
                        <CardContent class="p-6 space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="menu-max" class="font-bold flex items-center gap-1.5">
                                        Dung lượng ảnh thực đơn tối đa
                                        <span class="text-xs text-muted-foreground font-normal">(KB)</span>
                                    </Label>
                                    <Input
                                        id="menu-max"
                                        type="number"
                                        v-model="form.upload_menu_image_max"
                                        placeholder="Ví dụ: 2048"
                                        required
                                    />
                                    <span class="text-xs text-slate-405 leading-relaxed">
                                        Giới hạn dung lượng tệp cho ảnh món ăn của thực đơn nhà hàng. Ví dụ: <strong class="font-semibold">2048 KB</strong> = 2 MB.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="invoice-max" class="font-bold flex items-center gap-1.5">
                                        Dung lượng hóa đơn / chứng từ tối đa
                                        <span class="text-xs text-muted-foreground font-normal">(KB)</span>
                                    </Label>
                                    <Input
                                        id="invoice-max"
                                        type="number"
                                        v-model="form.upload_invoice_image_max"
                                        placeholder="Ví dụ: 4096"
                                        required
                                    />
                                    <span class="text-xs text-slate-405 leading-relaxed">
                                        Giới hạn dung lượng tệp cho chứng từ nhập hàng PO, ảnh hóa đơn đối soát. Ví dụ: <strong class="font-semibold">4096 KB</strong> = 4 MB.
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Submit action footer -->
                    <div class="flex items-center justify-between border-t pt-5 bg-background">
                        <div class="text-xs text-slate-400">
                            Cấu hình sẽ có hiệu lực tức thì trên toàn bộ hệ thống.
                        </div>
                        <Button
                            type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold flex items-center gap-2 px-5 h-11"
                            :disabled="form.processing"
                        >
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            <Check v-else class="size-4" />
                            <span>{{ form.processing ? 'Đang lưu cấu hình...' : 'Lưu cấu hình hệ thống' }}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
