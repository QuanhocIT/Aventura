<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Bot,
    Building2,
    Calendar,
    Check,
    ChevronRight,
    ChevronLeft,
    Clock3,
    LineChart,
    Monitor,
    Package,
    Phone,
    Play,
    Pause,
    QrCode,
    Rocket,
    Settings,
    ShieldCheck,
    Sparkles,
    Star,
    UserPlus,
    Users,
    Utensils,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import NewsCard from '@/components/NewsCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';
import { register } from '@/routes';
import { toast } from 'vue-sonner';

interface Banner {
    id: number;
    title: string | null;
    subtitle: string | null;
    image_url: string;
    link_url: string | null;
}

interface NewsPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    category: string;
    featured_image_url: string | null;
    is_featured: boolean;
    published_at: string;
}

interface DbPlan {
    id: number;
    code: string;
    name: string;
    price: number;
    billing_cycle: string;
    max_branches: number | null;
    max_tables: number | null;
    max_users: number | null;
    features: Record<string, unknown>;
}

const props = defineProps<{
    canRegister: boolean;
    banners?: { hero: Banner[]; promo: Banner[] };
    latestNews?: NewsPost[];
    plans?: DbPlan[];
}>();

// ── Hero Slideshow ──────────────────────────────────────────────
const heroIndex = ref(0);
const heroDir = ref<'next' | 'prev'>('next');
let heroTimer: ReturnType<typeof setInterval> | null = null;

const heroBanners = computed(() => props.banners?.hero ?? []);
const promoBanners = computed(() => props.banners?.promo ?? []);
const firstPromoBanner = computed(() => promoBanners.value[0] ?? null);

const promoDismissed = ref(false);

function dismissPromo() {
    promoDismissed.value = true;
    localStorage.setItem('aventura_promo_dismissed', '1');
}

// ── Demo & Consultation Request Form ─────────────────────────────
const demoRestaurantName = ref('');
const demoRestaurantType = ref('restaurant');
const demoTablesCount = ref<number | null>(null);
const demoPhoneNumber = ref('');
const demoSpecialRequest = ref('');

function submitDemoRequest() {
    if (!demoRestaurantName.value.trim()) {
        toast.error('Vui lòng nhập tên nhà hàng hoặc quán ăn của bạn.');
        return;
    }
    if (!demoPhoneNumber.value.trim()) {
        toast.error('Vui lòng nhập số điện thoại liên hệ.');
        return;
    }
    
    // Simulate server side request and toast success
    toast.success(`Đăng ký dùng thử thành công! Đội ngũ kỹ thuật Aventura sẽ liên hệ tư vấn mô hình ${
        demoRestaurantType.value === 'restaurant' ? 'Nhà hàng / Quán ăn' : 
        demoRestaurantType.value === 'cafe' ? 'Cà phê / Trà sữa' : 
        demoRestaurantType.value === 'bar' ? 'Bar / Pub / Beer club' : 'Chuỗi nhiều chi nhánh'
    } với quy mô ${demoTablesCount.value || 10} bàn cho quý khách qua số điện thoại ${demoPhoneNumber.value} trong vòng 5 phút.`);
    
    // Reset Form
    demoRestaurantName.value = '';
    demoRestaurantType.value = 'restaurant';
    demoTablesCount.value = null;
    demoPhoneNumber.value = '';
    demoSpecialRequest.value = '';
}

function startHero() {
    if (heroBanners.value.length <= 1) {
return;
}

    heroTimer = setInterval(() => advanceHero('next'), 4000);
}

function stopHero() {
    if (heroTimer) {
 clearInterval(heroTimer); heroTimer = null; 
}
}

function advanceHero(dir: 'next' | 'prev') {
    heroDir.value = dir;
    const len = heroBanners.value.length;
    heroIndex.value = dir === 'next'
        ? (heroIndex.value + 1) % len
        : (heroIndex.value - 1 + len) % len;
}

function goHero(idx: number) {
    heroDir.value = idx > heroIndex.value ? 'next' : 'prev';
    heroIndex.value = idx;
    stopHero();
    startHero();
}

function navHero(dir: 'next' | 'prev') {
    advanceHero(dir);
    stopHero();
    startHero();
}

const staticPlans = [
    {
        code: 'free',
        name: 'Free',
        price: '0đ',
        cycle: '/tháng',
        maxBranches: '1 chi nhánh',
        maxTables: '10 bàn',
        maxUsers: '5 nhân viên',
        note: 'Gói cơ bản trải nghiệm miễn phí.',
        features: [
            '1 chi nhánh',
            '10 bàn hoạt động',
            '5 nhân viên',
            '500 MB dung lượng lưu trữ',
            'Rate limit: 60 yêu cầu/phút',
        ],
        unsupportedFeatures: [
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
            'Realtime sync & Advanced Analytics',
        ],
        isRecommended: false,
    },
    {
        code: 'pro',
        name: 'Pro',
        price: '499.000đ',
        cycle: '/tháng',
        maxBranches: 'Không giới hạn chi nhánh',
        maxTables: 'Không giới hạn bàn',
        maxUsers: 'Không giới hạn nhân viên',
        note: 'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
        features: [
            'Không giới hạn chi nhánh',
            'Không giới hạn bàn',
            'Không giới hạn nhân viên',
            '10 GB dung lượng lưu trữ',
            'Rate limit: 600 yêu cầu/phút',
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
            'Realtime sync & Advanced Analytics',
            'Hệ thống Audit Log bảo mật',
        ],
        isRecommended: true,
    },
    {
        code: 'max',
        name: 'Max',
        price: '999.000đ',
        cycle: '/tháng',
        maxBranches: 'Tối đa 10 chi nhánh',
        maxTables: 'Tối đa 300 bàn',
        maxUsers: 'Tối đa 80 nhân viên',
        note: 'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
        features: [
            'Tối đa 10 chi nhánh',
            'Tối đa 300 bàn',
            'Tối đa 80 nhân viên',
            '50 GB dung lượng lưu trữ',
            'Rate limit: 1.200 yêu cầu/phút',
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
            'Realtime sync & Advanced Analytics',
            'Hệ thống Audit Log bảo mật',
        ],
        isRecommended: false,
    },
    {
        code: 'ultra',
        name: 'Ultra',
        price: '1.999.000đ',
        cycle: '/tháng',
        maxBranches: 'Không giới hạn chi nhánh',
        maxTables: 'Không giới hạn bàn',
        maxUsers: 'Không giới hạn nhân viên',
        note: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
        features: [
            'Không giới hạn chi nhánh',
            'Không giới hạn bàn',
            'Không giới hạn nhân viên',
            '200 GB dung lượng lưu trữ',
            'Rate limit: 3.000 yêu cầu/phút',
            'AI dự báo nguyên liệu & tồn kho',
            'Thuật toán AI phát hiện gian lận',
            'Realtime sync & Advanced Analytics',
            'Hệ thống Audit Log bảo mật',
        ],
        isRecommended: false,
    },
];

const latestNewsList = computed(() => props.latestNews ?? []);

const planNotes: Record<string, string> = {
    free:  'Gói cơ bản trải nghiệm miễn phí.',
    pro:   'Tối ưu hiệu năng, chống thất thoát cho mô hình chuyên nghiệp.',
    max:   'Phù hợp cho chuỗi nhà hàng vừa và lớn.',
    ultra: 'Giải pháp tối thượng cho doanh nghiệp lớn & chuỗi rộng khắp.',
};

function buildDisplayPlan(db: DbPlan) {
    const lim = (v: number | null, unit: string) =>
        v === null || v === -1 ? `Không giới hạn ${unit}` : `${v} ${unit}`;

    const mb  = (db.features.max_storage_mb as number) ?? 500;
    const storage = mb >= 1024 ? `${mb / 1024} GB dung lượng lưu trữ` : `${mb} MB dung lượng lưu trữ`;
    const rate = (db.features.api_rate_limit as number) ?? 60;

    const always = [
        lim(db.max_branches, 'chi nhánh'),
        lim(db.max_tables, 'bàn'),
        lim(db.max_users, 'nhân viên'),
        storage,
        `Rate limit: ${rate.toLocaleString('vi-VN')} yêu cầu/phút`,
    ];

    const conditionals = [
        { key: 'ai_features',         label: 'AI dự báo nguyên liệu & tồn kho' },
        { key: 'ai_features',         label: 'Thuật toán AI phát hiện gian lận' },
        { key: 'realtime',            label: 'Realtime sync & Advanced Analytics' },
        { key: 'advanced_analytics',  label: 'Hệ thống Audit Log bảo mật' },
    ];

    const features: string[] = [...always];
    const unsupportedFeatures: string[] = [];
    const seen = new Set<string>();

    for (const { key, label } of conditionals) {
        if (seen.has(label)) {
continue;
}

        seen.add(label);

        if (db.features[key]) {
features.push(label);
} else {
unsupportedFeatures.push(label);
}
    }

    return {
        code:               db.code,
        name:               db.name,
        price:              db.price === 0 ? '0đ' : db.price.toLocaleString('vi-VN') + 'đ',
        cycle:              db.billing_cycle === 'monthly' ? '/tháng' : '/năm',
        note:               (db.features.description as string | undefined) || planNotes[db.code] || '',
        features,
        unsupportedFeatures,
        isRecommended:      db.code === 'pro',
    };
}

// Pricing toggle: monthly / yearly
const billingCycle = ref<'monthly' | 'yearly'>('monthly');

const displayPlans = computed(() => {
    const plans = props.plans?.length ? props.plans.map(buildDisplayPlan) : staticPlans;

    if (billingCycle.value === 'yearly') {
        return plans.map(p => {
            if (p.price === '0đ') {
return p;
}

            // Strip existing price, compute 20% discount
            const dbPlan = props.plans?.find(d => d.code === p.code);

            if (!dbPlan || dbPlan.price === 0) {
return p;
}

            const yearlyMonthly = Math.round(dbPlan.price * 0.8);

            return {
                ...p,
                price: yearlyMonthly.toLocaleString('vi-VN') + 'đ',
                cycle: '/tháng (thanh toán năm)',
            };
        });
    }

    return plans;
});

// Count-up animation for stats section
const statsVisible   = ref(false);
const countRestaurants = ref(0);
const countOrders      = ref(0);
const countUptime      = ref(0);
let statsObserver: IntersectionObserver | null = null;

function animateCount(target: number, setter: (v: number) => void, duration = 1800) {
    const start     = performance.now();
    const step = (now: number) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased    = 1 - Math.pow(1 - progress, 3);
        setter(Math.round(target * eased));

        if (progress < 1) {
requestAnimationFrame(step);
}
    };
    requestAnimationFrame(step);
}

function startCountUp() {
    if (statsVisible.value) {
return;
}

    statsVisible.value = true;
    animateCount(500,  v => countRestaurants.value = v);
    animateCount(3000, v => countOrders.value      = v);
    animateCount(999,  v => countUptime.value      = v);
}

const featureMap = [
    {
        step: 1,
        icon: QrCode,
        title: 'QR order',
        description:
            'Khách quét QR tại bàn -> tạo order nhanh, giảm sai sót, giảm tải nhân viên.',
        category: 'sales',
        color: 'indigo',
        status: 'Realtime',
        dbTables: ['orders', 'order_items', 'tables'],
        techNote:
            'Sử dụng WebSockets qua Pusher/Laravel Echo giúp đồng bộ order tức thì từ điện thoại khách tới bếp.',
        flow: {
            trigger: 'Khách quét QR, gọi món trên di động',
            process: 'Laravel Broadcast -> WebSockets Event',
            storage: 'Lưu schema orders & order_items',
        },
        sla: 'Đồng bộ < 150ms',
    },
    {
        step: 2,
        icon: Monitor,
        title: 'Kitchen display',
        description:
            'Đơn lên bếp theo realtime, trạng thái rõ ràng, ít nhầm lẫn.',
        category: 'sales',
        color: 'emerald',
        status: 'Realtime',
        dbTables: ['kds_tickets', 'order_items'],
        techNote:
            'KDS lắng nghe sự kiện đẩy đơn từ POS/QR order để cập nhật trạng thái nấu ăn tức thời.',
        flow: {
            trigger: 'Hệ thống nhận order mới của khách',
            process: 'Pusher Broadcast -> Màn hình bếp KDS',
            storage: 'Đồng bộ trạng thái kds_tickets',
        },
        sla: 'Trực quan realtime',
    },
    {
        step: 3,
        icon: Package,
        title: 'Inventory',
        description:
            'Trừ kho theo định lượng, theo dõi nhập xuất, cảnh báo thiếu nguyên liệu.',
        category: 'mgmt',
        color: 'amber',
        status: 'Auto',
        dbTables: ['ingredients', 'recipes', 'inventory_transactions'],
        techNote:
            'Tự động trừ nguyên vật liệu trong kho dựa trên công thức món ăn (recipe) ngay khi thanh toán hóa đơn.',
        flow: {
            trigger: 'POS hoàn tất thanh toán hóa đơn',
            process: 'Recipe Engine tính toán định lượng món',
            storage: 'Trừ stock, ghi vết inventory_transactions',
        },
        sla: 'Chính xác 100%',
    },
    {
        step: 4,
        icon: Users,
        title: 'Staff',
        description:
            'Quản lý nhân sự, ca làm, vai trò, chấm công và hỗ trợ tính lương.',
        category: 'mgmt',
        color: 'sky',
        status: 'Secure',
        dbTables: ['users', 'roles', 'shifts', 'time_sheets'],
        techNote:
            'Phân quyền chi tiết (Spatie ACL) kết hợp chấm công theo ca thực tế để lập bảng tính lương tự động.',
        flow: {
            trigger: 'Nhân viên check-in ca làm việc',
            process: 'Kiểm tra Spatie Policy & IP chi nhánh',
            storage: 'Ghi nhận bảng chấm công time_sheets',
        },
        sla: 'Bảo mật chặt chẽ',
    },
    {
        step: 5,
        icon: ShieldCheck,
        title: 'Audit log',
        description:
            'Ghi vết thao tác nhạy cảm để tra soát, giảm phụ thuộc vào tính trung thực.',
        category: 'system',
        color: 'teal',
        status: 'Secure',
        dbTables: ['audit_logs'],
        techNote:
            'Middleware tự động chụp lại trạng thái dữ liệu trước/sau khi thay đổi và định danh IP người thực hiện.',
        flow: {
            trigger: 'Thay đổi giá món, xóa đơn hoặc hủy hóa đơn',
            process: 'Audit Trail Middleware ghi nhận payload',
            storage: 'Lưu vết immutable vào bảng audit_logs',
        },
        sla: 'Không thể tẩy xóa',
    },
    {
        step: 6,
        icon: BarChart3,
        title: 'Analytics',
        description:
            'Theo dõi doanh thu, hiệu suất, xu hướng món bán chạy, báo cáo vận hành.',
        category: 'sales',
        color: 'rose',
        status: 'Sync',
        dbTables: ['orders', 'payments'],
        techNote:
            'Các chỉ số tổng hợp được cache bằng Redis, tối ưu hóa database index để tải báo cáo chuỗi siêu tốc.',
        flow: {
            trigger: 'Quản lý mở xem báo cáo doanh thu',
            process: 'Redis Cache Checker -> DB Query Aggregates',
            storage: 'Kết xuất JSON báo cáo hiệu suất',
        },
        sla: 'Truy vấn < 10ms',
    },
    {
        step: 7,
        icon: Building2,
        title: 'Multi-branch',
        description:
            'Một tài khoản quản lý nhiều chi nhánh, dữ liệu tách biệt theo tenant.',
        category: 'mgmt',
        color: 'blue',
        status: 'Tenant Scope',
        dbTables: ['branches', 'tenant_scopes'],
        techNote:
            'Áp dụng Laravel Global Query Scope tự động lọc mọi câu lệnh SQL theo branch_id của tenant.',
        flow: {
            trigger: 'User thực hiện bất kỳ thao tác đọc/ghi',
            process: 'Global Query Scope tự động chèn branch_id',
            storage: 'Cô lập dữ liệu tuyệt đối giữa các Tenant',
        },
        sla: 'Bảo mật dữ liệu',
    },
    {
        step: 8,
        icon: Bot,
        title: 'AI insights',
        description:
            'Tổng hợp tín hiệu dữ liệu để gợi ý cảnh báo, dự báo và phát hiện bất thường.',
        category: 'system',
        color: 'violet',
        status: 'AI Model',
        dbTables: ['ai_predictions', 'stock_trends'],
        techNote:
            'Hồi quy tuyến tính học máy chạy nền để phân tích xu hướng và đưa ra cảnh báo sớm về hao hụt kho.',
        flow: {
            trigger: 'Chạy Cronjob định kỳ hàng đêm',
            process: 'Ingest lịch sử orders & stock -> AI Prediction',
            storage: 'Lưu dự báo xu hướng vào ai_predictions',
        },
        sla: 'Chính xác > 92%',
    },
];

const categories = [
    { key: 'all', label: 'Tất cả' },
    { key: 'sales', label: 'Bán hàng & POS' },
    { key: 'mgmt', label: 'Quản lý & Hậu cần' },
    { key: 'system', label: 'Hệ thống & AI' },
] as const;

const activeCategory = ref<string>('all');
const activeFeatureTitle = ref<string>('QR order');

const filteredFeatureMap = computed(() => {
    if (activeCategory.value === 'all') {
return featureMap;
}

    return featureMap.filter((item) => item.category === activeCategory.value);
});

const activeFeature = computed(() => {
    return (
        featureMap.find((f) => f.title === activeFeatureTitle.value) ||
        featureMap[0]
    );
});

const colorGlowBorder = computed(() => {
    switch (activeFeature.value.color) {
        case 'indigo':
            return 'rgba(99, 102, 241, 0.4)';
        case 'emerald':
            return 'rgba(16, 185, 129, 0.4)';
        case 'amber':
            return 'rgba(245, 158, 11, 0.4)';
        case 'sky':
            return 'rgba(14, 165, 233, 0.4)';
        case 'rose':
            return 'rgba(244, 63, 94, 0.4)';
        case 'blue':
            return 'rgba(59, 130, 246, 0.4)';
        case 'teal':
            return 'rgba(20, 184, 166, 0.4)';
        case 'violet':
            return 'rgba(139, 92, 246, 0.4)';
        case 'cyan':
            return 'rgba(6, 182, 212, 0.4)';
        default:
            return 'rgba(255, 255, 255, 0.1)';
    }
});

const goToNextStep = () => {
    const currentIndex = featureMap.findIndex(
        (f) => f.title === activeFeatureTitle.value,
    );
    const nextIndex = (currentIndex + 1) % featureMap.length;
    activeFeatureTitle.value = featureMap[nextIndex].title;

    if (
        activeCategory.value !== 'all' &&
        activeCategory.value !== featureMap[nextIndex].category
    ) {
        activeCategory.value = 'all';
    }
};

const isAutoPlaying = ref(false);
let autoPlayTimer: any = null;

const startAutoPlay = () => {
    isAutoPlaying.value = true;
    autoPlayTimer = setInterval(() => {
        goToNextStep();
    }, 3000);
};

const stopAutoPlay = () => {
    isAutoPlaying.value = false;

    if (autoPlayTimer) {
        clearInterval(autoPlayTimer);
        autoPlayTimer = null;
    }
};

const toggleAutoPlay = () => {
    if (isAutoPlaying.value) {
        stopAutoPlay();
    } else {
        startAutoPlay();
    }
};

onMounted(() => {
    promoDismissed.value = localStorage.getItem('aventura_promo_dismissed') === '1';
    stickyCtaDismissed.value = localStorage.getItem('aventura_sticky_cta_dismissed') === '1';
    startAutoPlay();
    startHero();

    heroObserver = new IntersectionObserver(
        ([entry]) => {
 showStickyCta.value = !entry.isIntersecting; 
},
        { threshold: 0.1 }
    );
    const heroEl = document.getElementById('hero-section');

    if (heroEl) {
heroObserver.observe(heroEl);
}

    // Count-up stats observer
    statsObserver = new IntersectionObserver(
        ([entry]) => {
 if (entry.isIntersecting) {
startCountUp();
} 
},
        { threshold: 0.3 }
    );
    const statsEl = document.getElementById('stats-section');

    if (statsEl) {
statsObserver.observe(statsEl);
}
});

onUnmounted(() => {
    stopAutoPlay();
    stopHero();
    heroObserver?.disconnect();
    statsObserver?.disconnect();
});

const faq = [
    {
        q: 'Tôi có thể dùng thử trước khi trả tiền không?',
        a: 'Hoàn toàn. Gói Free không yêu cầu thẻ tín dụng — đăng ký ngay và vận hành thực tế với 1 chi nhánh, 10 bàn, 5 nhân viên. Nâng gói bất kỳ lúc nào khi cần mở rộng.',
    },
    {
        q: 'Dữ liệu nhà hàng của tôi có được bảo mật không?',
        a: 'Có. Mỗi nhà hàng có tenant scope riêng — dữ liệu cô lập tuyệt đối. Mọi thao tác nhạy cảm (xóa đơn, thay đổi giá) đều được ghi vào audit log không thể xóa và có dấu vết IP.',
    },
    {
        q: 'Nâng gói hoặc hủy gói có mất dữ liệu không?',
        a: 'Không. Toàn bộ lịch sử đơn hàng, kho và nhân viên được giữ nguyên khi chuyển gói. Hạ gói sẽ khóa tính năng nâng cao nhưng không xóa bất kỳ dữ liệu nào.',
    },
    {
        q: 'Nếu gặp sự cố, hỗ trợ kỹ thuật ra sao?',
        a: 'Chatbot tích hợp trả lời 24/7 cho câu hỏi thường gặp. Ngoài ra có hệ thống ticket — team hỗ trợ phản hồi trong vòng 4 giờ làm việc (giờ hành chính).',
    },
    {
        q: 'Aventura có phù hợp với chuỗi nhiều chi nhánh không?',
        a: 'Có. Gói Max và Ultra hỗ trợ multi-branch với dữ liệu tách biệt theo tenant. Một tài khoản quản lý tất cả chi nhánh, báo cáo hợp nhất theo thời gian thực.',
    },
    {
        q: 'Tích hợp với máy in hóa đơn và phần cứng POS không?',
        a: 'Hệ thống hỗ trợ in bill qua mạng LAN. Với QR order, khách gọi món thẳng từ điện thoại nên giảm phụ thuộc vào máy tính tiền truyền thống.',
    },
];

const openFaqIndex = ref<number | null>(null);

function toggleFaq(i: number) {
    openFaqIndex.value = openFaqIndex.value === i ? null : i;
}

const howItWorksSteps = [
    {
        step: 1,
        icon: UserPlus,
        title: 'Đăng ký tài khoản',
        description: '30 giây, không cần thẻ tín dụng. Gói Free hoạt động ngay lập tức.',
    },
    {
        step: 2,
        icon: Settings,
        title: 'Thiết lập nhà hàng',
        description: 'Chatbot hướng dẫn từng bước: menu → bàn → nhân viên → in bill.',
    },
    {
        step: 3,
        icon: Zap,
        title: 'Vận hành ngay hôm nay',
        description: 'QR order, quản lý bếp, kiểm kho — chạy thật trong 30 phút đầu.',
    },
];

const testimonials = [
    {
        name: 'Trần Văn Hùng',
        restaurant: 'Phở Thái Sơn (3 chi nhánh)',
        text: 'Audit log giúp tôi tìm ra nhân viên thu tiền sai trong 5 phút. Gói Pro đáng từng đồng.',
        stars: 5,
    },
    {
        name: 'Nguyễn Thu Hà',
        restaurant: 'Cơm Tấm Hà My',
        text: 'AI dự báo nguyên liệu chính xác đến 90%. Giảm được 20% lãng phí thực phẩm mỗi tuần.',
        stars: 5,
    },
    {
        name: 'Lê Quốc Bảo',
        restaurant: 'Chuỗi Lẩu Đại Phú Gia',
        text: 'Multi-branch không cần 5 tab, 1 dashboard thấy hết. Tiết kiệm thời gian quản lý gấp đôi.',
        stars: 5,
    },
];

const showStickyCta = ref(false);
const stickyCtaDismissed = ref(false);

function dismissStickyCta() {
    stickyCtaDismissed.value = true;
    localStorage.setItem('aventura_sticky_cta_dismissed', '1');
}

let heroObserver: IntersectionObserver | null = null;

const demoTabs = [
    { key: 'pos', label: 'POS' },
    { key: 'kds', label: 'Bếp' },
    { key: 'report', label: 'Báo cáo' },
] as const;

const activeDemo = ref<(typeof demoTabs)[number]['key']>('pos');

const demoState = computed(() => {
    if (activeDemo.value === 'kds') {
        return {
            title: 'Kitchen board',
            left: ['2 order chờ', '1 order đang làm', '0 trễ SLA'],
            right: [
                { label: 'Bún bò', status: 'Đang làm' },
                { label: 'Cơm gà', status: 'Sẵn sàng' },
                { label: 'Trà đào', status: 'Chờ in bill' },
            ],
        };
    }

    if (activeDemo.value === 'report') {
        return {
            title: 'Daily snapshot',
            left: [
                'Doanh thu: 12,8M',
                'Tỷ lệ hoàn thành: 98%',
                'Món bán chạy: Phở bò',
            ],
            right: [
                { label: 'Giờ cao điểm', status: '11:30 - 13:30' },
                { label: 'Cảnh báo kho', status: '2 nguyên liệu thấp' },
                { label: 'Audit', status: '1 thay đổi giá' },
            ],
        };
    }

    return {
        title: 'POS checkout',
        left: ['Bàn 12', '3 món', 'Tổng: 168.000đ'],
        right: [
            { label: 'Phở bò tái', status: 'x2' },
            { label: 'Trà chanh', status: 'x1' },
            { label: 'Thanh toán', status: 'Hoàn tất' },
        ],
    };
});
</script>

<template>
    <AppTopbarLayout transparent>
        <Head title="Aventura | SaaS quản lý nhà hàng" />

        <!-- ── Promo Strip (2.8) ──────────────────────────────────────── -->
        <Transition name="promo-strip">
            <div
                v-if="firstPromoBanner && !promoDismissed"
                class="border-y border-green-500/20 bg-gradient-to-r from-green-500/10 via-emerald-400/8 to-green-500/10"
            >
                <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 lg:px-8">
                    <!-- Badge -->
                    <span class="hidden shrink-0 items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/15 px-2.5 py-0.5 text-xs font-semibold text-green-700 sm:inline-flex dark:text-green-300">
                        🎉 {{ firstPromoBanner.title ?? 'Khuyến mãi' }}
                    </span>

                    <!-- Middle text -->
                    <p class="flex-1 text-center text-sm text-green-800 dark:text-green-200">
                        <span class="font-semibold sm:hidden">{{ firstPromoBanner.title }} </span>
                        <span class="opacity-80">{{ firstPromoBanner.subtitle }}</span>
                    </p>

                    <!-- CTA -->
                    <a
                        v-if="firstPromoBanner.link_url"
                        :href="firstPromoBanner.link_url"
                        class="shrink-0 rounded-full bg-green-600 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500"
                    >
                        Bắt đầu →
                    </a>

                    <!-- Dismiss -->
                    <button
                        @click="dismissPromo"
                        class="shrink-0 rounded p-1 text-green-700/50 transition hover:text-green-700 focus:outline-none dark:text-green-300/50 dark:hover:text-green-300"
                        aria-label="Đóng thông báo"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </Transition>

        <!-- ── Premium Travel-Inspired Hero Section ────────────────────────── -->
        <section
            id="hero-section"
            class="relative overflow-hidden px-4 pt-24 pb-16 lg:px-8 lg:pt-28 lg:pb-20 bg-cover bg-center"
            style="background-image: linear-gradient(135deg, rgba(8, 10, 15, 0.93) 0%, rgba(15, 20, 30, 0.82) 50%, rgba(8, 10, 15, 0.91) 100%), url('/restaurant_hero_bg.png');"
        >
            <div class="mx-auto grid max-w-7xl gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center relative z-10">
                
                <!-- Left Column: Premium Value Proposition & Feature Badges -->
                <div class="flex flex-col justify-center">
                    <!-- Amber Subtitle -->
                    <span class="text-amber-400 font-extrabold tracking-wider mb-4 text-xs sm:text-sm uppercase block">
                        VẬN HÀNH THÔNG MINH – CHỐNG THẤT THOÁT TUYỆT ĐỐI
                    </span>
                    
                    <!-- Massive Headline -->
                    <h1 class="max-w-2xl text-4xl font-extrabold tracking-tight lg:text-6xl text-white font-sans leading-[1.12]">
                        Vận hành nhà hàng <br />
                        <span class="text-amber-400">vượt trội</span> cùng Aventura
                    </h1>
                    
                    <!-- Description -->
                    <p class="mt-6 max-w-xl text-base leading-relaxed text-zinc-300 lg:text-lg">
                        Hệ thống quản trị SaaS tối ưu cho mọi mô hình: từ nhà hàng, quán cà phê đến chuỗi kinh doanh. Tự động hóa QR order, định lượng nguyên vật liệu kho, giám sát doanh thu tức thì và báo cáo vận hành thông minh bằng AI.
                    </p>
                    
                    <!-- Glassmorphic Tag Badges in Travel layout style -->
                    <div class="mt-10 flex flex-wrap gap-3.5">
                        <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 backdrop-blur-md px-4 py-3 text-xs sm:text-sm font-bold text-white shadow-lg hover:bg-white/10 transition-all duration-300">
                            <span class="text-amber-400 text-sm">✨</span> AI thông minh
                        </div>
                        <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 backdrop-blur-md px-4 py-3 text-xs sm:text-sm font-bold text-white shadow-lg hover:bg-white/10 transition-all duration-300">
                            <span class="text-amber-400 text-sm">⚡</span> Đồng bộ Realtime
                        </div>
                        <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 backdrop-blur-md px-4 py-3 text-xs sm:text-sm font-bold text-white shadow-lg hover:bg-white/10 transition-all duration-300">
                            <span class="text-amber-400 text-sm">🔒</span> Bảo mật Audit
                        </div>
                        <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 backdrop-blur-md px-4 py-3 text-xs sm:text-sm font-bold text-white shadow-lg hover:bg-white/10 transition-all duration-300">
                            <span class="text-amber-400 text-sm">🎁</span> Dùng miễn phí
                        </div>
                    </div>
                </div>

                <!-- Right Column: Interactive Live Demo widget styled in travel-glassmorphism -->
                <div class="relative w-full max-w-lg mx-auto lg:ml-auto rounded-2xl border border-white/10 bg-zinc-950/75 backdrop-blur-xl shadow-2xl p-6 sm:p-8 transition-all duration-500 hover:border-amber-500/25">
                    
                    <!-- Header with Live Demo tag -->
                    <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-5">
                        <div>
                            <span class="text-xs text-amber-400 font-extrabold tracking-widest uppercase block mb-1">
                                Live demo
                            </span>
                            <h2 class="text-2xl font-bold text-white tracking-tight">
                                {{ demoState.title }}
                            </h2>
                        </div>
                        
                        <!-- Glowing Badge -->
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-[10px] font-bold text-amber-400 uppercase tracking-wider animate-pulse">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            Interactive
                        </span>
                    </div>
                    
                    <!-- Navigation Tab triggers -->
                    <div class="flex gap-2 bg-white/5 border border-white/10 p-1.5 rounded-xl">
                        <button
                            v-for="tab in demoTabs"
                            :key="tab.key"
                            class="flex-1 cursor-pointer rounded-lg py-2.5 text-xs font-bold transition-all duration-300"
                            :class="activeDemo === tab.key
                                ? 'bg-amber-500 text-zinc-950 shadow-lg font-extrabold scale-[1.02]'
                                : 'text-zinc-300 hover:bg-white/10 hover:text-white'"
                            @click="activeDemo = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Flow Realtime Visual Dashboard Panel -->
                    <div class="mt-5 rounded-xl border border-white/10 bg-white/5 p-4 shadow-inner">
                        <!-- Panel header -->
                        <div class="flex items-center justify-between border-b border-white/10 pb-3 mb-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span>
                                <span class="text-xs font-bold text-white uppercase tracking-wider">
                                    {{ activeDemo === 'pos' ? 'POS Checkout' : activeDemo === 'kds' ? 'Màn hình Bếp KDS' : 'Báo cáo thông minh' }}
                                </span>
                            </div>
                            <span class="text-[10px] font-bold text-amber-400 uppercase tracking-widest bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/10">
                                Realtime
                            </span>
                        </div>
                        
                        <!-- TAB 1: POS Checkout Visual Mockup -->
                        <div v-if="activeDemo === 'pos'" class="grid gap-3 sm:grid-cols-2">
                            <!-- POS Bill summary -->
                            <div class="relative space-y-3.5 rounded-xl bg-zinc-950/40 border border-white/10 p-4 text-xs flex flex-col justify-between h-[125px] sm:h-[145px]">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-zinc-400">
                                        <span>Bàn phục vụ:</span>
                                        <span class="font-bold text-white bg-white/10 px-2 py-0.5 rounded text-[10px]">Bàn 12</span>
                                    </div>
                                    <div class="flex items-center justify-between text-zinc-400">
                                        <span>Số lượng món:</span>
                                        <span class="font-bold text-white">03 món</span>
                                    </div>
                                </div>
                                
                                <!-- Receipt decorative dots line -->
                                <div class="border-t border-dashed border-white/20 my-1"></div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-400 font-medium">Tổng tiền:</span>
                                    <span class="text-lg font-extrabold text-amber-400 tracking-tight">168.000đ</span>
                                </div>
                            </div>
                            
                            <!-- POS Order list details -->
                            <div class="space-y-2.5 rounded-xl bg-zinc-950/40 border border-white/10 p-4 text-xs h-[125px] sm:h-[145px] flex flex-col justify-between">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-zinc-200 py-1 border-b border-white/5">
                                        <span class="flex items-center gap-1.5"><span class="text-amber-400">🍜</span> Phở bò tái</span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-500/15 text-indigo-400 border border-indigo-500/10">x2</span>
                                    </div>
                                    <div class="flex items-center justify-between text-zinc-200 py-1 border-b border-white/5">
                                        <span class="flex items-center gap-1.5"><span class="text-amber-400">🍋</span> Trà chanh</span>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-500/15 text-indigo-400 border border-indigo-500/10">x1</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="font-semibold text-zinc-400">Thanh toán</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-500/20 text-emerald-400 border border-emerald-500/25 tracking-wide uppercase">Hoàn tất</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- TAB 2: KDS Kitchen Board Visual Mockup -->
                        <div v-else-if="activeDemo === 'kds'" class="grid gap-3 sm:grid-cols-2">
                            <!-- KDS stats metrics card -->
                            <div class="space-y-3 rounded-xl bg-zinc-950/40 border border-white/10 p-4 text-xs h-[125px] sm:h-[145px] flex flex-col justify-between">
                                <div class="flex items-center justify-between py-1 border-b border-white/5">
                                    <span class="text-zinc-400 font-medium">Order chờ chế biến:</span>
                                    <span class="font-bold text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded text-[10px] animate-pulse">2 đơn</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-white/5">
                                    <span class="text-zinc-400 font-medium">Đang chế biến:</span>
                                    <span class="font-bold text-sky-400 bg-sky-400/10 px-2 py-0.5 rounded text-[10px]">1 đơn</span>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-zinc-400 font-medium">Trễ thời gian (SLA):</span>
                                    <span class="font-bold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded text-[10px]">0 đơn</span>
                                </div>
                            </div>
                            
                            <!-- KDS cooking ticket list -->
                            <div class="space-y-2 rounded-xl bg-zinc-950/40 border border-white/10 p-3 text-[11px] leading-normal h-[125px] sm:h-[145px] flex flex-col justify-between">
                                <div class="flex items-center justify-between p-1.5 rounded bg-white/5 border border-white/5">
                                    <span class="text-white font-bold">1. Bún bò Huế</span>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/20">Đang làm</span>
                                </div>
                                <div class="flex items-center justify-between p-1.5 rounded bg-white/5 border border-white/5">
                                    <span class="text-white font-bold">2. Cơm gà chiên</span>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/25">Sẵn sàng</span>
                                </div>
                                <div class="flex items-center justify-between p-1.5 rounded bg-white/5 border border-white/5">
                                    <span class="text-zinc-300 font-bold">3. Trà đào cam sả</span>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-white/10 text-zinc-300 border border-white/10">In bill</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- TAB 3: Executive Analytics Report Visual Mockup -->
                        <div v-else-if="activeDemo === 'report'" class="grid gap-3 sm:grid-cols-2">
                            <!-- Report metrics KPI -->
                            <div class="space-y-3.5 rounded-xl bg-zinc-950/40 border border-white/10 p-4 text-xs h-[125px] sm:h-[145px] flex flex-col justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-amber-400 text-sm">💰</span>
                                    <div class="flex flex-col">
                                        <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold">Doanh thu trong ngày</span>
                                        <span class="text-base font-extrabold text-white">12,8M VNĐ</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 border-t border-white/5 pt-2">
                                    <span class="text-emerald-400 text-sm">📈</span>
                                    <div class="flex flex-col">
                                        <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold">Hoàn thành order</span>
                                        <span class="text-xs font-bold text-white">98% (SLA tốt)</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 border-t border-white/5 pt-2">
                                    <span class="text-rose-400 text-sm">🏆</span>
                                    <div class="flex flex-col">
                                        <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold">Món bán chạy nhất</span>
                                        <span class="text-xs font-bold text-amber-400">Phở bò tái nạm</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- AI and Audit Signals -->
                            <div class="space-y-2 rounded-xl bg-zinc-950/40 border border-white/10 p-3 text-xs leading-normal h-[125px] sm:h-[145px] flex flex-col justify-between">
                                <div class="flex items-center justify-between p-1.5 border-b border-white/5 text-zinc-200">
                                    <span class="flex items-center gap-1.5">⚡ Cao điểm</span>
                                    <span class="font-bold text-sky-400">11:30 - 13:30</span>
                                </div>
                                <div class="flex items-center justify-between p-1.5 border-b border-white/5 text-zinc-200">
                                    <span class="flex items-center gap-1.5">⚠️ Kho tồn</span>
                                    <span class="font-bold text-rose-400">2 nguyên liệu thấp</span>
                                </div>
                                <div class="flex items-center justify-between p-1.5 text-zinc-200">
                                    <span class="flex items-center gap-1.5">🔒 Tra soát</span>
                                    <span class="font-bold text-amber-400">1 thay đổi giá</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CTA button at the bottom of the widget -->
                    <div class="mt-6 pt-2 border-t border-white/10">
                        <Button as-child size="lg" class="w-full bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-600 hover:to-yellow-700 text-zinc-950 font-extrabold py-4 text-xs tracking-wider uppercase shadow-lg shadow-amber-500/20 active:scale-95 transition-all duration-300">
                            <Link :href="register()">Đăng ký dùng thử miễn phí</Link>
                        </Button>
                    </div>

                </div>
                
            </div>
        </section>

        <!-- ── Cách hoạt động ─────────────────────────────────── -->
        <section class="px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="text-center">
                    <Badge variant="outline" class="mb-3">3 bước đơn giản</Badge>
                    <h2 class="text-3xl font-semibold">Bắt đầu trong 30 phút</h2>
                    <p class="mx-auto mt-3 max-w-xl text-muted-foreground">
                        Không cần cài đặt phức tạp. Từ đăng ký đến vận hành thật chỉ với 3 bước.
                    </p>
                </div>
                <div class="relative mt-10 grid gap-4 md:grid-cols-3">
                    <div
                        v-for="(step, i) in howItWorksSteps"
                        :key="step.step"
                        class="group relative flex flex-col items-center rounded-2xl border border-border bg-card p-6 text-center transition-all hover:-translate-y-1 hover:shadow-md"
                    >
                        <!-- Connector arrow -->
                        <div
                            v-if="i < 2"
                            class="absolute -right-2.5 top-1/2 z-10 hidden -translate-y-1/2 md:block"
                        >
                            <ChevronRight class="size-5 text-muted-foreground" />
                        </div>
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-primary/20 bg-primary/5 text-primary transition-transform group-hover:scale-105">
                            <component :is="step.icon" class="size-7" />
                        </div>
                        <span class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Bước {{ step.step }}</span>
                        <h3 class="mb-2 text-base font-semibold">{{ step.title }}</h3>
                        <p class="text-sm leading-relaxed text-muted-foreground">{{ step.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="features"
            class="relative mt-0 overflow-hidden border-y border-border bg-muted/30 px-4 py-16 lg:px-8"
        >
            <!-- Decorative subtle background grids or glows -->
            <div
                class="absolute inset-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] bg-[size:14px_24px]"
            ></div>

            <div class="relative z-10 mx-auto max-w-7xl">
                <div
                    class="flex flex-col justify-between gap-6 md:flex-row md:items-end"
                >
                    <div class="max-w-2xl">
                        <Badge
                            variant="outline"
                            class="mb-3 border-primary/30 bg-primary/5 text-primary"
                        >
                            Kiến Trúc Minh Bạch
                        </Badge>
                        <h2
                            class="bg-gradient-to-r from-foreground via-foreground/90 to-muted-foreground bg-clip-text text-3xl font-bold tracking-tight text-transparent sm:text-4xl"
                        >
                            Map tính năng theo DB + vận hành
                        </h2>
                        <p
                            class="mt-3 text-sm leading-relaxed text-muted-foreground sm:text-base"
                        >
                            Không marketing mơ hồ. Đây là các lớp chức năng khớp
                            trực tiếp với schema database và báo cáo kỹ thuật
                            thực tế đã chốt.
                        </p>
                    </div>

                    <!-- Category Pills Filter -->
                    <div
                        class="flex w-fit flex-wrap gap-1.5 rounded-xl border border-border/80 bg-muted p-1"
                    >
                        <button
                            v-for="cat in categories"
                            :key="cat.key"
                            @click="activeCategory = cat.key"
                            class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all duration-200"
                            :class="
                                activeCategory === cat.key
                                    ? 'bg-background font-bold text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-background/40 hover:text-foreground'
                            "
                        >
                            {{ cat.label }}
                        </button>
                    </div>
                </div>

                <!-- Grid Split: 12 Columns -->
                <div class="mt-10 grid items-start gap-8 lg:grid-cols-12">
                    <!-- LEFT COLUMN: Operations & Data Flow Console (5/12 columns) -->
                    <div class="space-y-4 lg:col-span-5">
                        <div
                            class="relative flex min-h-[480px] flex-col justify-between overflow-hidden rounded-2xl border bg-zinc-950 p-6 text-zinc-100 shadow-2xl transition-all duration-500"
                            :style="`border-color: ${colorGlowBorder}; box-shadow: 0 10px 30px -10px ${colorGlowBorder}`"
                        >
                            <!-- Color Glow Effects behind the console -->
                            <div
                                class="absolute -top-16 -right-16 h-36 w-36 rounded-full opacity-40 blur-[80px] transition-all duration-500"
                                :class="{
                                    'bg-indigo-500':
                                        activeFeature.color === 'indigo',
                                    'bg-emerald-500':
                                        activeFeature.color === 'emerald',
                                    'bg-amber-500':
                                        activeFeature.color === 'amber',
                                    'bg-sky-500': activeFeature.color === 'sky',
                                    'bg-rose-500':
                                        activeFeature.color === 'rose',
                                    'bg-blue-500':
                                        activeFeature.color === 'blue',
                                    'bg-teal-500':
                                        activeFeature.color === 'teal',
                                    'bg-violet-500':
                                        activeFeature.color === 'violet',
                                    'bg-cyan-500':
                                        activeFeature.color === 'cyan',
                                }"
                            ></div>

                            <!-- Console Header -->
                            <div
                                class="flex items-center justify-between border-b border-zinc-900 pb-4"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="flex gap-1.5">
                                        <span
                                            class="h-2.5 w-2.5 rounded-full bg-red-500/80"
                                        ></span>
                                        <span
                                            class="h-2.5 w-2.5 rounded-full bg-yellow-500/80"
                                        ></span>
                                        <span
                                            class="h-2.5 w-2.5 rounded-full bg-green-500/80"
                                        ></span>
                                    </div>
                                    <span
                                        class="ml-2 font-mono text-[9px] tracking-wider text-zinc-500"
                                        >DATAFLOW_MONITOR v1.0.0</span
                                    >
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        @click="toggleAutoPlay"
                                        class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-2 py-1 text-[9.5px] font-bold transition-all duration-300 active:scale-95"
                                        :class="
                                            isAutoPlaying
                                                ? 'animate-pulse border-primary bg-primary font-extrabold text-primary-foreground'
                                                : 'border-zinc-800 bg-zinc-900 text-zinc-300 hover:border-zinc-700 hover:bg-zinc-800/80 hover:text-white'
                                        "
                                    >
                                        <component
                                            :is="isAutoPlaying ? Pause : Play"
                                            class="size-3"
                                        />
                                        <span>{{
                                            isAutoPlaying
                                                ? 'Dừng phát'
                                                : 'Tự động chạy'
                                        }}</span>
                                    </button>
                                    <button
                                        @click="goToNextStep"
                                        class="flex cursor-pointer items-center gap-1 rounded-lg border border-zinc-800 bg-zinc-900 px-2 py-1 text-[9.5px] font-bold text-zinc-300 transition-all hover:border-zinc-700 hover:bg-zinc-800/80 hover:text-white active:scale-95"
                                    >
                                        <span>Bước tiếp theo</span>
                                        <ChevronRight
                                            class="size-3 animate-pulse text-primary"
                                        />
                                    </button>
                                    <Badge
                                        class="border px-2 py-0.5 font-mono text-[9px] tracking-wider uppercase"
                                        :class="{
                                            'border-indigo-500/30 bg-indigo-950/20 text-indigo-400':
                                                activeFeature.color ===
                                                'indigo',
                                            'border-emerald-500/30 bg-emerald-950/20 text-emerald-400':
                                                activeFeature.color ===
                                                'emerald',
                                            'border-cyan-500/30 bg-cyan-950/20 text-cyan-400':
                                                activeFeature.color === 'cyan',
                                            'border-amber-500/30 bg-amber-950/20 text-amber-400':
                                                activeFeature.color === 'amber',
                                            'border-sky-500/30 bg-sky-950/20 text-sky-400':
                                                activeFeature.color === 'sky',
                                            'border-teal-500/30 bg-teal-950/20 text-teal-400':
                                                activeFeature.color === 'teal',
                                            'border-rose-500/30 bg-rose-950/20 text-rose-400':
                                                activeFeature.color === 'rose',
                                            'border-blue-500/30 bg-blue-950/20 text-blue-400':
                                                activeFeature.color === 'blue',
                                            'border-violet-500/30 bg-violet-950/20 text-violet-400':
                                                activeFeature.color ===
                                                'violet',
                                        }"
                                    >
                                        {{ activeFeature.status }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Selected Feature Overview -->
                            <div class="mt-4 space-y-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="rounded-xl border p-2.5 transition-all duration-300"
                                        :class="{
                                            'border-indigo-500/30 bg-indigo-950/50 text-indigo-400':
                                                activeFeature.color ===
                                                'indigo',
                                            'border-emerald-500/30 bg-emerald-950/50 text-emerald-400':
                                                activeFeature.color ===
                                                'emerald',
                                            'border-amber-500/30 bg-amber-950/50 text-amber-400':
                                                activeFeature.color === 'amber',
                                            'border-sky-500/30 bg-sky-950/50 text-sky-400':
                                                activeFeature.color === 'sky',
                                            'border-rose-500/30 bg-rose-950/50 text-rose-400':
                                                activeFeature.color === 'rose',
                                            'border-blue-500/30 bg-blue-950/50 text-blue-400':
                                                activeFeature.color === 'blue',
                                            'border-teal-500/30 bg-teal-950/50 text-teal-400':
                                                activeFeature.color === 'teal',
                                            'border-violet-500/30 bg-violet-950/50 text-violet-400':
                                                activeFeature.color ===
                                                'violet',
                                            'border-cyan-500/30 bg-cyan-950/50 text-cyan-400':
                                                activeFeature.color === 'cyan',
                                        }"
                                    >
                                        <component
                                            :is="activeFeature.icon"
                                            class="size-6 animate-pulse"
                                        />
                                    </div>
                                    <div>
                                        <h3
                                            class="flex items-center gap-2 text-base font-bold tracking-tight text-white"
                                        >
                                            Bước {{ activeFeature.step }}:
                                            {{ activeFeature.title }}
                                        </h3>
                                        <p
                                            class="mt-0.5 text-xs leading-relaxed text-zinc-400"
                                        >
                                            {{ activeFeature.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Flow Visualization -->
                            <div
                                class="mt-5 flex-grow space-y-3.5 border-y border-zinc-900/60 py-4"
                            >
                                <p
                                    class="font-mono text-[9px] tracking-widest text-zinc-500 uppercase"
                                >
                                    LUỒNG HOẠT ĐỘNG & ĐỒNG BỘ
                                </p>

                                <!-- Step 1: TRIGGER -->
                                <div class="relative flex items-start gap-3">
                                    <div
                                        class="relative z-10 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                                        :class="{
                                            'bg-indigo-400 shadow-[0_0_8px_rgba(99,102,241,0.8)]':
                                                activeFeature.color ===
                                                'indigo',
                                            'bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)]':
                                                activeFeature.color ===
                                                'emerald',
                                            'bg-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.8)]':
                                                activeFeature.color === 'amber',
                                            'bg-sky-400 shadow-[0_0_8px_rgba(14,165,233,0.8)]':
                                                activeFeature.color === 'sky',
                                            'bg-rose-400 shadow-[0_0_8px_rgba(244,63,94,0.8)]':
                                                activeFeature.color === 'rose',
                                            'bg-blue-400 shadow-[0_0_8px_rgba(59,130,246,0.8)]':
                                                activeFeature.color === 'blue',
                                            'bg-teal-400 shadow-[0_0_8px_rgba(20,184,166,0.8)]':
                                                activeFeature.color === 'teal',
                                            'bg-violet-400 shadow-[0_0_8px_rgba(139,92,246,0.8)]':
                                                activeFeature.color ===
                                                'violet',
                                            'bg-cyan-400 shadow-[0_0_8px_rgba(6,182,212,0.8)]':
                                                activeFeature.color === 'cyan',
                                        }"
                                    ></div>
                                    <div
                                        class="absolute top-[14px] left-[3px] h-[28px] w-[1px] bg-gradient-to-b from-zinc-700 to-zinc-900"
                                    ></div>
                                    <div class="space-y-0.5">
                                        <span
                                            class="font-mono text-[9px] text-zinc-500 uppercase"
                                            >Tác nhân (Trigger)</span
                                        >
                                        <p
                                            class="text-xs font-semibold text-zinc-200"
                                        >
                                            {{ activeFeature.flow.trigger }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Step 2: PROCESS -->
                                <div class="relative flex items-start gap-3">
                                    <div
                                        class="relative z-10 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full"
                                        :class="{
                                            'bg-indigo-400 shadow-[0_0_8px_rgba(99,102,241,0.8)]':
                                                activeFeature.color ===
                                                'indigo',
                                            'bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)]':
                                                activeFeature.color ===
                                                'emerald',
                                            'bg-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.8)]':
                                                activeFeature.color === 'amber',
                                            'bg-sky-400 shadow-[0_0_8px_rgba(14,165,233,0.8)]':
                                                activeFeature.color === 'sky',
                                            'bg-rose-400 shadow-[0_0_8px_rgba(244,63,94,0.8)]':
                                                activeFeature.color === 'rose',
                                            'bg-blue-400 shadow-[0_0_8px_rgba(59,130,246,0.8)]':
                                                activeFeature.color === 'blue',
                                            'bg-teal-400 shadow-[0_0_8px_rgba(20,184,166,0.8)]':
                                                activeFeature.color === 'teal',
                                            'bg-violet-400 shadow-[0_0_8px_rgba(139,92,246,0.8)]':
                                                activeFeature.color ===
                                                'violet',
                                            'bg-cyan-400 shadow-[0_0_8px_rgba(6,182,212,0.8)]':
                                                activeFeature.color === 'cyan',
                                        }"
                                    ></div>
                                    <div
                                        class="absolute top-[14px] left-[3px] h-[28px] w-[1px] bg-gradient-to-b from-zinc-700 to-zinc-900"
                                    ></div>
                                    <div class="space-y-0.5">
                                        <span
                                            class="font-mono text-[9px] text-zinc-500 uppercase"
                                            >Xử lý (Processing)</span
                                        >
                                        <p
                                            class="text-xs font-semibold text-zinc-200"
                                        >
                                            {{ activeFeature.flow.process }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Step 3: STORAGE -->
                                <div class="relative flex items-start gap-3">
                                    <div
                                        class="relative z-10 mt-2 h-1.5 w-1.5 flex-shrink-0 animate-pulse rounded-full"
                                        :class="{
                                            'bg-indigo-400 shadow-[0_0_8px_rgba(99,102,241,0.8)]':
                                                activeFeature.color ===
                                                'indigo',
                                            'bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)]':
                                                activeFeature.color ===
                                                'emerald',
                                            'bg-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.8)]':
                                                activeFeature.color === 'amber',
                                            'bg-sky-400 shadow-[0_0_8px_rgba(14,165,233,0.8)]':
                                                activeFeature.color === 'sky',
                                            'bg-rose-400 shadow-[0_0_8px_rgba(244,63,94,0.8)]':
                                                activeFeature.color === 'rose',
                                            'bg-blue-400 shadow-[0_0_8px_rgba(59,130,246,0.8)]':
                                                activeFeature.color === 'blue',
                                            'bg-teal-400 shadow-[0_0_8px_rgba(20,184,166,0.8)]':
                                                activeFeature.color === 'teal',
                                            'bg-violet-400 shadow-[0_0_8px_rgba(139,92,246,0.8)]':
                                                activeFeature.color ===
                                                'violet',
                                            'bg-cyan-400 shadow-[0_0_8px_rgba(6,182,212,0.8)]':
                                                activeFeature.color === 'cyan',
                                        }"
                                    ></div>
                                    <div class="flex-grow space-y-0.5">
                                        <span
                                            class="font-mono text-[9px] text-zinc-500 uppercase"
                                            >Lưu trữ Database</span
                                        >
                                        <p
                                            class="text-xs font-semibold text-white"
                                        >
                                            {{ activeFeature.flow.storage }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Technology & DB details -->
                            <div class="mt-4 space-y-3 pt-3">
                                <div class="space-y-1">
                                    <span
                                        class="font-mono text-[9px] tracking-widest text-zinc-500 uppercase"
                                        >MAPPED SCHEMAS (DATABASE TABLES)</span
                                    >
                                    <div class="flex flex-wrap gap-1.5">
                                        <code
                                            v-for="tbl in activeFeature.dbTables"
                                            :key="tbl"
                                            class="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 font-mono text-[10px] font-bold text-zinc-300 transition-colors hover:border-zinc-700"
                                        >
                                            {{ tbl }}
                                        </code>
                                    </div>
                                </div>

                                <div
                                    class="rounded-xl border border-zinc-900/60 bg-zinc-900/30 p-3 text-[11px] leading-relaxed text-zinc-400"
                                >
                                    <span class="font-bold text-zinc-200"
                                        >Tech note: </span
                                    >{{ activeFeature.techNote }}
                                </div>

                                <div
                                    class="flex items-center justify-between border-t border-zinc-900/40 pt-3 font-mono text-[10px] text-zinc-500"
                                >
                                    <span
                                        >Mục tiêu SLA:
                                        <strong
                                            class="font-medium text-zinc-300"
                                            >{{ activeFeature.sla }}</strong
                                        ></span
                                    >
                                    <span
                                        >STATUS:
                                        <strong class="text-green-500"
                                            >ACTIVE</strong
                                        ></span
                                    >
                                </div>
                            </div>

                            <!-- Global SaaS Operations Step-by-Step Flow Map -->
                            <div
                                class="relative z-10 mt-4 space-y-2 border-t border-zinc-900/60 pt-3"
                            >
                                <span
                                    class="flex items-center gap-1.5 font-mono text-[9px] tracking-widest text-zinc-500 uppercase"
                                >
                                    <span
                                        class="h-1.5 w-1.5 animate-ping rounded-full bg-primary"
                                    ></span>
                                    TIẾN TRÌNH LUỒNG DỮ LIỆU SAAS (8 BƯỚC VẬN
                                    HÀNH CHÍNH)
                                </span>
                                <div
                                    class="flex scrollbar-none items-center justify-between gap-1 overflow-x-auto rounded-xl border border-zinc-900/80 bg-zinc-900/20 p-2"
                                >
                                    <div
                                        v-for="(item, idx) in featureMap"
                                        :key="item.title"
                                        class="flex flex-shrink-0 items-center"
                                    >
                                        <button
                                            @click="
                                                activeFeatureTitle = item.title;
                                                if (
                                                    activeCategory !== 'all' &&
                                                    activeCategory !==
                                                        item.category
                                                )
                                                    activeCategory = 'all';
                                            "
                                            class="group relative flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border font-mono text-xs transition-all duration-300"
                                            :class="
                                                activeFeatureTitle ===
                                                item.title
                                                    ? 'scale-110 border-white bg-white font-extrabold text-zinc-950 shadow-lg'
                                                    : 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                                            "
                                            :style="
                                                activeFeatureTitle ===
                                                item.title
                                                    ? `box-shadow: 0 0 10px ${colorGlowBorder}`
                                                    : ''
                                            "
                                        >
                                            <span>{{ item.step }}</span>
                                            <!-- Tooltip hint -->
                                            <span
                                                class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 rounded border border-zinc-900 bg-zinc-950 px-2 py-1 font-sans text-[9px] whitespace-nowrap text-zinc-200 opacity-0 transition-opacity group-hover:opacity-100"
                                            >
                                                Bước {{ item.step }}:
                                                {{ item.title }}
                                            </span>
                                        </button>
                                        <span
                                            v-if="idx < featureMap.length - 1"
                                            class="mx-0.5 font-mono text-[9px] text-zinc-800"
                                            >➔</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Interactive Grid (7/12 columns) -->
                    <div class="lg:col-span-7">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div
                                v-for="item in filteredFeatureMap"
                                :key="item.title"
                                @mouseenter="activeFeatureTitle = item.title"
                                @click="activeFeatureTitle = item.title"
                                class="group relative cursor-pointer overflow-hidden rounded-2xl border border-border bg-card p-5 transition-all duration-300 hover:shadow-lg"
                                :class="
                                    activeFeatureTitle === item.title
                                        ? 'ring-2 ring-offset-2 ring-offset-background'
                                        : 'hover:border-zinc-300 dark:hover:border-zinc-700'
                                "
                                :style="
                                    activeFeatureTitle === item.title
                                        ? `border-color: ${item.color === 'indigo' ? 'rgba(99, 102, 241, 0.5)' : item.color === 'emerald' ? 'rgba(16, 185, 129, 0.5)' : item.color === 'amber' ? 'rgba(245, 158, 11, 0.5)' : item.color === 'sky' ? 'rgba(14, 165, 233, 0.5)' : item.color === 'rose' ? 'rgba(244, 63, 94, 0.5)' : item.color === 'blue' ? 'rgba(59, 130, 246, 0.5)' : item.color === 'teal' ? 'rgba(20, 184, 166, 0.5)' : item.color === 'violet' ? 'rgba(139, 92, 246, 0.5)' : 'rgba(6, 182, 212, 0.5)'}; --tw-ring-color: ${item.color === 'indigo' ? '#6366f1' : item.color === 'emerald' ? '#10b981' : item.color === 'amber' ? '#f59e0b' : item.color === 'sky' ? '#0ea5e9' : item.color === 'rose' ? '#f43f5e' : item.color === 'blue' ? '#3b82f6' : item.color === 'teal' ? '#14b8a6' : item.color === 'violet' ? '#8b5cf6' : '#06b6d4'}`
                                        : ''
                                "
                            >
                                <!-- Interactive Hover Glow matching the icon color -->
                                <div
                                    class="absolute -right-10 -bottom-10 h-24 w-24 rounded-full opacity-0 blur-2xl transition-all duration-300 group-hover:opacity-10"
                                    :class="{
                                        'bg-indigo-500':
                                            item.color === 'indigo',
                                        'bg-emerald-500':
                                            item.color === 'emerald',
                                        'bg-amber-500': item.color === 'amber',
                                        'bg-sky-500': item.color === 'sky',
                                        'bg-rose-500': item.color === 'rose',
                                        'bg-blue-500': item.color === 'blue',
                                        'bg-teal-500': item.color === 'teal',
                                        'bg-violet-500':
                                            item.color === 'violet',
                                        'bg-cyan-500': item.color === 'cyan',
                                    }"
                                ></div>

                                <div
                                    class="relative z-10 mb-3 flex items-center justify-between"
                                >
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-xl border transition-all duration-300 group-hover:scale-110"
                                        :class="{
                                            'border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-900/40 dark:bg-indigo-950/20 dark:text-indigo-400':
                                                item.color === 'indigo',
                                            'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-400':
                                                item.color === 'emerald',
                                            'border-cyan-100 bg-cyan-50 text-cyan-600 dark:border-cyan-900/40 dark:bg-cyan-950/20 dark:text-cyan-400':
                                                item.color === 'cyan',
                                            'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400':
                                                item.color === 'amber',
                                            'border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/20 dark:text-sky-400':
                                                item.color === 'sky',
                                            'border-teal-100 bg-teal-50 text-teal-600 dark:border-teal-900/40 dark:bg-teal-950/20 dark:text-teal-400':
                                                item.color === 'teal',
                                            'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-400':
                                                item.color === 'rose',
                                            'border-blue-100 bg-blue-50 text-blue-600 dark:border-blue-900/40 dark:bg-blue-950/20 dark:text-blue-400':
                                                item.color === 'blue',
                                            'border-violet-100 bg-violet-50 text-violet-600 dark:border-violet-900/40 dark:bg-violet-950/20 dark:text-violet-400':
                                                item.color === 'violet',
                                        }"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="size-5"
                                        />
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        <!-- Step Number Indicator -->
                                        <span
                                            class="animate-pulse rounded-md px-1.5 py-0.5 font-mono text-[10px] font-extrabold"
                                            :class="{
                                                'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400':
                                                    item.color === 'indigo',
                                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400':
                                                    item.color === 'emerald',
                                                'bg-cyan-100 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-400':
                                                    item.color === 'cyan',
                                                'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400':
                                                    item.color === 'amber',
                                                'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400':
                                                    item.color === 'sky',
                                                'bg-teal-100 text-teal-700 dark:bg-teal-950/40 dark:text-teal-400':
                                                    item.color === 'teal',
                                                'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400':
                                                    item.color === 'rose',
                                                'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400':
                                                    item.color === 'blue',
                                                'bg-violet-100 text-violet-700 dark:bg-violet-950/40 dark:text-violet-400':
                                                    item.color === 'violet',
                                            }"
                                        >
                                            {{
                                                String(item.step).padStart(
                                                    2,
                                                    '0',
                                                )
                                            }}
                                        </span>
                                        <!-- Inline DB Table hint -->
                                        <span
                                            class="rounded border border-border bg-muted px-2 py-0.5 font-mono text-[9px] text-muted-foreground"
                                        >
                                            {{ item.dbTables[0] }}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            class="px-1.5 py-0 text-[9px] tracking-wider uppercase"
                                        >
                                            {{ item.status }}
                                        </Badge>
                                    </div>
                                </div>

                                <div class="relative z-10 space-y-1.5">
                                    <h3
                                        class="flex items-center gap-2 text-base font-bold tracking-tight text-card-foreground transition-colors group-hover:text-primary"
                                    >
                                        {{ item.title }}
                                        <ChevronRight
                                            class="size-3.5 -translate-x-1 text-primary opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100"
                                        />
                                    </h3>
                                    <p
                                        class="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Tin tức mới nhất ──────────────────────────────────────── -->
        <section v-if="latestNewsList.length > 0" id="news" class="px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="flex items-end justify-between">
                    <div class="max-w-2xl">
                        <Badge variant="outline" class="mb-3 border-primary/30 bg-primary/5 text-primary">Tin tức</Badge>
                        <h2 class="text-3xl font-semibold">Tin tức & Cập nhật</h2>
                        <p class="mt-3 text-muted-foreground">
                            Những bài viết mới nhất về Aventura, nhà hàng và ngành dịch vụ ăn uống.
                        </p>
                    </div>
                    <Link href="/tin-tuc" class="hidden shrink-0 items-center gap-1 text-sm font-medium text-primary hover:underline sm:flex">
                        Xem tất cả <ChevronRight class="size-4" />
                    </Link>
                </div>
                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <NewsCard
                        v-for="post in latestNewsList"
                        :key="post.id"
                        :title="post.title"
                        :slug="post.slug"
                        :excerpt="post.excerpt"
                        :category="post.category"
                        :featured_image_url="post.featured_image_url"
                        :is_featured="post.is_featured"
                        :published_at="post.published_at"
                    />
                </div>
                <div class="mt-6 flex justify-center sm:hidden">
                    <Button as-child variant="outline">
                        <Link href="/tin-tuc">Xem tất cả tin tức</Link>
                    </Button>
                </div>
            </div>
        </section>

        <section id="pricing" class="px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 max-w-full">
                    <div class="max-w-2xl">
                        <h2 class="text-3xl font-semibold">Gói dịch vụ linh hoạt</h2>
                        <p class="mt-3 text-muted-foreground">
                            Từ quán nhỏ đến chuỗi lớn — chọn gói phù hợp và nâng cấp bất kỳ lúc nào mà không mất dữ liệu.
                        </p>
                    </div>
                    <!-- Billing toggle -->
                    <div class="flex items-center gap-1 rounded-xl border border-border bg-muted p-1 text-sm shrink-0 self-start sm:self-auto">
                        <button
                            @click="billingCycle = 'monthly'"
                            class="rounded-lg px-4 py-1.5 font-medium transition-all"
                            :class="billingCycle === 'monthly' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                        >Tháng</button>
                        <button
                            @click="billingCycle = 'yearly'"
                            class="rounded-lg px-4 py-1.5 font-medium transition-all flex items-center gap-1.5"
                            :class="billingCycle === 'yearly' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Năm
                            <span class="text-[10px] font-bold bg-emerald-500 text-white px-1.5 py-0.5 rounded-full">-20%</span>
                        </button>
                    </div>
                </div>
                <div
                    class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
                >
                    <Card
                        v-for="plan in displayPlans"
                        :key="plan.code"
                        class="flex flex-col justify-between border-border transition-all duration-200 hover:shadow-md"
                        :class="{
                            'border-2 border-primary shadow-sm':
                                plan.isRecommended,
                            'border-2 border-violet-500/80 bg-gradient-to-b from-violet-500/5 to-transparent':
                                plan.code === 'ultra',
                        }"
                    >
                        <CardHeader>
                            <div class="mb-2 flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-1.5 text-2xl font-bold"
                                    :class="{
                                        'text-primary': plan.isRecommended,
                                        'text-violet-500':
                                            plan.code === 'ultra',
                                    }"
                                >
                                    {{ plan.name }}
                                </CardTitle>
                                <Badge
                                    v-if="plan.code === 'free'"
                                    variant="secondary"
                                    >Mặc định</Badge
                                >
                                <Badge v-else-if="plan.isRecommended"
                                    >Khuyến nghị</Badge
                                >
                                <Badge
                                    v-else-if="plan.code === 'ultra'"
                                    class="bg-violet-600 text-white hover:bg-violet-700"
                                    >VIP</Badge
                                >
                            </div>
                            <div class="mt-2 flex items-end gap-1">
                                <span
                                    class="text-3xl font-extrabold text-foreground"
                                    :class="{
                                        'text-primary': plan.isRecommended,
                                        'text-violet-500':
                                            plan.code === 'ultra',
                                    }"
                                >
                                    {{ plan.price }}
                                </span>
                                <span
                                    class="pb-1 text-xs text-muted-foreground"
                                    >{{ plan.cycle }}</span
                                >
                            </div>
                            <CardDescription class="mt-2 min-h-[40px] text-xs">
                                {{ plan.note }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex-grow space-y-2 text-xs">
                            <p
                                v-for="feat in plan.features"
                                :key="feat"
                                class="flex items-center gap-2"
                            >
                                <Check
                                    class="size-4 flex-shrink-0 text-emerald-500"
                                    :class="{
                                        'text-primary': plan.isRecommended,
                                        'text-violet-500':
                                            plan.code === 'ultra',
                                    }"
                                />
                                <span>{{ feat }}</span>
                            </p>
                            <p
                                v-for="unfeat in plan.unsupportedFeatures"
                                :key="unfeat"
                                class="flex items-center gap-2 text-muted-foreground opacity-60"
                            >
                                <X class="size-4 flex-shrink-0" />
                                <span>{{ unfeat }}</span>
                            </p>
                        </CardContent>
                        <div class="mt-4 px-6 pb-6">
                            <Button
                                v-if="canRegister"
                                as-child
                                :variant="
                                    plan.isRecommended
                                        ? 'default'
                                        : plan.code === 'ultra'
                                          ? 'default'
                                          : 'outline'
                                "
                                class="w-full text-xs font-semibold"
                                :class="{
                                    'border-0 bg-violet-600 text-white hover:bg-violet-700':
                                        plan.code === 'ultra',
                                }"
                            >
                                <Link :href="register() + '?plan=' + plan.code"
                                    >Chọn {{ plan.name }}</Link
                                >
                            </Button>
                        </div>
                    </Card>
                </div>
            </div>
        </section>

        <!-- ── Testimonials ──────────────────────────────────────── -->
        <section class="border-y border-border bg-muted/30 px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="mb-10 text-center">
                    <Badge variant="outline" class="mb-3">Khách hàng nói gì</Badge>
                    <h2 class="text-3xl font-semibold">Được tin dùng bởi chủ quán thực tế</h2>
                </div>
                <div class="grid gap-5 md:grid-cols-3">
                    <div
                        v-for="t in testimonials"
                        :key="t.name"
                        class="flex flex-col gap-4 rounded-2xl border border-border bg-card p-6 transition-shadow hover:shadow-md"
                    >
                        <div class="font-serif text-5xl leading-none text-primary/20">"</div>
                        <div class="flex gap-0.5">
                            <Star
                                v-for="s in t.stars"
                                :key="s"
                                class="size-4 fill-amber-400 text-amber-400"
                            />
                        </div>
                        <p class="flex-1 text-sm leading-relaxed text-muted-foreground">{{ t.text }}</p>
                        <div class="border-t border-border pt-3">
                            <p class="text-sm font-semibold">{{ t.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ t.restaurant }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── FAQ Accordion ─────────────────────────────────────── -->
        <section class="px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-end">
                    <div class="max-w-xl">
                        <Badge variant="outline" class="mb-3 border-primary/30 bg-primary/5 text-primary">FAQ</Badge>
                        <h2 class="text-3xl font-semibold">Câu hỏi thường gặp</h2>
                        <p class="mt-3 text-muted-foreground">
                            Những thắc mắc phổ biến nhất từ chủ nhà hàng trước khi bắt đầu dùng Aventura.
                        </p>
                    </div>
                    <Button v-if="canRegister" as-child variant="outline" size="sm" class="shrink-0">
                        <Link :href="register()">Bắt đầu miễn phí →</Link>
                    </Button>
                </div>
                <div class="mt-8 divide-y divide-border overflow-hidden rounded-xl border border-border">
                    <div v-for="(item, i) in faq" :key="item.q">
                        <button
                            @click="toggleFaq(i)"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors hover:bg-muted/50"
                        >
                            <span class="text-sm font-medium">{{ item.q }}</span>
                            <ChevronRight
                                class="size-4 flex-shrink-0 text-muted-foreground transition-transform duration-200"
                                :class="openFaqIndex === i ? 'rotate-90' : ''"
                            />
                        </button>
                        <div v-show="openFaqIndex === i" class="border-t border-border/50 bg-muted/20 px-5 py-4">
                            <p class="text-sm leading-relaxed text-muted-foreground">{{ item.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Stats + Value Props ───────────────────────────────── -->
        <section id="stats-section" class="border-y border-border bg-muted/30 px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <!-- Số liệu — animated count-up -->
                <div class="grid grid-cols-3 gap-6 text-center">
                    <div>
                        <p class="text-4xl font-extrabold tracking-tight tabular-nums">
                            {{ countRestaurants }}+
                        </p>
                        <p class="mt-1.5 text-sm text-muted-foreground">Nhà hàng đang vận hành</p>
                    </div>
                    <div>
                        <p class="text-4xl font-extrabold tracking-tight tabular-nums">
                            {{ (countOrders / 1000).toFixed(1) }}K+
                        </p>
                        <p class="mt-1.5 text-sm text-muted-foreground">Đơn hàng đã xử lý</p>
                    </div>
                    <div>
                        <p class="text-4xl font-extrabold tracking-tight tabular-nums">
                            {{ (countUptime / 10).toFixed(1) }}%
                        </p>
                        <p class="mt-1.5 text-sm text-muted-foreground">Uptime cam kết SLA</p>
                    </div>
                </div>

                <!-- 3 value prop cards -->
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <Card class="group border-border transition-shadow hover:shadow-md">
                        <CardHeader>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 text-primary transition-transform group-hover:scale-105">
                                <Building2 class="size-5" />
                            </div>
                            <CardTitle class="mt-3 text-base">Quản lý chuỗi quy mô lớn</CardTitle>
                            <CardDescription>
                                Multi-branch với dữ liệu tenant tách biệt. Một tài khoản kiểm soát toàn bộ hệ thống chi nhánh, báo cáo hợp nhất theo thời gian thực.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                    <Card class="group border-border transition-shadow hover:shadow-md">
                        <CardHeader>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 text-primary transition-transform group-hover:scale-105">
                                <LineChart class="size-5" />
                            </div>
                            <CardTitle class="mt-3 text-base">Minh bạch từng thao tác</CardTitle>
                            <CardDescription>
                                Audit log ghi vết mọi thay đổi nhạy cảm — thay giá, hủy đơn, xóa dữ liệu. Không thể tẩy xóa, tra soát bất kỳ lúc nào.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                    <Card class="group border-border transition-shadow hover:shadow-md">
                        <CardHeader>
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/5 text-primary transition-transform group-hover:scale-105">
                                <Rocket class="size-5" />
                            </div>
                            <CardTitle class="mt-3 text-base">Onboarding không cần giải thích</CardTitle>
                            <CardDescription>
                                Đăng ký xong, chatbot hướng dẫn từng bước từ menu → bàn → nhân viên → order. Vận hành thật ngay trong 30 phút đầu.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </div>
        </section>

        <section class="px-4 py-16 lg:px-8">
            <div
                class="mx-auto flex max-w-4xl flex-col items-center gap-5 text-center"
            >
                <h2 class="text-3xl font-semibold">Đăng ký và thử ngay</h2>
                <p class="max-w-2xl text-muted-foreground">
                    Aventura đủ gọn để thử, đủ sâu để chạy thật. Gói Free có hạn
                    mức rõ; gói Pro mở khóa phần cần kiểm soát.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button v-if="canRegister" as-child size="lg">
                        <Link :href="register()">Bắt đầu miễn phí</Link>
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <a href="#features">Xem lại tính năng</a>
                    </Button>
                </div>
            </div>
        </section>
    </AppTopbarLayout>

    <!-- ── Sticky bottom CTA bar ──────────────────────────────── -->
    <Teleport to="body">
        <Transition name="slide-up">
            <div
                v-if="canRegister && showStickyCta && !stickyCtaDismissed"
                class="fixed bottom-0 left-0 right-0 z-50 border-t border-border bg-background/95 px-4 py-3 shadow-lg backdrop-blur"
            >
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <p class="hidden text-sm font-medium sm:block">
                        Bắt đầu miễn phí — không cần thẻ tín dụng
                    </p>
                    <div class="flex flex-1 items-center justify-end gap-3">
                        <Button as-child size="sm">
                            <Link :href="register()" class="flex items-center gap-1.5">
                                <Zap class="size-3.5" />
                                Tạo tài khoản ngay
                            </Link>
                        </Button>
                        <button
                            @click="dismissStickyCta"
                            class="rounded p-1 text-muted-foreground hover:text-foreground"
                            aria-label="Đóng"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Hero slideshow — slide-next (left → right) */
.slide-next-enter-active,
.slide-next-leave-active,
.slide-prev-enter-active,
.slide-prev-leave-active {
    transition: transform 0.6s cubic-bezier(0.77, 0, 0.175, 1), opacity 0.6s ease;
    position: absolute;
    inset: 0;
}

.slide-next-enter-from  { transform: translateX(100%);  opacity: 0; }
.slide-next-enter-to    { transform: translateX(0);      opacity: 1; }
.slide-next-leave-from  { transform: translateX(0);      opacity: 1; }
.slide-next-leave-to    { transform: translateX(-100%);  opacity: 0; }

.slide-prev-enter-from  { transform: translateX(-100%); opacity: 0; }
.slide-prev-enter-to    { transform: translateX(0);      opacity: 1; }
.slide-prev-leave-from  { transform: translateX(0);      opacity: 1; }
.slide-prev-leave-to    { transform: translateX(100%);   opacity: 0; }

/* Promo strip dismiss */
.promo-strip-leave-active {
    transition: max-height 0.3s ease, opacity 0.3s ease, padding 0.3s ease;
    overflow: hidden;
}
.promo-strip-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}
.promo-strip-enter-active {
    transition: max-height 0.3s ease, opacity 0.3s ease;
    overflow: hidden;
}
.promo-strip-enter-from { max-height: 0; opacity: 0; }
.promo-strip-enter-to   { max-height: 80px; opacity: 1; }

/* Sticky CTA slide-up */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
</style>
