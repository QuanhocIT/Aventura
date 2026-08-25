<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Settings,
    Bot,
    Mail,
    UploadCloud,
    Check,
    Loader2,
    Shield,
    Activity,
    Lock,
    Globe,
    Server,
    CheckCircle2,
    Send,
    User,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { PageHeader } from '@/components/super-admin';
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
    DialogDescription,
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
        },
    });
};

const thresholdAdvice = computed(() => {
    const val = form.chatbot_similarity_threshold;

    if (val === undefined || val === null) {
        return { status: 'neutral', text: 'Chưa nhập ngưỡng.' };
    }

    if (val < 0.2) {
        return {
            status: 'warning',
            text: 'Ngưỡng quá thấp (< 0.20) có thể khiến chatbot phản hồi sai lệch, nhầm chủ đề.',
        };
    }

    if (val > 0.5) {
        return {
            status: 'warning',
            text: 'Ngưỡng quá cao (> 0.50) yêu cầu khách nhập cực kỳ chính xác mới khớp câu hỏi.',
        };
    }

    return { status: 'ok', text: 'Mức độ tương đồng tối ưu (0.2 -> 0.4).' };
});

const cacheTtlAdvice = computed(() => {
    const val = form.chatbot_cache_ttl;

    if (val === undefined || val === null) {
        return { status: 'neutral', text: 'Chưa nhập TTL.' };
    }

    if (val < 60) {
        return {
            status: 'warning',
            text: 'Thời gian cache quá thấp tăng số lần truy vấn tới DB của Python NLP service.',
        };
    }

    if (val > 3600) {
        return {
            status: 'warning',
            text: 'Thời gian cache quá dài làm chậm việc cập nhật Q&A mới lên chatbot.',
        };
    }

    return { status: 'ok', text: 'Thời gian sống cache tối ưu.' };
});

const mailDriverAdvice = computed(() => {
    const driver = form.mail_driver;

    if (driver === 'smtp') {
        const user = (form.mail_smtp_username || '').toLowerCase();

        if (
            user.includes('gmail.com') ||
            user.includes('yahoo.com') ||
            user.includes('outlook.com')
        ) {
            return {
                status: 'caution',
                text: 'Đang dùng SMTP cá nhân. Tránh gửi số lượng lớn tránh bị khóa/spam.',
            };
        }
    }

    return { status: 'ok', text: 'Cấu hình email chính quy.' };
});

const smtpPortAdvice = computed(() => {
    const port = form.mail_smtp_port;

    if (form.mail_driver === 'smtp') {
        if (port === 25) {
            return {
                status: 'warning',
                text: 'Cổng 25 thường bị chặn bởi các nhà cung cấp cloud. Hãy dùng 587 hoặc 465.',
            };
        }
    }

    return { status: 'ok', text: 'Cổng gửi mail an toàn.' };
});

const hasActiveWarnings = computed(() => {
    return (
        thresholdAdvice.value.status === 'warning' ||
        cacheTtlAdvice.value.status === 'warning' ||
        mailDriverAdvice.value.status === 'caution' ||
        (form.mail_driver === 'smtp' &&
            smtpPortAdvice.value.status === 'warning')
    );
});

const sendTestEmail = () => {
    testingEmail.value = true;
    router.post('/super-admin/settings/test-email', form.data() as any, {
        preserveScroll: true,
        onFinish: () => {
            testingEmail.value = false;
        },
    });
};
</script>

<template>
    <Head title="Cấu hình hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Cấu hình Hệ thống Toàn cục"
            subtitle="Thiết lập tham số AI Chatbot, thư điện tử gửi đi và giới hạn kích thước tệp tải lên toàn hệ thống."
            :icon="Settings"
        />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <!-- Left tabs navigation -->
            <div class="flex flex-col gap-2 lg:col-span-1">
                <button
                    type="button"
                    @click="activeTab = 'chatbot'"
                    :class="[
                        'flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-left text-xs font-bold transition-all',
                        activeTab === 'chatbot'
                            ? 'border-orange-500 bg-orange-500 text-white shadow-md shadow-orange-500/15'
                            : 'border-border/40 bg-card text-slate-700 hover:bg-muted/30 dark:text-slate-300',
                    ]"
                >
                    <Bot class="size-4 shrink-0" />
                    <span>Cấu hình Chatbot AI</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'mail'"
                    :class="[
                        'flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-left text-xs font-bold transition-all',
                        activeTab === 'mail'
                            ? 'border-orange-500 bg-orange-500 text-white shadow-md shadow-orange-500/15'
                            : 'border-border/40 bg-card text-slate-700 hover:bg-muted/30 dark:text-slate-300',
                    ]"
                >
                    <Mail class="size-4 shrink-0" />
                    <span>Thư điện tử & SMTP</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'upload'"
                    :class="[
                        'flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-left text-xs font-bold transition-all',
                        activeTab === 'upload'
                            ? 'border-orange-500 bg-orange-500 text-white shadow-md shadow-orange-500/15'
                            : 'border-border/40 bg-card text-slate-700 hover:bg-muted/30 dark:text-slate-300',
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
                    <Card
                        v-if="activeTab === 'chatbot'"
                        class="animate-fade-in overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                    >
                        <CardHeader
                            class="border-b border-border/40 bg-muted/10"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-100"
                            >
                                <Bot class="size-5 text-orange-500" /> Cấu hình
                                Đối sánh & bộ nhớ đệm Chatbot
                            </CardTitle>
                            <CardDescription
                                class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                                >Các tham số này điều khiển hành vi của Python
                                NLP Service.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-5 p-5">
                            <div class="grid grid-cols-1 gap-4">
                                <div class="grid gap-3">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <Label
                                            for="threshold"
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                        >
                                            Độ tương đồng tối thiểu (Similarity
                                            Threshold)
                                        </Label>
                                        <span
                                            class="rounded-lg border border-orange-500/20 bg-orange-500/10 px-2 py-0.5 font-mono text-xs font-extrabold text-orange-500"
                                        >
                                            {{
                                                form.chatbot_similarity_threshold
                                            }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 py-1.5">
                                        <span
                                            class="text-[10px] font-bold text-muted-foreground"
                                            >0.0 (Thấp)</span
                                        >
                                        <input
                                            id="threshold"
                                            type="range"
                                            min="0"
                                            max="1"
                                            step="0.01"
                                            v-model.number="
                                                form.chatbot_similarity_threshold
                                            "
                                            class="h-1.5 flex-1 cursor-pointer appearance-none rounded-lg bg-muted accent-orange-500"
                                        />
                                        <span
                                            class="text-[10px] font-bold text-muted-foreground"
                                            >1.0 (Tuyệt đối)</span
                                        >
                                    </div>
                                    <span
                                        class="text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Ngưỡng càng cao yêu cầu câu hỏi khách
                                        hàng càng phải sát với ngân hàng câu
                                        hỏi. Mặc định là
                                        <strong
                                            class="font-bold text-slate-700 dark:text-slate-300"
                                            >0.28</strong
                                        >.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        for="max-sug"
                                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                        >Số gợi ý tối đa (Max
                                        Suggestions)</Label
                                    >
                                    <Input
                                        id="max-sug"
                                        type="number"
                                        v-model="form.chatbot_max_suggestions"
                                        placeholder="Ví dụ: 5"
                                        class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        required
                                    />
                                    <span
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Số lượng câu hỏi phổ biến gợi ý cho
                                        khách hàng khi chatbot không tìm được
                                        câu trả lời tốt nhất. Mặc định là
                                        <strong
                                            class="font-bold text-slate-700 dark:text-slate-300"
                                            >5</strong
                                        >.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        for="cache-ttl"
                                        class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                        >Thời gian sống của bộ nhớ đệm
                                        (giây)</Label
                                    >
                                    <Input
                                        id="cache-ttl"
                                        type="number"
                                        v-model="form.chatbot_cache_ttl"
                                        placeholder="Ví dụ: 300"
                                        class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        required
                                    />
                                    <span
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Thời gian lưu trữ cache in-memory của
                                        NLP. Sau thời gian này hoặc khi click
                                        reload cache, dữ liệu sẽ tự động tải lại
                                        từ DB. Mặc định
                                        <strong
                                            class="font-bold text-slate-700 dark:text-slate-300"
                                            >300</strong
                                        >
                                        giây.
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tab Content: Mail config -->
                    <Card
                        v-if="activeTab === 'mail'"
                        class="animate-fade-in overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                    >
                        <CardHeader
                            class="border-b border-border/40 bg-muted/10"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-100"
                            >
                                <Mail class="size-5 text-orange-500" /> Cấu hình
                                Máy chủ thư điện tử gửi đi (SMTP)
                            </CardTitle>
                            <CardDescription
                                class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                                >Chuyển đổi linh hoạt trình điều khiển gửi thư
                                điện tử chính của hệ thống
                                SaaS.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-6 p-5">
                            <div class="grid gap-2">
                                <Label
                                    class="text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                                    >Driver gửi mail chính (Mail Driver)</Label
                                >
                                <div class="grid grid-cols-3 gap-3">
                                    <label
                                        :class="[
                                            'flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 p-3 transition-all hover:bg-muted/10',
                                            form.mail_driver === 'smtp'
                                                ? 'border-orange-500 bg-orange-500/5 text-orange-600 dark:text-orange-400'
                                                : 'border-border/60',
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.mail_driver"
                                            value="smtp"
                                            class="sr-only"
                                        />
                                        <Server class="size-5 shrink-0" />
                                        <span
                                            class="mt-0.5 text-[10px] font-black tracking-wider uppercase"
                                            >SMTP Nội bộ</span
                                        >
                                    </label>

                                    <label
                                        :class="[
                                            'flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 p-3 transition-all hover:bg-muted/10',
                                            form.mail_driver === 'ses'
                                                ? 'border-orange-500 bg-orange-500/5 text-orange-600 dark:text-orange-400'
                                                : 'border-border/60',
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.mail_driver"
                                            value="ses"
                                            class="sr-only"
                                        />
                                        <Globe class="size-5 shrink-0" />
                                        <span
                                            class="mt-0.5 text-[10px] font-black tracking-wider uppercase"
                                            >AWS SES (Amazon)</span
                                        >
                                    </label>

                                    <label
                                        :class="[
                                            'flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 p-3 transition-all hover:bg-muted/10',
                                            form.mail_driver === 'mailgun'
                                                ? 'border-orange-500 bg-orange-500/5 text-orange-600 dark:text-orange-400'
                                                : 'border-border/60',
                                        ]"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.mail_driver"
                                            value="mailgun"
                                            class="sr-only"
                                        />
                                        <Globe class="size-5 shrink-0" />
                                        <span
                                            class="mt-0.5 text-[10px] font-black tracking-wider uppercase"
                                            >Dịch vụ Mailgun</span
                                        >
                                    </label>
                                </div>
                            </div>

                            <!-- SMTP Config Options -->
                            <div
                                v-if="form.mail_driver === 'smtp'"
                                class="space-y-4 border-t border-border/40 pt-4"
                            >
                                <h4
                                    class="dark:text-slate-350 text-[10px] font-extrabold tracking-wider text-slate-700 uppercase"
                                >
                                    Thông số SMTP nội bộ
                                </h4>
                                <div
                                    class="grid grid-cols-1 gap-4 md:grid-cols-3"
                                >
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="smtp-host"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >SMTP Host</Label
                                        >
                                        <div class="relative flex items-center">
                                            <div
                                                class="pointer-events-none absolute left-3 text-muted-foreground"
                                            >
                                                <Server
                                                    class="size-4 text-orange-500"
                                                />
                                            </div>
                                            <Input
                                                id="smtp-host"
                                                v-model="form.mail_smtp_host"
                                                placeholder="smtp.gmail.com"
                                                class="h-9 rounded-xl border-border bg-background pl-9.5 text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="smtp-port"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >SMTP Port</Label
                                        >
                                        <div class="relative flex items-center">
                                            <div
                                                class="pointer-events-none absolute left-3 text-muted-foreground"
                                            >
                                                <Settings
                                                    class="size-4 text-orange-500"
                                                />
                                            </div>
                                            <Input
                                                id="smtp-port"
                                                type="number"
                                                v-model="form.mail_smtp_port"
                                                placeholder="587"
                                                class="h-9 rounded-xl border-border bg-background pl-9.5 font-mono text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Encryption</Label
                                        >
                                        <Select
                                            v-model="form.mail_smtp_encryption"
                                        >
                                            <SelectTrigger
                                                id="smtp-enc"
                                                class="h-9 rounded-xl border border-border bg-background text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent class="rounded-xl">
                                                <SelectItem
                                                    value="tls"
                                                    class="cursor-pointer text-xs font-semibold"
                                                    >TLS</SelectItem
                                                >
                                                <SelectItem
                                                    value="ssl"
                                                    class="cursor-pointer text-xs font-semibold"
                                                    >SSL</SelectItem
                                                >
                                                <SelectItem
                                                    value="none"
                                                    class="cursor-pointer text-xs font-semibold"
                                                    >Không mã hóa
                                                    (None)</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div
                                    class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                >
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="smtp-user"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Tên người dùng</Label
                                        >
                                        <div class="relative flex items-center">
                                            <div
                                                class="pointer-events-none absolute left-3 text-muted-foreground"
                                            >
                                                <User
                                                    class="size-4 text-orange-500"
                                                />
                                            </div>
                                            <Input
                                                id="smtp-user"
                                                v-model="
                                                    form.mail_smtp_username
                                                "
                                                placeholder="admin@domain.com"
                                                class="h-9 rounded-xl border-border bg-background pl-9.5 text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="smtp-pass"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Password</Label
                                        >
                                        <div class="relative flex items-center">
                                            <div
                                                class="pointer-events-none absolute left-3 text-muted-foreground"
                                            >
                                                <Lock
                                                    class="size-4 text-orange-500"
                                                />
                                            </div>
                                            <Input
                                                id="smtp-pass"
                                                type="password"
                                                v-model="
                                                    form.mail_smtp_password
                                                "
                                                class="h-9 rounded-xl border-border bg-background pl-9.5 text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                                :placeholder="
                                                    settings.has_smtp_password
                                                        ? 'Đã thiết lập — để trống nếu không đổi'
                                                        : 'Nhập mật khẩu SMTP'
                                                "
                                            />
                                        </div>
                                        <span
                                            v-if="settings.has_smtp_password"
                                            class="mt-0.5 flex items-center gap-1 text-[10px] font-bold text-emerald-600"
                                        >
                                            <CheckCircle2 class="size-3" /> Đã
                                            thiết lập mật khẩu
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- AWS SES Config Options -->
                            <div
                                v-if="form.mail_driver === 'ses'"
                                class="space-y-4 border-t border-border/40 pt-4"
                            >
                                <h4
                                    class="dark:text-slate-350 text-[10px] font-extrabold tracking-wider text-slate-700 uppercase"
                                >
                                    Thông số AWS SES credentials
                                </h4>
                                <div
                                    class="grid grid-cols-1 gap-4 md:grid-cols-3"
                                >
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="ses-key"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >AWS Key ID</Label
                                        >
                                        <Input
                                            id="ses-key"
                                            v-model="form.mail_ses_key"
                                            placeholder="AKIAIOSFODNN7EXAMPLE"
                                            class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="ses-secret"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >AWS Secret Access Key</Label
                                        >
                                        <Input
                                            id="ses-secret"
                                            type="password"
                                            v-model="form.mail_ses_secret"
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            :placeholder="
                                                settings.has_ses_secret
                                                    ? 'Đã thiết lập — để trống nếu không đổi'
                                                    : 'Nhập AWS Secret Key'
                                            "
                                        />
                                        <span
                                            v-if="settings.has_ses_secret"
                                            class="mt-0.5 flex items-center gap-1 text-[10px] font-bold text-emerald-600"
                                        >
                                            <CheckCircle2 class="size-3" /> Đã
                                            thiết lập Secret Key
                                        </span>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="ses-region"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Region</Label
                                        >
                                        <Input
                                            id="ses-region"
                                            v-model="form.mail_ses_region"
                                            placeholder="us-east-1"
                                            class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Mailgun Config Options -->
                            <div
                                v-if="form.mail_driver === 'mailgun'"
                                class="space-y-4 border-t border-border/40 pt-4"
                            >
                                <h4
                                    class="dark:text-slate-350 text-[10px] font-extrabold tracking-wider text-slate-700 uppercase"
                                >
                                    Thông số Mailgun API
                                </h4>
                                <div
                                    class="grid grid-cols-1 gap-4 md:grid-cols-3"
                                >
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="mg-dom"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Domain</Label
                                        >
                                        <Input
                                            id="mg-dom"
                                            v-model="form.mail_mailgun_domain"
                                            placeholder="mg.yourdomain.com"
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="mg-sec"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Secret API Key</Label
                                        >
                                        <Input
                                            id="mg-sec"
                                            type="password"
                                            v-model="form.mail_mailgun_secret"
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            :placeholder="
                                                settings.has_mailgun_secret
                                                    ? 'Đã thiết lập — để trống nếu không đổi'
                                                    : 'Nhập Mailgun Secret Key'
                                            "
                                        />
                                        <span
                                            v-if="settings.has_mailgun_secret"
                                            class="mt-0.5 flex items-center gap-1 text-[10px] font-bold text-emerald-600"
                                        >
                                            <CheckCircle2 class="size-3" /> Đã
                                            thiết lập Secret Key
                                        </span>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="mg-end"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Đường dẫn API</Label
                                        >
                                        <Input
                                            id="mg-end"
                                            v-model="form.mail_mailgun_endpoint"
                                            placeholder="api.mailgun.net"
                                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- General Sender Settings -->
                            <div
                                class="space-y-4 border-t border-border/40 pt-4"
                            >
                                <h4
                                    class="dark:text-slate-350 text-[10px] font-extrabold tracking-wider text-slate-700 uppercase"
                                >
                                    Thông tin địa chỉ gửi (Sender Info)
                                </h4>
                                <div
                                    class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                >
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="mail-from"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Thư điện tử gửi đi (địa chỉ người
                                            gửi)</Label
                                        >
                                        <div class="relative flex items-center">
                                            <div
                                                class="pointer-events-none absolute left-3 text-muted-foreground"
                                            >
                                                <Mail
                                                    class="size-4 text-orange-500"
                                                />
                                            </div>
                                            <Input
                                                id="mail-from"
                                                type="email"
                                                v-model="form.mail_from_address"
                                                placeholder="no-reply@aventura.com"
                                                class="h-9 rounded-xl border-border bg-background pl-9.5 text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            />
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label
                                            for="mail-name"
                                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                            >Tên hiển thị người gửi (From
                                            Name)</Label
                                        >
                                        <div class="relative flex items-center">
                                            <div
                                                class="pointer-events-none absolute left-3 text-muted-foreground"
                                            >
                                                <User
                                                    class="size-4 text-orange-500"
                                                />
                                            </div>
                                            <Input
                                                id="mail-name"
                                                v-model="form.mail_from_name"
                                                placeholder="Hệ thống Aventura"
                                                class="h-9 rounded-xl border-border bg-background pl-9.5 text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Test Email Button -->
                            <div
                                class="space-y-2 border-t border-border/40 pt-4"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-border bg-background text-xs font-bold transition-colors hover:bg-muted"
                                    :disabled="testingEmail"
                                    @click="sendTestEmail"
                                >
                                    <Loader2
                                        v-if="testingEmail"
                                        class="size-4 animate-spin"
                                    />
                                    <Send v-else class="size-4" />
                                    <span>{{
                                        testingEmail
                                            ? 'Đang gửi thư điện tử thử...'
                                            : 'Gửi thư điện tử thử nghiệm'
                                    }}</span>
                                </Button>
                                <p
                                    class="text-[10px] leading-relaxed font-semibold text-muted-foreground"
                                >
                                    Gửi thư điện tử thử đến địa chỉ thư điện tử
                                    tài khoản đang đăng nhập để xác nhận cấu
                                    hình hoạt động.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Tab Content: Storage / Upload Config -->
                    <Card
                        v-if="activeTab === 'upload'"
                        class="animate-fade-in overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                    >
                        <CardHeader
                            class="border-b border-border/40 bg-muted/10"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-100"
                            >
                                <UploadCloud class="size-5 text-orange-500" />
                                Giới hạn dung lượng tải lên
                            </CardTitle>
                            <CardDescription
                                class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                                >Thiết lập dung lượng tệp tối đa (đơn vị: KB)
                                cho toàn bộ hệ thống để bảo vệ ổ đĩa máy
                                chủ.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-5 p-5">
                            <div class="grid grid-cols-1 gap-4">
                                <div class="grid gap-2">
                                    <Label
                                        for="menu-max"
                                        class="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300"
                                    >
                                        Dung lượng ảnh thực đơn tối đa
                                        <span
                                            class="text-[10px] font-normal text-muted-foreground"
                                            >(KB)</span
                                        >
                                    </Label>
                                    <Input
                                        id="menu-max"
                                        type="number"
                                        v-model="form.upload_menu_image_max"
                                        placeholder="Ví dụ: 2048"
                                        class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        required
                                    />
                                    <span
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Giới hạn dung lượng tệp cho ảnh món ăn
                                        của thực đơn nhà hàng. Ví dụ:
                                        <strong
                                            class="font-bold text-slate-700 dark:text-slate-300"
                                            >2048 KB</strong
                                        >
                                        = 2 MB.
                                    </span>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        for="invoice-max"
                                        class="flex items-center gap-1.5 text-xs font-bold text-slate-700 dark:text-slate-300"
                                    >
                                        Dung lượng hóa đơn / chứng từ tối đa
                                        <span
                                            class="text-[10px] font-normal text-muted-foreground"
                                            >(KB)</span
                                        >
                                    </Label>
                                    <Input
                                        id="invoice-max"
                                        type="number"
                                        v-model="form.upload_invoice_image_max"
                                        placeholder="Ví dụ: 4096"
                                        class="h-9 rounded-xl border-border bg-background font-mono text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                                        required
                                    />
                                    <span
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Giới hạn dung lượng tệp cho chứng từ
                                        nhập hàng PO, ảnh hóa đơn đối soát. Ví
                                        dụ:
                                        <strong
                                            class="font-bold text-slate-700 dark:text-slate-300"
                                            >4096 KB</strong
                                        >
                                        = 4 MB.
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Submit action footer -->
                    <div
                        class="flex items-center justify-between border-t border-border/40 bg-background pt-5"
                    >
                        <div
                            class="text-[10px] font-semibold text-muted-foreground"
                        >
                            Cấu hình sẽ có hiệu lực tức thì trên toàn bộ hệ
                            thống.
                        </div>
                        <Button
                            type="submit"
                            class="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 px-5 text-xs font-bold text-white shadow-xs transition-colors hover:from-orange-600 hover:to-amber-600"
                            :disabled="form.processing"
                        >
                            <Loader2
                                v-if="form.processing"
                                class="size-4 animate-spin"
                            />
                            <Check v-else class="size-4" />
                            <span>{{
                                form.processing
                                    ? 'Đang lưu cấu hình...'
                                    : 'Lưu cấu hình hệ thống'
                            }}</span>
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Right Column: AI Configuration Diagnostics Coach -->
            <div class="space-y-6 lg:col-span-1">
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardContent class="space-y-4 p-5">
                        <div
                            class="flex items-center justify-between border-b border-border/40 pb-3"
                        >
                            <h4
                                class="text-xs font-black tracking-wider text-muted-foreground uppercase"
                            >
                                Trợ lý AI cấu hình
                            </h4>
                            <span
                                class="animate-pulse rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                :class="
                                    hasActiveWarnings
                                        ? 'border-rose-500/20 bg-rose-500/10 text-rose-600'
                                        : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                "
                            >
                                {{
                                    hasActiveWarnings
                                        ? 'Khuyến nghị'
                                        : 'Đạt chuẩn'
                                }}
                            </span>
                        </div>

                        <!-- Chatbot Advices -->
                        <div v-if="activeTab === 'chatbot'" class="space-y-3.5">
                            <div
                                class="flex items-start gap-2.5 rounded-xl border p-3"
                                :class="
                                    thresholdAdvice.status === 'warning'
                                        ? 'border-amber-500/20 bg-amber-500/[0.03]'
                                        : 'border-border/30 bg-muted/10'
                                "
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg p-1"
                                    :class="
                                        thresholdAdvice.status === 'warning'
                                            ? 'bg-amber-500/10 text-amber-500'
                                            : 'bg-slate-500/10 text-slate-500'
                                    "
                                >
                                    <Bot class="size-4" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Ngưỡng đối sánh
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        {{ thresholdAdvice.text }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex items-start gap-2.5 rounded-xl border p-3"
                                :class="
                                    cacheTtlAdvice.status === 'warning'
                                        ? 'border-amber-500/20 bg-amber-500/[0.03]'
                                        : 'border-border/30 bg-muted/10'
                                "
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg p-1"
                                    :class="
                                        cacheTtlAdvice.status === 'warning'
                                            ? 'bg-amber-500/10 text-amber-500'
                                            : 'bg-slate-500/10 text-slate-500'
                                    "
                                >
                                    <Activity class="size-4" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Thời gian sống bộ nhớ đệm
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        {{ cacheTtlAdvice.text }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Mail Advices -->
                        <div v-if="activeTab === 'mail'" class="space-y-3.5">
                            <div
                                class="flex items-start gap-2.5 rounded-xl border p-3"
                                :class="
                                    mailDriverAdvice.status === 'caution'
                                        ? 'border-amber-500/20 bg-amber-500/[0.03]'
                                        : 'border-border/30 bg-muted/10'
                                "
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg p-1"
                                    :class="
                                        mailDriverAdvice.status === 'caution'
                                            ? 'bg-amber-500/10 text-amber-500'
                                            : 'bg-slate-500/10 text-slate-500'
                                    "
                                >
                                    <Mail class="size-4" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Mail Driver & SMTP
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        {{ mailDriverAdvice.text }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="form.mail_driver === 'smtp'"
                                class="flex items-start gap-2.5 rounded-xl border p-3"
                                :class="
                                    smtpPortAdvice.status === 'warning'
                                        ? 'border-rose-500/20 bg-rose-500/[0.03]'
                                        : 'border-border/30 bg-muted/10'
                                "
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg p-1"
                                    :class="
                                        smtpPortAdvice.status === 'warning'
                                            ? 'bg-rose-500/10 text-rose-500'
                                            : 'bg-slate-500/10 text-slate-500'
                                    "
                                >
                                    <Server class="size-4" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        SMTP Port Security
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        {{ smtpPortAdvice.text }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Advices -->
                        <div v-if="activeTab === 'upload'" class="space-y-3.5">
                            <div
                                class="flex items-start gap-2.5 rounded-xl border border-border/30 bg-muted/10 p-3"
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg bg-slate-500/10 p-1 text-slate-500"
                                >
                                    <UploadCloud class="size-4" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Disk Quota Limit
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Cấu hình KB giúp ngăn chặn tấn công từ
                                        chối dịch vụ (DoS) qua tải lên tệp ảnh
                                        dung lượng khổng lồ.
                                    </p>
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
        <DialogContent
            class="rounded-2xl border border-border/40 bg-card backdrop-blur-md sm:max-w-md"
        >
            <DialogHeader>
                <DialogTitle
                    class="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-slate-100"
                >
                    <Shield class="size-5 animate-pulse text-amber-500" />
                    Xác nhận thay đổi cấu hình Email
                </DialogTitle>
                <DialogDescription
                    class="mt-2 text-xs leading-relaxed font-semibold text-muted-foreground"
                >
                    Thay đổi cấu hình email sẽ ảnh hưởng đến toàn bộ hệ thống
                    gửi mail (OTP, thông báo, hóa đơn...). Bạn có chắc chắn muốn
                    lưu?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter
                class="mt-3 flex gap-2 border-t border-border/40 pt-4 sm:justify-end"
            >
                <Button
                    variant="outline"
                    @click="showConfirmDialog = false"
                    class="h-9 cursor-pointer rounded-xl border-border text-xs font-bold hover:bg-muted"
                    >Hủy bỏ</Button
                >
                <Button
                    class="h-9 cursor-pointer rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 text-xs font-bold text-white hover:from-orange-600 hover:to-amber-600"
                    @click="doSubmit"
                    :disabled="form.processing"
                >
                    <Loader2
                        v-if="form.processing"
                        class="mr-1 size-4 animate-spin"
                    />
                    Xác nhận lưu
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
