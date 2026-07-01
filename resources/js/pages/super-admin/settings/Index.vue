<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Settings, Bot, Mail, UploadCloud, Check, Loader2,
    Shield, Activity, FileText, Lock, Globe, Server, CheckCircle2, Send, User
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { PageHeader, SectionCard, LedIndicator, StatusBadge } from '@/components/super-admin';
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
        has_smtp_password: boolean;
        has_ses_secret: boolean;
        has_mailgun_secret: boolean;
    };
}>();

const page = usePage();
const activeTab = ref<'chatbot' | 'mail' | 'upload'>('chatbot');
const showConfirmDialog = ref(false);
const testingEmail = ref(false);

// Flash message → toast
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

const form = useForm({
    chatbot_similarity_threshold: props.settings.chatbot_similarity_threshold,
    chatbot_max_suggestions: props.settings.chatbot_max_suggestions,
    chatbot_cache_ttl: props.settings.chatbot_cache_ttl,
    mail_driver: props.settings.mail_driver,
    mail_smtp_host: props.settings.mail_smtp_host,
    mail_smtp_port: props.settings.mail_smtp_port,
    mail_smtp_username: props.settings.mail_smtp_username,
    mail_smtp_password: '',
    mail_smtp_encryption: props.settings.mail_smtp_encryption,
    mail_ses_key: props.settings.mail_ses_key,
    mail_ses_secret: '',
    mail_ses_region: props.settings.mail_ses_region,
    mail_mailgun_domain: props.settings.mail_mailgun_domain,
    mail_mailgun_secret: '',
    mail_mailgun_endpoint: props.settings.mail_mailgun_endpoint,
    mail_from_address: props.settings.mail_from_address,
    mail_from_name: props.settings.mail_from_name,
    upload_menu_image_max: props.settings.upload_menu_image_max,
    upload_invoice_image_max: props.settings.upload_invoice_image_max,
});

const handleSubmit = () => {
    if (activeTab.value === 'mail') {
        showConfirmDialog.value = true;
        return;
    }
    doSubmit();
};

const doSubmit = () => {
    showConfirmDialog.value = false;
    form.post('/super-admin/settings', {
        preserveScroll: true,
        onError: () => {
            toast.error('Lưu cấu hình thất bại. Vui lòng kiểm tra lại!');
        }
    });
};

const thresholdAdvice = computed(() => {
    const val = form.chatbot_similarity_threshold;
    if (val === undefined || val === null) return { status: 'neutral', text: 'Chưa nhập ngưỡng.' };
    if (val < 0.2) return { status: 'warning', text: 'Ngưỡng quá thấp (< 0.20) có thể khiến chatbot phản hồi sai lệch, nhầm chủ đề.' };
    if (val > 0.5) return { status: 'warning', text: 'Ngưỡng quá cao (> 0.50) yêu cầu khách nhập cực kỳ chính xác mới khớp câu hỏi.' };
    return { status: 'ok', text: 'Mức độ tương đồng tối ưu (0.2 -> 0.4).' };
});

const cacheTtlAdvice = computed(() => {
    const val = form.chatbot_cache_ttl;
    if (val === undefined || val === null) return { status: 'neutral', text: 'Chưa nhập TTL.' };
    if (val < 60) return { status: 'warning', text: 'Thời gian cache quá thấp tăng số lần truy vấn tới DB của Python NLP service.' };
    if (val > 3600) return { status: 'warning', text: 'Thời gian cache quá dài làm chậm việc cập nhật Q&A mới lên chatbot.' };
    return { status: 'ok', text: 'Thời gian sống cache tối ưu.' };
});

const mailDriverAdvice = computed(() => {
    const driver = form.mail_driver;
    if (driver === 'smtp') {
        const user = (form.mail_smtp_username || '').toLowerCase();
        if (user.includes('gmail.com') || user.includes('yahoo.com') || user.includes('outlook.com')) {
            return { status: 'caution', text: 'Đang dùng SMTP cá nhân. Tránh gửi số lượng lớn tránh bị khóa/spam.' };
        }
    }
    return { status: 'ok', text: 'Cấu hình email chính quy.' };
});

const smtpPortAdvice = computed(() => {
    const port = form.mail_smtp_port;
    if (form.mail_driver === 'smtp') {
        if (port === 25) return { status: 'warning', text: 'Cổng 25 thường bị chặn bởi các nhà cung cấp cloud. Hãy dùng 587 hoặc 465.' };
    }
    return { status: 'ok', text: 'Cổng gửi mail an toàn.' };
});

const hasActiveWarnings = computed(() => {
    return thresholdAdvice.value.status === 'warning' ||
           cacheTtlAdvice.value.status === 'warning' ||
           mailDriverAdvice.value.status === 'caution' ||
           (form.mail_driver === 'smtp' && smtpPortAdvice.value.status === 'warning');
});

const sendTestEmail = () => {
    testingEmail.value = true;
    router.post('/super-admin/settings/test-email', form.data() as any, {
        preserveScroll: true,
        onFinish: () => { testingEmail.value = false; },
    });
};
</script>

<template>
    <Head title="Cấu hình hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Cấu hình Hệ thống Toàn cục"
            subtitle="Thiết lập tham số AI Chatbot, Email gửi đi, và giới hạn kích thước tệp upload toàn hệ thống."
            :icon="Settings"
        />

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Left tabs navigation -->
            <div class="lg:col-span-1 flex flex-col gap-2">
                <button
                    type="button"
                    @click="activeTab = 'chatbot'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all text-left border cursor-pointer',
                        activeTab === 'chatbot'
                            ? 'bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-500/15'
                            : 'bg-card hover:bg-muted/30 border-border/40 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <Bot class="size-4 shrink-0" />
                    <span>Cấu hình Chatbot AI</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'mail'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all text-left border cursor-pointer',
                        activeTab === 'mail'
                            ? 'bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-500/15'
                            : 'bg-card hover:bg-muted/30 border-border/40 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <Mail class="size-4 shrink-0" />
                    <span>Email & SMTP</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'upload'"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all text-left border cursor-pointer',
                        activeTab === 'upload'
                            ? 'bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-500/15'
                            : 'bg-card hover:bg-muted/30 border-border/40 text-slate-700 dark:text-slate-300'
                    ]"
                >
                    <UploadCloud class="size-4 shrink-0" />
                    <span>Giới hạn Tải lên</span>
                </button>
            </div>

            <!-- Middle: Settings Form Content -->
            <div class="lg:col-span-3">
                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <!-- Tab Content: Chatbot -->
                    <Card v-if="activeTab === 'chatbot'" class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                        <CardHeader class="border-b border-border/40 bg-muted/10">
                            <CardTitle class="text-xs font-black uppercase tracking-wider flex items-center gap-2 text-slate-800 dark:text-slate-100">
                                <Bot class="size-5 text-orange-500" /> Cấu hình Matching & Cache Chatbot
                            </CardTitle>
                            <CardDescription class="text-[10px] font-semibold text-muted-foreground mt-0.5">Các tham số này điều khiển hành vi của Python NLP Service.</CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 space-y-5">
                            <div class="grid grid-cols-1 gap-4">
                                <div class="grid gap-3">
                                    <div class="flex items-center justify-between">
                                        <Label for="threshold" class="text-xs font-bold text-slate-700 dark:text-slate-300">
                                            Độ tương đồng tối thiểu (Similarity Threshold)
                                        </Label>
                                        <span class="text-xs font-extrabold text-orange-500 font-mono bg-orange-500/10 px-2 py-0.5 rounded-lg border border-orange-500/20">
                                            {{ form.chatbot_similarity_threshold }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 py-1.5">
                                        <span class="text-[10px] font-bold text-muted-foreground">0.0 (Thấp)</span>
                                        <input
                                            id="threshold"
                                            type="range"
                                            min="0"
                                            max="1"
                                            step="0.01"
                                            v-model.number="form.chatbot_similarity_threshold"
                                            class="flex-1 h-1.5 rounded-lg bg-muted appearance-none cursor-pointer accent-orange-500"
                                        />
                                        <span class="text-[10px] font-bold text-muted-foreground">1.0 (Tuyệt đối)</span>
                                    </div>
                                    <span class="text-[10px] text-muted-foreground leading-normal font-semibold">
                                        Ngưỡng càng cao yêu cầu câu hỏi khách hàng càng phải sát với ngân hàng câu hỏi. Mặc định là <strong class="font-bold text-slate-700 dark:text-slate-300">0.28</strong>.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="max-sug" class="text-xs font-bold text-slate-700 dark:text-slate-300">Số gợi ý tối đa (Max Suggestions)</Label>
                                    <Input
                                        id="max-sug"
                                        type="number"
                                        v-model="form.chatbot_max_suggestions"
                                        placeholder="Ví dụ: 5"
                                        class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold font-mono"
                                        required
                                    />
                                    <span class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">
                                        Số lượng câu hỏi phổ biến gợi ý cho khách hàng khi chatbot không tìm được câu trả lời tốt nhất. Mặc định là <strong class="font-bold text-slate-700 dark:text-slate-300">5</strong>.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="cache-ttl" class="text-xs font-bold text-slate-700 dark:text-slate-300">Thời gian sống của cache (Cache TTL - Giây)</Label>
                                    <Input
                                        id="cache-ttl"
                                        type="number"
                                        v-model="form.chatbot_cache_ttl"
                                        placeholder="Ví dụ: 300"
                                        class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold font-mono"
                                        required
                                    />
                                    <span class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">
                                        Thời gian lưu trữ cache in-memory của NLP. Sau thời gian này hoặc khi click reload cache, dữ liệu sẽ tự động tải lại từ DB. Mặc định <strong class="font-bold text-slate-700 dark:text-slate-300">300</strong> giây.
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tab Content: Mail config -->
                    <Card v-if="activeTab === 'mail'" class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                        <CardHeader class="border-b border-border/40 bg-muted/10">
                            <CardTitle class="text-xs font-black uppercase tracking-wider flex items-center gap-2 text-slate-800 dark:text-slate-100">
                                <Mail class="size-5 text-orange-500" /> Cấu hình Máy chủ Email gửi đi (SMTP)
                            </CardTitle>
                            <CardDescription class="text-[10px] font-semibold text-muted-foreground mt-0.5">Chuyển đổi linh hoạt driver gửi email chính của hệ thống SaaS.</CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 space-y-6">
                            <div class="grid gap-2">
                                <Label class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Driver gửi mail chính (Mail Driver)</Label>
                                <div class="grid grid-cols-3 gap-3">
                                    <label
                                        :class="[
                                            'flex flex-col items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all hover:bg-muted/10',
                                            form.mail_driver === 'smtp'
                                                ? 'border-orange-500 bg-orange-500/5 text-orange-600 dark:text-orange-400'
                                                : 'border-border/60'
                                        ]"
                                    >
                                        <input type="radio" v-model="form.mail_driver" value="smtp" class="sr-only" />
                                        <Server class="size-5 shrink-0" />
                                        <span class="text-[10px] font-black uppercase tracking-wider mt-0.5">SMTP Nội bộ</span>
                                    </label>

                                    <label
                                        :class="[
                                            'flex flex-col items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all hover:bg-muted/10',
                                            form.mail_driver === 'ses'
                                                ? 'border-orange-500 bg-orange-500/5 text-orange-600 dark:text-orange-400'
                                                : 'border-border/60'
                                        ]"
                                    >
                                        <input type="radio" v-model="form.mail_driver" value="ses" class="sr-only" />
                                        <Globe class="size-5 shrink-0" />
                                        <span class="text-[10px] font-black uppercase tracking-wider mt-0.5">AWS SES (Amazon)</span>
                                    </label>

                                    <label
                                        :class="[
                                            'flex flex-col items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all hover:bg-muted/10',
                                            form.mail_driver === 'mailgun'
                                                ? 'border-orange-500 bg-orange-500/5 text-orange-600 dark:text-orange-400'
                                                : 'border-border/60'
                                        ]"
                                    >
                                        <input type="radio" v-model="form.mail_driver" value="mailgun" class="sr-only" />
                                        <Globe class="size-5 shrink-0" />
                                        <span class="text-[10px] font-black uppercase tracking-wider mt-0.5">Mailgun Service</span>
                                    </label>
                                </div>
                            </div>

                            <!-- SMTP Config Options -->
                            <div v-if="form.mail_driver === 'smtp'" class="space-y-4 border-t border-border/40 pt-4">
                                <h4 class="text-[10px] font-extrabold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Thông số SMTP nội bộ</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-host" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">SMTP Host</Label>
                                        <div class="relative flex items-center">
                                            <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                                <Server class="size-4 text-orange-500" />
                                            </div>
                                            <Input id="smtp-host" v-model="form.mail_smtp_host" placeholder="smtp.gmail.com" class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" />
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-port" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">SMTP Port</Label>
                                        <div class="relative flex items-center">
                                            <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                                <Settings class="size-4 text-orange-500" />
                                            </div>
                                            <Input id="smtp-port" type="number" v-model="form.mail_smtp_port" placeholder="587" class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold font-mono" />
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Encryption</Label>
                                        <Select v-model="form.mail_smtp_encryption">
                                            <SelectTrigger id="smtp-enc" class="rounded-xl border border-border bg-background focus:ring-orange-500/20 focus:border-orange-500 text-xs h-9 font-semibold">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent class="rounded-xl">
                                                <SelectItem value="tls" class="text-xs font-semibold cursor-pointer">TLS</SelectItem>
                                                <SelectItem value="ssl" class="text-xs font-semibold cursor-pointer">SSL</SelectItem>
                                                <SelectItem value="none" class="text-xs font-semibold cursor-pointer">Không mã hóa (None)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-user" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Username</Label>
                                        <div class="relative flex items-center">
                                            <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                                <User class="size-4 text-orange-500" />
                                            </div>
                                            <Input id="smtp-user" v-model="form.mail_smtp_username" placeholder="admin@domain.com" class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" />
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="smtp-pass" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Password</Label>
                                        <div class="relative flex items-center">
                                            <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                                <Lock class="size-4 text-orange-500" />
                                            </div>
                                            <Input id="smtp-pass" type="password" v-model="form.mail_smtp_password"
                                                class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold"
                                                :placeholder="settings.has_smtp_password ? 'Đã thiết lập — để trống nếu không đổi' : 'Nhập mật khẩu SMTP'" />
                                        </div>
                                        <span v-if="settings.has_smtp_password" class="text-[10px] text-emerald-600 font-bold flex items-center gap-1 mt-0.5">
                                            <CheckCircle2 class="size-3" /> Đã thiết lập mật khẩu
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- AWS SES Config Options -->
                            <div v-if="form.mail_driver === 'ses'" class="space-y-4 border-t border-border/40 pt-4">
                                <h4 class="text-[10px] font-extrabold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Thông số AWS SES credentials</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="ses-key" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">AWS Key ID</Label>
                                        <Input id="ses-key" v-model="form.mail_ses_key" placeholder="AKIAIOSFODNN7EXAMPLE" class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold font-mono" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="ses-secret" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">AWS Secret Access Key</Label>
                                        <Input id="ses-secret" type="password" v-model="form.mail_ses_secret"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold"
                                            :placeholder="settings.has_ses_secret ? 'Đã thiết lập — để trống nếu không đổi' : 'Nhập AWS Secret Key'" />
                                        <span v-if="settings.has_ses_secret" class="text-[10px] text-emerald-600 font-bold flex items-center gap-1 mt-0.5">
                                            <CheckCircle2 class="size-3" /> Đã thiết lập Secret Key
                                        </span>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="ses-region" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Region</Label>
                                        <Input id="ses-region" v-model="form.mail_ses_region" placeholder="us-east-1" class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold font-mono" />
                                    </div>
                                </div>
                            </div>

                            <!-- Mailgun Config Options -->
                            <div v-if="form.mail_driver === 'mailgun'" class="space-y-4 border-t border-border/40 pt-4">
                                <h4 class="text-[10px] font-extrabold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Thông số Mailgun API</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="mg-dom" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Domain</Label>
                                        <Input id="mg-dom" v-model="form.mail_mailgun_domain" placeholder="mg.yourdomain.com" class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="mg-sec" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Secret API Key</Label>
                                        <Input id="mg-sec" type="password" v-model="form.mail_mailgun_secret"
                                            class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold"
                                            :placeholder="settings.has_mailgun_secret ? 'Đã thiết lập — để trống nếu không đổi' : 'Nhập Mailgun Secret Key'" />
                                        <span v-if="settings.has_mailgun_secret" class="text-[10px] text-emerald-600 font-bold flex items-center gap-1 mt-0.5">
                                            <CheckCircle2 class="size-3" /> Đã thiết lập Secret Key
                                        </span>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="mg-end" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">API Endpoint</Label>
                                        <Input id="mg-end" v-model="form.mail_mailgun_endpoint" placeholder="api.mailgun.net" class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" />
                                    </div>
                                </div>
                            </div>

                            <!-- General Sender Settings -->
                            <div class="space-y-4 border-t border-border/40 pt-4">
                                <h4 class="text-[10px] font-extrabold text-slate-700 dark:text-slate-350 uppercase tracking-wider">Thông tin địa chỉ gửi (Sender Info)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="grid gap-1.5">
                                        <Label for="mail-from" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Email gửi đi (From Address)</Label>
                                        <div class="relative flex items-center">
                                            <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                                <Mail class="size-4 text-orange-500" />
                                            </div>
                                            <Input id="mail-from" type="email" v-model="form.mail_from_address" placeholder="no-reply@aventura.com" class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" />
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="mail-name" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Tên hiển thị người gửi (From Name)</Label>
                                        <div class="relative flex items-center">
                                            <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                                <User class="size-4 text-orange-500" />
                                            </div>
                                            <Input id="mail-name" v-model="form.mail_from_name" placeholder="Hệ thống Aventura" class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Test Email Button -->
                            <div class="border-t border-border/40 pt-4 space-y-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="flex items-center gap-2 rounded-xl border border-border bg-background hover:bg-muted text-xs h-9 font-bold cursor-pointer transition-colors"
                                    :disabled="testingEmail"
                                    @click="sendTestEmail"
                                >
                                    <Loader2 v-if="testingEmail" class="size-4 animate-spin" />
                                    <Send v-else class="size-4" />
                                    <span>{{ testingEmail ? 'Đang gửi email thử...' : 'Gửi email thử nghiệm' }}</span>
                                </Button>
                                <p class="text-[10px] text-muted-foreground font-semibold leading-relaxed">Gửi email thử đến địa chỉ email tài khoản đang đăng nhập để xác nhận cấu hình hoạt động.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tab Content: Upload Size Limits -->
                    <Card v-if="activeTab === 'upload'" class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                        <CardHeader class="border-b border-border/40 bg-muted/10">
                            <CardTitle class="text-xs font-black uppercase tracking-wider flex items-center gap-2 text-slate-800 dark:text-slate-100">
                                <UploadCloud class="size-5 text-orange-500" /> Giới hạn dung lượng tải lên (Upload limits)
                            </CardTitle>
                            <CardDescription class="text-[10px] font-semibold text-muted-foreground mt-0.5">Thiết lập dung lượng tệp tối đa (đơn vị: KB) cho toàn bộ hệ thống để bảo vệ ổ đĩa máy chủ.</CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 space-y-5">
                            <div class="grid grid-cols-1 gap-4">
                                <div class="grid gap-2">
                                    <Label for="menu-max" class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                        Dung lượng ảnh thực đơn tối đa
                                        <span class="text-[10px] text-muted-foreground font-normal">(KB)</span>
                                    </Label>
                                    <Input
                                        id="menu-max"
                                        type="number"
                                        v-model="form.upload_menu_image_max"
                                        placeholder="Ví dụ: 2048"
                                        class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold font-mono"
                                        required
                                    />
                                    <span class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">
                                        Giới hạn dung lượng tệp cho ảnh món ăn của thực đơn nhà hàng. Ví dụ: <strong class="font-bold text-slate-700 dark:text-slate-300">2048 KB</strong> = 2 MB.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="invoice-max" class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                        Dung lượng hóa đơn / chứng từ tối đa
                                        <span class="text-[10px] text-muted-foreground font-normal">(KB)</span>
                                    </Label>
                                    <Input
                                        id="invoice-max"
                                        type="number"
                                        v-model="form.upload_invoice_image_max"
                                        placeholder="Ví dụ: 4096"
                                        class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold font-mono"
                                        required
                                    />
                                    <span class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">
                                        Giới hạn dung lượng tệp cho chứng từ nhập hàng PO, ảnh hóa đơn đối soát. Ví dụ: <strong class="font-bold text-slate-700 dark:text-slate-300">4096 KB</strong> = 4 MB.
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Submit action footer -->
                    <div class="flex items-center justify-between border-t border-border/40 pt-5 bg-background">
                        <div class="text-[10px] text-muted-foreground font-semibold">
                            Cấu hình sẽ có hiệu lực tức thì trên toàn bộ hệ thống.
                        </div>
                        <Button
                            type="submit"
                            class="bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl shadow-xs cursor-pointer font-bold h-10 text-xs transition-colors flex items-center gap-2 px-5"
                            :disabled="form.processing"
                        >
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            <Check v-else class="size-4" />
                            <span>{{ form.processing ? 'Đang lưu cấu hình...' : 'Lưu cấu hình hệ thống' }}</span>
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Right Column: AI Configuration Diagnostics Coach -->
            <div class="lg:col-span-1 space-y-6">
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardContent class="p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-border/40 pb-3">
                            <h4 class="text-xs font-black text-muted-foreground uppercase tracking-wider">AI Cấu hình Advisor</h4>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase border animate-pulse"
                                :class="
                                    hasActiveWarnings ? 'bg-rose-500/10 text-rose-600 border-rose-500/20' : 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                                "
                            >
                                {{ hasActiveWarnings ? 'Khuyến nghị' : 'Đạt chuẩn' }}
                            </span>
                        </div>

                        <!-- Chatbot Advices -->
                        <div v-if="activeTab === 'chatbot'" class="space-y-3.5">
                            <div class="p-3 rounded-xl border flex items-start gap-2.5"
                                :class="thresholdAdvice.status === 'warning' ? 'border-amber-500/20 bg-amber-500/[0.03]' : 'border-border/30 bg-muted/10'"
                            >
                                <div class="p-1 rounded-lg shrink-0 mt-0.5"
                                    :class="thresholdAdvice.status === 'warning' ? 'bg-amber-500/10 text-amber-500' : 'bg-slate-500/10 text-slate-500'"
                                >
                                    <Bot class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Matching Threshold</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">{{ thresholdAdvice.text }}</p>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl border flex items-start gap-2.5"
                                :class="cacheTtlAdvice.status === 'warning' ? 'border-amber-500/20 bg-amber-500/[0.03]' : 'border-border/30 bg-muted/10'"
                            >
                                <div class="p-1 rounded-lg shrink-0 mt-0.5"
                                    :class="cacheTtlAdvice.status === 'warning' ? 'bg-amber-500/10 text-amber-500' : 'bg-slate-500/10 text-slate-500'"
                                >
                                    <Activity class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Cache TTL</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">{{ cacheTtlAdvice.text }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Mail Advices -->
                        <div v-if="activeTab === 'mail'" class="space-y-3.5">
                            <div class="p-3 rounded-xl border flex items-start gap-2.5"
                                :class="mailDriverAdvice.status === 'caution' ? 'border-amber-500/20 bg-amber-500/[0.03]' : 'border-border/30 bg-muted/10'"
                            >
                                <div class="p-1 rounded-lg shrink-0 mt-0.5"
                                    :class="mailDriverAdvice.status === 'caution' ? 'bg-amber-500/10 text-amber-500' : 'bg-slate-500/10 text-slate-500'"
                                >
                                    <Mail class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Mail Driver & SMTP</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">{{ mailDriverAdvice.text }}</p>
                                </div>
                            </div>

                            <div v-if="form.mail_driver === 'smtp'" class="p-3 rounded-xl border flex items-start gap-2.5"
                                :class="smtpPortAdvice.status === 'warning' ? 'border-rose-500/20 bg-rose-500/[0.03]' : 'border-border/30 bg-muted/10'"
                            >
                                <div class="p-1 rounded-lg shrink-0 mt-0.5"
                                    :class="smtpPortAdvice.status === 'warning' ? 'bg-rose-500/10 text-rose-500' : 'bg-slate-500/10 text-slate-500'"
                                >
                                    <Server class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">SMTP Port Security</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">{{ smtpPortAdvice.text }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Advices -->
                        <div v-if="activeTab === 'upload'" class="space-y-3.5">
                            <div class="p-3 rounded-xl border border-border/30 bg-muted/10 flex items-start gap-2.5">
                                <div class="p-1 bg-slate-500/10 text-slate-500 rounded-lg shrink-0 mt-0.5">
                                    <UploadCloud class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Disk Quota Limit</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">Cấu hình KB giúp ngăn chặn tấn công từ chối dịch vụ (DoS) qua tải lên tệp ảnh dung lượng khổng lồ.</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Confirmation Dialog for Mail config changes -->
    <Dialog v-model:open="showConfirmDialog">
        <DialogContent class="sm:max-w-md bg-card border border-border/40 backdrop-blur-md rounded-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-slate-100">
                    <Shield class="size-5 text-amber-500 animate-pulse" />
                    Xác nhận thay đổi cấu hình Email
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground font-semibold leading-relaxed mt-2">
                    Thay đổi cấu hình email sẽ ảnh hưởng đến toàn bộ hệ thống gửi mail (OTP, thông báo, hóa đơn...). Bạn có chắc chắn muốn lưu?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="flex gap-2 sm:justify-end border-t border-border/40 pt-4 mt-3">
                <Button variant="outline" @click="showConfirmDialog = false" class="rounded-xl border-border hover:bg-muted font-bold text-xs h-9 cursor-pointer">Hủy bỏ</Button>
                <Button class="bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl font-bold text-xs h-9 cursor-pointer" @click="doSubmit" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin mr-1" />
                    Xác nhận lưu
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
