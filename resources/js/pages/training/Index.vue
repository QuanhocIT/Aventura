<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    Award,
    BookOpen,
    CalendarClock,
    Check,
    CheckCircle2,
    ChevronRight,
    ClipboardCheck,
    Clock3,
    GraduationCap,
    Link as LinkIcon,
    MapPin,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    courses: any[];
    enrollments: any[];
    employees: any[];
    branches: any[];
    canManage: boolean;
    currentEmployeeId: number | null;
    stats: {
        total_courses: number;
        total_enrollments: number;
        completed: number;
        in_progress: number;
        overdue: number;
        awaiting_approval: number;
        failed: number;
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

const activeTab = ref<'courses' | 'enrollments'>('courses');

// ─── GIAO ĐÀO TẠO ─────────────────────────────────────────────────────────────
const showAssignmentDialog = ref(false);
const assignmentCourseId = ref<number | null>(null);
const employeeSearchQuery = ref('');

const assignmentForm = useForm({
    employee_ids: [] as number[],
    due_at: '',
    mandatory: false,
    reason: 'Giao đào tạo theo kế hoạch vận hành',
});

function openAssignmentDialog(course: any) {
    assignmentCourseId.value = course.id;
    assignmentForm.reset();
    assignmentForm.mandatory = Boolean(
        course.is_required || course.required_for_new_hires,
    );
    assignmentForm.due_at = new Date(
        Date.now() + Number(course.due_days || 14) * 86400000,
    )
        .toISOString()
        .slice(0, 16);
    showAssignmentDialog.value = true;
}

const filteredEmployees = computed(() => {
    if (!employeeSearchQuery.value.trim()) {
        return props.employees;
    }

    const q = employeeSearchQuery.value.toLowerCase().trim();

    return props.employees.filter(
        (e) =>
            e.full_name?.toLowerCase().includes(q) ||
            e.branch_name?.toLowerCase().includes(q) ||
            e.role?.toLowerCase().includes(q),
    );
});

function toggleAllEmployees() {
    if (assignmentForm.employee_ids.length === filteredEmployees.value.length) {
        assignmentForm.employee_ids = [];
    } else {
        assignmentForm.employee_ids = filteredEmployees.value.map((e) => e.id);
    }
}

function submitAssignment() {
    if (!assignmentCourseId.value || assignmentForm.employee_ids.length === 0) {
        toast.error('Vui lòng chọn ít nhất một nhân viên.');

        return;
    }

    assignmentForm.post('/training/enroll', {
        onSuccess: () => {
            showAssignmentDialog.value = false;
        },
    });
}

// ─── CỔNG HỌC VIÊN ────────────────────────────────────────────────────────────
const showLearningDialog = ref(false);
const learning = ref<any>(null);
const learningLoading = ref(false);
const quizAnswers = ref<Record<number, number[]>>({});

async function openLearning(enrollment: any) {
    learningLoading.value = true;

    try {
        const response = await axios.get(
            `/training/courses/${enrollment.course_id || enrollment.course?.id}/content`,
        );
        learning.value = response.data;
        quizAnswers.value = {};
        showLearningDialog.value = true;
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể tải nội dung khóa học.');
    } finally {
        learningLoading.value = false;
    }
}

async function completeLearningLesson(lessonId: number) {
    if (!learning.value?.enrollment?.id) {
        return;
    }

    try {
        const response = await axios.post('/training/complete-lesson', {
            enrollment_id: learning.value.enrollment.id,
            lesson_id: lessonId,
        });
        const completed = new Set(
            learning.value.enrollment.completed_lessons || [],
        );
        completed.add(lessonId);
        learning.value.enrollment.completed_lessons = Array.from(completed);
        learning.value.enrollment.progress_percent =
            response.data.progress ??
            learning.value.enrollment.progress_percent;
        toast.success('Đã đánh dấu hoàn tất bài học!');
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Lỗi khi cập nhật bài học.');
    }
}

async function submitLearningQuiz(quiz: any) {
    if (!learning.value?.enrollment?.id) {
        return;
    }

    try {
        const response = await axios.post('/training/submit-quiz', {
            enrollment_id: learning.value.enrollment.id,
            quiz_id: quiz.id,
            answers: quizAnswers.value[quiz.id] || [],
        });
        toast.success(`Kết quả kiểm tra: ${response.data.score}%`);
        learning.value.enrollment.progress_percent = Math.max(
            learning.value.enrollment.progress_percent,
            response.data.progress || learning.value.enrollment.progress_percent,
        );

        if (response.data.certificate_code) {
            learning.value.enrollment.certificate_code =
                response.data.certificate_code;
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể nộp bài kiểm tra.');
    }
}

function quizAnswersFor(quiz: any): number[] {
    if (!quizAnswers.value[quiz.id]) {
        quizAnswers.value[quiz.id] = [];
    }

    return quizAnswers.value[quiz.id];
}

async function approveEnrollment(enrollment: any) {
    try {
        await axios.post(`/training/enrollments/${enrollment.id}/approve`);
        enrollment.awaiting_manager_approval = false;
        enrollment.status = 'completed';
        toast.success('Đã ký duyệt hoàn thành đào tạo cho nhân viên.');
    } catch (e: any) {
        toast.error('Lỗi khi ký duyệt hồ sơ.');
    }
}

// ─── PHÂN QUYỀN VAI TRÒ & CHI NHÁNH CHIP SELECT ───────────────────────────────
const availableRoles = [
    { key: 'manager', label: 'Quản lý' },
    { key: 'warehouse_manager', label: 'Trưởng kho' },
    { key: 'warehouse_staff', label: 'Nhân viên kho' },
    { key: 'kitchen', label: 'Bếp / Chế biến' },
    { key: 'cashier', label: 'Thu ngân' },
    { key: 'waiter', label: 'Phục vụ' },
    { key: 'operations_inspector', label: 'Thanh tra / Giám sát' },
];

const roleLabelsMap: Record<string, string> = {
    manager: 'Quản lý',
    warehouse_manager: 'Trưởng kho',
    warehouse_staff: 'Nhân viên kho',
    kitchen: 'Bếp',
    cashier: 'Thu ngân',
    waiter: 'Phục vụ',
    operations_inspector: 'Thanh tra',
};

// ─── TẠO KHÓA HỌC FORM ────────────────────────────────────────────────────────
const showCourseDialog = ref(false);
const courseForm = useForm({
    title: '',
    description: '',
    course_code: '',
    type: 'onboarding' as string,
    is_required: false,
    required_for_new_hires: false,
    due_days: 14,
    validity_days: null as number | null,
    requires_manager_signoff: false,
    target_roles: [] as string[],
    target_branch_ids: [] as number[],
});

function toggleTargetRole(roleKey: string) {
    const idx = courseForm.target_roles.indexOf(roleKey);

    if (idx >= 0) {
        courseForm.target_roles.splice(idx, 1);
    } else {
        courseForm.target_roles.push(roleKey);
    }
}

function toggleTargetBranch(branchId: number) {
    const idx = courseForm.target_branch_ids.indexOf(branchId);

    if (idx >= 0) {
        courseForm.target_branch_ids.splice(idx, 1);
    } else {
        courseForm.target_branch_ids.push(branchId);
    }
}

function submitCourse() {
    if (!courseForm.title.trim()) {
        toast.error('Vui lòng nhập tên khóa học.');

        return;
    }

    courseForm.post('/training/courses', {
        onSuccess: () => {
            showCourseDialog.value = false;
            courseForm.reset();
            toast.success('Đã khởi tạo khóa đào tạo mới!');
        },
    });
}

function destroyCourse(course: any) {
    if (
        confirm(
            `Bạn có chắc chắn muốn xóa khóa học "${course.title}" và toàn bộ tiến độ liên quan?`,
        )
    ) {
        router.delete(`/training/courses/${course.id}`, {
            onSuccess: () => toast.success('Đã xóa khóa học.'),
        });
    }
}

// ─── BÀI HỌC FORM ─────────────────────────────────────────────────────────────
const showLessonDialog = ref(false);
const lessonCourseId = ref<number | null>(null);
const lessonForm = useForm({
    title: '',
    content_type: 'text' as string,
    content: '',
    duration_minutes: 15 as number | null,
});

function openLessonDialog(courseId: number) {
    lessonCourseId.value = courseId;
    lessonForm.reset();
    showLessonDialog.value = true;
}

function submitLesson() {
    if (!lessonCourseId.value) {
        return;
    }

    lessonForm.post(`/training/courses/${lessonCourseId.value}/lessons`, {
        onSuccess: () => {
            showLessonDialog.value = false;
            toast.success('Đã thêm bài học mới!');
        },
    });
}

// ─── QUIZ FORM ────────────────────────────────────────────────────────────────
const showQuizDialog = ref(false);
const quizCourseId = ref<number | null>(null);
const quizForm = useForm({
    title: '',
    pass_score: 70,
    max_attempts: 3,
    questions: [{ question: '', options: ['', ''], correct: 0 }] as {
        question: string;
        options: string[];
        correct: number;
    }[],
});

function openQuizDialog(courseId: number) {
    quizCourseId.value = courseId;
    quizForm.reset();
    quizForm.questions = [{ question: '', options: ['', ''], correct: 0 }];
    showQuizDialog.value = true;
}

function addQuestion() {
    quizForm.questions.push({ question: '', options: ['', ''], correct: 0 });
}

function removeQuestion(idx: number) {
    if (quizForm.questions.length > 1) {
        quizForm.questions.splice(idx, 1);
    }
}

function addOption(qIdx: number) {
    quizForm.questions[qIdx].options.push('');
}

function removeOption(qIdx: number, oIdx: number) {
    if (quizForm.questions[qIdx].options.length > 2) {
        quizForm.questions[qIdx].options.splice(oIdx, 1);
    }
}

function submitQuiz() {
    if (!quizCourseId.value) {
        return;
    }

    quizForm.post(`/training/courses/${quizCourseId.value}/quizzes`, {
        onSuccess: () => {
            showQuizDialog.value = false;
            toast.success('Đã tạo bài kiểm tra!');
        },
    });
}

// ─── DICTIONARY MAPPING ───────────────────────────────────────────────────────
const typeLabel: Record<string, string> = {
    onboarding: 'Onboarding',
    menu: 'Thực đơn & Công thức',
    attp: 'An toàn thực phẩm',
    operations: 'Vận hành & Quy chuẩn',
    custom: 'Chuyên đề nâng cao',
};

const typeColor: Record<string, string> = {
    onboarding:
        'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-200/50 dark:border-blue-500/30',
    menu: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200/50 dark:border-emerald-500/30',
    attp: 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-200/50 dark:border-rose-500/30',
    operations:
        'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-200/50 dark:border-amber-500/30',
    custom: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-200/50 dark:border-indigo-500/30',
};

const statusLabel: Record<string, string> = {
    enrolled: 'Đã ghi danh',
    in_progress: 'Đang học',
    completed: 'Hoàn thành',
    failed: 'Không đạt',
};

const statusColor: Record<string, string> = {
    enrolled:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
    in_progress:
        'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200/60 dark:border-blue-500/30',
    completed:
        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-500/30',
    failed: 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-200/60 dark:border-rose-500/30',
};
</script>

<template>
    <Head title="Đào tạo & Onboarding" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Hero Header -->
        <div
            class="relative overflow-hidden rounded-2xl border border-indigo-200/80 bg-gradient-to-r from-indigo-500/10 via-blue-500/5 to-slate-100/50 p-5 shadow-xs lg:p-6 dark:border-indigo-500/20 dark:from-indigo-950/40 dark:via-blue-950/20 dark:to-slate-900/40"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20 dark:bg-indigo-500"
                    >
                        <GraduationCap class="size-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1
                                class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl"
                            >
                                Đào Tạo & Onboarding
                            </h1>
                            <Badge
                                variant="outline"
                                class="border-indigo-300/80 bg-indigo-50 text-[10px] font-bold text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300"
                            >
                                Tiêu chuẩn vận hành
                            </Badge>
                        </div>
                        <p
                            class="mt-1 text-xs text-slate-600 dark:text-slate-300"
                        >
                            Quản lý quy trình đào tạo hội nhập, chuẩn hóa bài kiểm
                            tra ATTP/Thực đơn và chứng chỉ nhân sự.
                        </p>
                    </div>
                </div>

                <div
                    v-if="canManage"
                    class="flex items-center gap-2.5 self-start sm:self-center"
                >
                    <Button
                        @click="showCourseDialog = true"
                        class="gap-2 bg-indigo-600 font-semibold text-white shadow-md transition hover:bg-indigo-700 active:scale-95 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                    >
                        <Plus class="size-4" /> Tạo khóa học mới
                    </Button>
                </div>
            </div>
        </div>

        <!-- Metric KPI Overview -->
        <div class="grid grid-cols-2 gap-3.5 lg:grid-cols-4">
            <Card
                class="border-slate-200/80 bg-white shadow-xs transition-all hover:border-indigo-200 dark:border-slate-800 dark:bg-slate-900"
            >
                <CardContent class="p-4">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >Tổng khóa học</span
                        >
                        <div
                            class="rounded-lg bg-indigo-50 p-2 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"
                        >
                            <BookOpen class="size-4" />
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span
                            class="text-2xl font-extrabold text-slate-900 dark:text-white"
                            >{{ stats.total_courses }}</span
                        >
                        <span class="text-[11px] text-slate-500">chương trình</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        Đang lưu hành trong hệ thống
                    </p>
                </CardContent>
            </Card>

            <Card
                class="border-slate-200/80 bg-white shadow-xs transition-all hover:border-blue-200 dark:border-slate-800 dark:bg-slate-900"
            >
                <CardContent class="p-4">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >Lượt ghi danh</span
                        >
                        <div
                            class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400"
                        >
                            <Users class="size-4" />
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span
                            class="text-2xl font-extrabold text-slate-900 dark:text-white"
                            >{{ stats.total_enrollments }}</span
                        >
                        <span class="text-[11px] text-slate-500">hồ sơ</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        Hồ sơ nhân viên học tập
                    </p>
                </CardContent>
            </Card>

            <Card
                class="border-slate-200/80 bg-white shadow-xs transition-all hover:border-emerald-200 dark:border-slate-800 dark:bg-slate-900"
            >
                <CardContent class="p-4">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >Đã hoàn thành</span
                        >
                        <div
                            class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                        >
                            <CheckCircle2 class="size-4" />
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span
                            class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400"
                            >{{ stats.completed }}</span
                        >
                        <span class="text-[11px] text-slate-500">đã đạt</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        Được cấp chứng chỉ / Xác nhận
                    </p>
                </CardContent>
            </Card>

            <Card
                class="border-slate-200/80 bg-white shadow-xs transition-all hover:border-amber-200 dark:border-slate-800 dark:bg-slate-900"
            >
                <CardContent class="p-4">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >Đang học tập</span
                        >
                        <div
                            class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"
                        >
                            <GraduationCap class="size-4" />
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span
                            class="text-2xl font-extrabold text-amber-600 dark:text-amber-400"
                            >{{ stats.in_progress }}</span
                        >
                        <span class="text-[11px] text-slate-500">nhân viên</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        Đang làm bài học / quiz
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Compliance & Sign-off Warning Bar -->
        <div
            v-if="
                canManage &&
                (stats.overdue || stats.awaiting_approval || stats.failed)
            "
            class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-xs dark:border-amber-500/30 dark:bg-amber-950/20 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-wrap items-center gap-4">
                <div
                    v-if="stats.overdue"
                    class="flex items-center gap-2 text-amber-800 font-semibold dark:text-amber-300"
                >
                    <AlertTriangle class="size-4 shrink-0 text-amber-600" />
                    <span>Có <strong>{{ stats.overdue }}</strong> hồ sơ trễ hạn</span>
                </div>
                <div
                    v-if="stats.awaiting_approval"
                    class="flex items-center gap-2 text-blue-800 font-semibold dark:text-blue-300"
                >
                    <ClipboardCheck class="size-4 shrink-0 text-blue-600" />
                    <span>Có <strong>{{ stats.awaiting_approval }}</strong> hồ sơ thực hành chờ Quản lý ký duyệt</span>
                </div>
                <div
                    v-if="stats.failed"
                    class="flex items-center gap-2 text-rose-800 font-semibold dark:text-rose-300"
                >
                    <Clock3 class="size-4 shrink-0 text-rose-600" />
                    <span>Có <strong>{{ stats.failed }}</strong> lượt kiểm tra chưa đạt</span>
                </div>
            </div>
            <button
                @click="activeTab = 'enrollments'"
                class="inline-flex items-center gap-1 font-bold text-amber-900 underline hover:text-amber-950 dark:text-amber-200"
            >
                Xem tiến độ nhân viên <ChevronRight class="size-3.5" />
            </button>
        </div>

        <!-- Tabs Switcher -->
        <div class="flex border-b border-slate-200/80 pb-0 dark:border-slate-800">
            <div class="flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800/80">
                <button
                    v-for="tab in [
                        { key: 'courses', label: 'Danh mục khóa học', icon: BookOpen },
                        { key: 'enrollments', label: 'Tiến độ & Hồ sơ nhân viên', icon: Users },
                    ]"
                    :key="tab.key"
                    @click="activeTab = tab.key as any"
                    :class="[
                        'flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition-all duration-200',
                        activeTab === tab.key
                            ? 'bg-white text-indigo-700 shadow-sm dark:bg-slate-900 dark:text-indigo-400'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                    ]"
                >
                    <component :is="tab.icon" class="size-3.5" />
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Courses Tab -->
        <div v-if="activeTab === 'courses'" class="space-y-4">
            <div
                v-if="courses.length"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="course in courses"
                    :key="course.id"
                    class="group flex flex-col overflow-hidden border-slate-200/80 bg-white shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500/40"
                >
                    <CardContent class="flex flex-1 flex-col justify-between p-5 space-y-4">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <Badge
                                    :class="typeColor[course.type]"
                                    class="border px-2.5 py-1 text-[11px] font-semibold"
                                >
                                    {{ typeLabel[course.type] || 'Khóa học' }}
                                </Badge>
                                <div class="flex items-center gap-1">
                                    <Badge
                                        v-if="course.is_required"
                                        variant="destructive"
                                        class="px-2 py-0.5 text-[10px] font-bold"
                                    >
                                        Bắt buộc
                                    </Badge>
                                    <Badge
                                        v-if="course.required_for_new_hires"
                                        class="bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-200 px-2 py-0.5 text-[10px]"
                                    >
                                        Auto New-hire
                                    </Badge>
                                </div>
                            </div>

                            <div>
                                <h3
                                    class="text-base font-bold text-slate-900 dark:text-white"
                                >
                                    {{ course.title }}
                                </h3>
                                <p
                                    v-if="course.course_code"
                                    class="mt-0.5 font-mono text-[11px] font-semibold text-slate-500"
                                >
                                    Mã: {{ course.course_code }}
                                </p>
                                <p
                                    v-if="course.description"
                                    class="mt-2 line-clamp-2 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    {{ course.description }}
                                </p>
                            </div>

                            <!-- Target roles & Branches -->
                            <div class="space-y-1.5 pt-1">
                                <div
                                    v-if="course.target_roles?.length"
                                    class="flex flex-wrap items-center gap-1 text-[11px]"
                                >
                                    <span class="font-semibold text-slate-500">Vai trò:</span>
                                    <span
                                        v-for="rKey in course.target_roles"
                                        :key="rKey"
                                        class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {{ roleLabelsMap[rKey] || rKey }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-[11px] text-slate-500">
                                    <span>📚 {{ course.lessons_count || 0 }} bài học</span>
                                    <span>👥 {{ course.enrollments_count || 0 }} học viên</span>
                                </div>
                            </div>

                            <!-- Quizzes preview -->
                            <div
                                v-if="course.quizzes?.length"
                                class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-800"
                            >
                                <span
                                    v-for="q in course.quizzes"
                                    :key="q.id"
                                    class="inline-flex items-center gap-1 rounded-md bg-indigo-50/80 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300"
                                >
                                    <Award class="size-3" /> {{ q.title }}
                                </span>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div
                            v-if="canManage"
                            class="flex flex-wrap items-center justify-between gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-800"
                        >
                            <div class="flex items-center gap-1">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 text-xs font-medium"
                                    @click="openLessonDialog(course.id)"
                                >
                                    <Plus class="mr-1 size-3" /> Bài học
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 text-xs font-medium"
                                    @click="openQuizDialog(course.id)"
                                >
                                    <Award class="mr-1 size-3" /> Quiz
                                </Button>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    size="sm"
                                    class="h-8 bg-blue-600 text-xs font-semibold text-white hover:bg-blue-700"
                                    @click="openAssignmentDialog(course)"
                                >
                                    <Users class="mr-1 size-3" /> Giao học
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="h-8 size-8 p-0 text-slate-400 hover:text-rose-600"
                                    title="Xóa khóa học"
                                    @click="destroyCourse(course)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty Courses State -->
            <Card
                v-else
                class="border-dashed border-slate-300 bg-slate-50/50 p-12 text-center dark:border-slate-800 dark:bg-slate-900/30"
            >
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400"
                >
                    <BookOpen class="size-7" />
                </div>
                <h3 class="mt-4 text-base font-bold text-slate-900 dark:text-white">
                    Chưa có khóa đào tạo nào
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Bắt đầu bằng cách tạo khóa học Onboarding hoặc Quy chuẩn vận hành mới.
                </p>

                <Button
                    v-if="canManage"
                    @click="showCourseDialog = true"
                    class="mt-4 gap-2 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                >
                    <Plus class="size-4" /> Tạo khóa học đầu tiên
                </Button>
            </Card>
        </div>

        <!-- Enrollments Tab -->
        <div v-if="activeTab === 'enrollments'" class="space-y-4">
            <Card
                class="overflow-hidden border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full min-w-[700px] border-collapse text-left">
                        <thead>
                            <tr
                                class="border-b border-slate-200 bg-slate-50 text-[11px] font-extrabold tracking-wider text-slate-600 uppercase dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300"
                            >
                                <th class="px-5 py-3.5">Nhân viên</th>
                                <th class="px-5 py-3.5">Khóa đào tạo</th>
                                <th class="px-5 py-3.5 text-center">Tiến độ học tập</th>
                                <th class="px-5 py-3.5">Trạng thái</th>
                                <th class="px-5 py-3.5">Mã chứng chỉ</th>
                                <th class="px-5 py-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr
                                v-for="e in enrollments"
                                :key="e.id"
                                class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                            >
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-9 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                        >
                                            {{ (e.employee?.full_name || 'NV').slice(0, 2).toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900 dark:text-white">
                                                {{ e.employee?.full_name ?? 'Chưa xác định' }}
                                            </p>
                                            <p class="text-[11px] text-slate-500">
                                                {{ e.employee?.branch_name || 'Chi nhánh' }} · {{ e.employee?.role || 'Nhân viên' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                        {{ e.course?.title }}
                                    </p>
                                    <p v-if="e.due_at" class="text-[10px] text-slate-500">
                                        Hạn: {{ new Date(e.due_at).toLocaleDateString('vi-VN') }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <div class="h-2 w-28 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                class="h-full rounded-full bg-indigo-600 transition-all duration-500"
                                                :style="{ width: `${e.progress_percent}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                                            {{ e.progress_percent }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge
                                        :class="statusColor[e.status]"
                                        class="border px-2.5 py-1 text-[10px] font-semibold"
                                    >
                                        {{ statusLabel[e.status] }}
                                    </Badge>
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        v-if="e.certificate_code"
                                        class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-1 font-mono text-xs font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-500/30 dark:text-emerald-300"
                                    >
                                        <Award class="size-3.5" /> {{ e.certificate_code }}
                                    </span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Button
                                        v-if="!canManage || e.employee_id === currentEmployeeId"
                                        size="sm"
                                        class="bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                                        :disabled="learningLoading"
                                        @click="openLearning(e)"
                                    >
                                        <BookOpen class="mr-1 size-3.5" /> Vào học ngay
                                    </Button>
                                    <Button
                                        v-else-if="e.awaiting_manager_approval"
                                        size="sm"
                                        class="bg-emerald-600 font-semibold text-white hover:bg-emerald-700"
                                        @click="approveEnrollment(e)"
                                    >
                                        <ShieldCheck class="mr-1 size-3.5" /> Duyệt thực hành
                                    </Button>
                                    <Button
                                        v-else
                                        size="sm"
                                        variant="outline"
                                        class="text-xs"
                                        @click="openLearning(e)"
                                    >
                                        <BookOpen class="mr-1 size-3.5" /> Xem tiến độ
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="!enrollments.length"
                        class="p-12 text-center text-xs text-slate-500"
                    >
                        Chưa có lịch sử ghi danh học tập nào.
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- MODAL 1: TẠO KHÓA ĐÀO TẠO MỚI (FIXED THEME & CHIP SELECTORS) -->
    <Dialog v-model:open="showCourseDialog">
        <DialogContent
            class="max-h-[90vh] max-w-xl overflow-y-auto rounded-2xl border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-950"
        >
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400"
                    >
                        <GraduationCap class="size-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold text-slate-900 dark:text-white"
                            >Tạo Khóa Đào Tạo Mới</DialogTitle
                        >
                        <DialogDescription class="text-xs text-slate-500"
                            >Khởi tạo khóa đào tạo chuẩn hóa cho nhân sự chi nhánh.</DialogDescription
                        >
                    </div>
                </div>
            </DialogHeader>

            <form @submit.prevent="submitCourse" class="mt-4 space-y-4">
                <div class="space-y-1.5">
                    <Label class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Tên khóa học <span class="text-rose-500">*</span></Label
                    >
                    <Input
                        v-model="courseForm.title"
                        placeholder="Ví dụ: Đào tạo ATTP & Quy trình Vận hành Bếp 2026"
                        class="border-slate-300 bg-slate-50 text-xs font-medium text-slate-900 focus:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        required
                    />
                </div>

                <div class="space-y-1.5">
                    <Label class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Mô tả khóa học</Label
                    >
                    <Input
                        v-model="courseForm.description"
                        placeholder="Mô tả mục tiêu, đối tượng áp dụng..."
                        class="border-slate-300 bg-slate-50 text-xs text-slate-900 focus:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300"
                            >Mã khóa học</Label
                        >
                        <Input
                            v-model="courseForm.course_code"
                            placeholder="ATTP-2026"
                            class="border-slate-300 bg-slate-50 font-mono text-xs text-slate-900 focus:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300"
                            >Hạn hoàn thành (ngày)</Label
                        >
                        <Input
                            v-model.number="courseForm.due_days"
                            type="number"
                            min="1"
                            class="border-slate-300 bg-slate-50 text-xs text-slate-900 focus:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label class="text-xs font-bold text-slate-700 dark:text-slate-300"
                        >Phân loại bài học</Label
                    >
                    <select
                        v-model="courseForm.type"
                        class="h-9 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    >
                        <option value="onboarding">Onboarding - Nhân viên mới</option>
                        <option value="menu">Thực đơn & Công thức món</option>
                        <option value="attp">ATTP - An toàn thực phẩm</option>
                        <option value="operations">Vận hành & Quy chuẩn phục vụ</option>
                        <option value="custom">Chuyên đề nâng cao</option>
                    </select>
                </div>

                <!-- Interactive Target Roles Tag Chips -->
                <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-900/50">
                    <Label class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Áp dụng cho vai trò (Nhấp chọn)
                    </Label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="role in availableRoles"
                            :key="role.key"
                            type="button"
                            @click="toggleTargetRole(role.key)"
                            :class="[
                                'flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-all duration-200 active:scale-95',
                                courseForm.target_roles.includes(role.key)
                                    ? 'border-indigo-500 bg-indigo-600 text-white shadow-xs dark:bg-indigo-500'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
                            ]"
                        >
                            <Check v-if="courseForm.target_roles.includes(role.key)" class="size-3.5 shrink-0" />
                            {{ role.label }}
                        </button>
                    </div>
                </div>

                <!-- Interactive Target Branches Tag Chips -->
                <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-900/50">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-bold text-slate-800 dark:text-slate-200">
                            Giới hạn chi nhánh áp dụng
                        </Label>
                        <span class="text-[10px] text-slate-500">
                            {{ courseForm.target_branch_ids.length === 0 ? 'Áp dụng toàn chuỗi' : `Đã chọn ${courseForm.target_branch_ids.length} chi nhánh` }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            @click="courseForm.target_branch_ids = []"
                            :class="[
                                'flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-all duration-200',
                                courseForm.target_branch_ids.length === 0
                                    ? 'border-emerald-500 bg-emerald-600 text-white shadow-xs'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
                            ]"
                        >
                            <Check v-if="courseForm.target_branch_ids.length === 0" class="size-3.5" />
                            🌐 Toàn chuỗi
                        </button>

                        <button
                            v-for="branch in branches"
                            :key="branch.id"
                            type="button"
                            @click="toggleTargetBranch(branch.id)"
                            :class="[
                                'flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition-all duration-200',
                                courseForm.target_branch_ids.includes(branch.id)
                                    ? 'border-indigo-500 bg-indigo-600 text-white shadow-xs dark:bg-indigo-500'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
                            ]"
                        >
                            <MapPin class="size-3 shrink-0" />
                            {{ branch.name }}
                        </button>
                    </div>
                </div>

                <!-- Switches & Checks -->
                <div class="space-y-2 pt-1 text-xs">
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 p-2.5 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900">
                        <input v-model="courseForm.required_for_new_hires" type="checkbox" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        <div>
                            <span class="font-bold text-slate-800 dark:text-slate-200">Tự động giao cho nhân viên mới</span>
                            <p class="text-[10px] text-slate-500">Tự động khởi tạo ghi danh khi có nhân sự mới nhận việc.</p>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 p-2.5 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900">
                        <input v-model="courseForm.requires_manager_signoff" type="checkbox" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        <div>
                            <span class="font-bold text-slate-800 dark:text-slate-200">Cần Quản lý ký duyệt thực hành</span>
                            <p class="text-[10px] text-slate-500">Sau khi học xong lý thuyết, Quản lý phải ký xác nhận tại cửa hàng.</p>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 p-2.5 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900">
                        <input v-model="courseForm.is_required" type="checkbox" class="size-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                        <div>
                            <span class="font-bold text-rose-700 dark:text-rose-400">Đánh dấu khóa học bắt buộc tuân thủ</span>
                            <p class="text-[10px] text-slate-500">Cảnh báo nếu nhân sự quá hạn đào tạo quy chuẩn.</p>
                        </div>
                    </label>
                </div>

                <DialogFooter class="pt-2">
                    <Button variant="outline" type="button" @click="showCourseDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="courseForm.processing" class="bg-indigo-600 font-bold text-white hover:bg-indigo-700">
                        {{ courseForm.processing ? 'Đang khởi tạo...' : 'Tạo khóa học' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- MODAL 2: GIAO ĐÀO TẠO CHO NHÂN VIÊN -->
    <Dialog v-model:open="showAssignmentDialog">
        <DialogContent class="max-h-[85vh] max-w-lg overflow-y-auto rounded-2xl border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-950">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                    <Users class="size-5 text-blue-600" /> Giao đào tạo cho Nhân viên
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submitAssignment" class="mt-3 space-y-4">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Danh sách nhân sự</Label>
                        <button type="button" @click="toggleAllEmployees" class="text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                            {{ assignmentForm.employee_ids.length === filteredEmployees.length ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}
                        </button>
                    </div>

                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 size-4 text-slate-400" />
                        <Input v-model="employeeSearchQuery" placeholder="Tìm tên nhân viên, vai trò, chi nhánh..." class="h-9 pl-9 text-xs" />
                    </div>

                    <div class="max-h-56 space-y-1.5 overflow-y-auto rounded-xl border border-slate-200 p-2 dark:border-slate-800">
                        <label
                            v-for="employee in filteredEmployees"
                            :key="employee.id"
                            class="flex cursor-pointer items-center justify-between rounded-lg p-2 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            <div class="flex items-center gap-2.5">
                                <input v-model="assignmentForm.employee_ids" type="checkbox" :value="employee.id" class="size-4 rounded border-slate-300 text-indigo-600" />
                                <div>
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">{{ employee.full_name }}</p>
                                    <p class="text-[10px] text-slate-500">{{ employee.branch_name }} · {{ employee.role || 'Nhân viên' }}</p>
                                </div>
                            </div>
                        </label>
                        <p v-if="!filteredEmployees.length" class="p-4 text-center text-xs text-slate-400">Không tìm thấy nhân viên phù hợp.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Hạn hoàn thành</Label>
                        <Input v-model="assignmentForm.due_at" type="datetime-local" class="h-9 text-xs" required />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                            <input v-model="assignmentForm.mandatory" type="checkbox" class="size-4 rounded text-rose-600" /> Bắt buộc phải đạt
                        </label>
                    </div>
                </div>

                <div class="space-y-1">
                    <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Lý do giao</Label>
                    <Input v-model="assignmentForm.reason" placeholder="Kế hoạch đào tạo quý..." class="h-9 text-xs" maxlength="120" />
                </div>

                <DialogFooter class="pt-2">
                    <Button variant="outline" type="button" @click="showAssignmentDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="assignmentForm.processing || !assignmentForm.employee_ids.length" class="bg-blue-600 font-bold text-white hover:bg-blue-700">
                        Giao khóa học ({{ assignmentForm.employee_ids.length }})
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- MODAL 3: THÊM BÀI HỌC MỚI -->
    <Dialog v-model:open="showLessonDialog">
        <DialogContent class="max-w-md rounded-2xl border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-950">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                    <Plus class="size-5 text-indigo-600" /> Thêm Bài Học Mới
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submitLesson" class="mt-3 space-y-4">
                <div class="space-y-1.5">
                    <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Tiêu đề bài học</Label>
                    <Input v-model="lessonForm.title" placeholder="Ví dụ: Quy định rửa tay & Khử khuẩn dụng cụ bếp" class="text-xs" required />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Loại nội dung</Label>
                        <select v-model="lessonForm.content_type" class="h-9 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <option value="text">Văn bản hướng dẫn</option>
                            <option value="video">Video đào tạo</option>
                            <option value="pdf">Tài liệu PDF</option>
                            <option value="link">Đường dẫn đính kèm</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Thời lượng (phút)</Label>
                        <Input type="number" v-model="lessonForm.duration_minutes" class="text-xs" />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Nội dung chi tiết / Đường dẫn URL</Label>
                    <textarea v-model="lessonForm.content" rows="4" placeholder="Nhập nội dung quy trình hoặc dán link video Youtube/Drive..." class="w-full rounded-lg border border-slate-300 bg-slate-50 p-2.5 text-xs text-slate-900 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea>
                </div>

                <DialogFooter class="pt-2">
                    <Button variant="outline" type="button" @click="showLessonDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="lessonForm.processing" class="bg-indigo-600 font-bold text-white hover:bg-indigo-700">
                        Thêm bài học
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- MODAL 4: TẠO BÀI KIỂM TRA (QUIZ BUILDER) -->
    <Dialog v-model:open="showQuizDialog">
        <DialogContent class="max-h-[85vh] max-w-xl overflow-y-auto rounded-2xl border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-950">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                    <Award class="size-5 text-indigo-600" /> Tạo Bài Kiểm Tra (Quiz)
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submitQuiz" class="mt-3 space-y-4">
                <div class="space-y-1.5">
                    <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Tiêu đề bài kiểm tra</Label>
                    <Input v-model="quizForm.title" placeholder="Kiểm tra kiến thức Quy trình Bếp..." class="text-xs" required />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Điểm đạt tối thiểu (%)</Label>
                        <Input type="number" v-model="quizForm.pass_score" min="1" max="100" class="text-xs" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-bold text-slate-700 dark:text-slate-300">Số lần làm tối đa</Label>
                        <Input type="number" v-model="quizForm.max_attempts" min="1" class="text-xs" />
                    </div>
                </div>

                <div class="space-y-3 pt-2">
                    <Label class="text-xs font-bold text-slate-800 dark:text-slate-200">Danh sách câu hỏi trắc nghiệm</Label>
                    <div
                        v-for="(q, qIdx) in quizForm.questions"
                        :key="qIdx"
                        class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-slate-800 dark:bg-slate-900/50"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">Câu {{ Number(qIdx) + 1 }}</span>
                            <button v-if="quizForm.questions.length > 1" type="button" @click="removeQuestion(Number(qIdx))" class="text-[11px] font-semibold text-rose-500 hover:underline">
                                Xóa câu hỏi
                            </button>
                        </div>

                        <Input v-model="q.question" :placeholder="`Nội dung câu hỏi ${Number(qIdx) + 1}...`" class="text-xs font-semibold" required />

                        <div class="space-y-1.5 pt-1">
                            <p class="text-[10px] font-bold text-slate-500 uppercase">Các đáp án (Tích chọn đáp án đúng):</p>
                            <div v-for="(opt, oIdx) in q.options" :key="oIdx" class="flex items-center gap-2">
                                <input type="radio" :name="`q_${qIdx}`" :value="oIdx" v-model="q.correct" class="size-4 text-indigo-600" />
                                <Input v-model="q.options[Number(oIdx)]" :placeholder="`Đáp án ${Number(oIdx) + 1}`" class="h-8 flex-1 text-xs" required />
                                <button v-if="q.options.length > 2" type="button" @click="removeOption(Number(qIdx), Number(oIdx))" class="text-slate-400 hover:text-rose-500">
                                    <X class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <button type="button" @click="addOption(Number(qIdx))" class="text-[11px] font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                            + Thêm đáp án lựa chọn
                        </button>
                    </div>

                    <Button variant="outline" size="sm" type="button" @click="addQuestion" class="w-full gap-1.5 border-dashed">
                        <Plus class="size-3.5" /> Thêm câu hỏi trắc nghiệm mới
                    </Button>
                </div>

                <DialogFooter class="pt-2">
                    <Button variant="outline" type="button" @click="showQuizDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="quizForm.processing" class="bg-indigo-600 font-bold text-white hover:bg-indigo-700">
                        Tạo bài kiểm tra
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- MODAL 5: CỔNG HỌC VIÊN LÀM BÀI HỌC VÀ QUIZ -->
    <Dialog v-model:open="showLearningDialog">
        <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto rounded-2xl border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-950">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white">
                    <BookOpen class="size-5 text-indigo-600" /> {{ learning?.course?.title || 'Nội dung đào tạo' }}
                </DialogTitle>
            </DialogHeader>

            <div v-if="learning" class="mt-4 space-y-6">
                <!-- Progress Header Banner -->
                <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-500/30 dark:bg-indigo-950/30">
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="text-indigo-900 dark:text-indigo-200">
                            Tiến độ hoàn thành: <strong class="text-indigo-600 dark:text-indigo-400">{{ learning.enrollment.progress_percent }}%</strong>
                        </span>
                        <span v-if="learning.enrollment.due_at" :class="learning.enrollment.is_overdue ? 'text-rose-600' : 'text-slate-600 dark:text-slate-300'">
                            <CalendarClock class="mr-1 inline size-3.5" /> Hạn hoàn thành: {{ new Date(learning.enrollment.due_at).toLocaleDateString('vi-VN') }}
                        </span>
                    </div>
                    <div class="mt-2.5 h-2.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-indigo-600 transition-all duration-500" :style="{ width: `${learning.enrollment.progress_percent}%` }"></div>
                    </div>
                    <p v-if="learning.enrollment.awaiting_manager_approval" class="mt-2.5 text-xs font-bold text-amber-700 dark:text-amber-300">
                        ⚠️ Bạn đã hoàn thành bài học lý thuyết. Đang chờ Quản lý ký duyệt phần thực hành!
                    </p>
                </div>

                <!-- Lessons Section -->
                <section class="space-y-3">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                        <BookOpen class="size-4 text-indigo-600" /> Danh sách bài học lý thuyết
                    </h3>
                    <div class="space-y-2.5">
                        <div
                            v-for="lesson in learning.lessons"
                            :key="lesson.id"
                            class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-4 transition dark:border-slate-800 dark:bg-slate-900/50 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ lesson.title }}</h4>
                                    <Badge variant="outline" class="text-[10px]">
                                        {{ lesson.duration_minutes || 10 }} phút
                                    </Badge>
                                </div>
                                <p v-if="lesson.content" class="mt-2 whitespace-pre-wrap text-xs text-slate-600 dark:text-slate-300">
                                    {{ lesson.content }}
                                </p>
                                <a
                                    v-if="lesson.file_url"
                                    :href="`/storage/${lesson.file_url}`"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline"
                                >
                                    <LinkIcon class="size-3.5" /> Mở tài liệu đính kèm
                                </a>
                            </div>

                            <Button
                                size="sm"
                                :variant="(learning.enrollment.completed_lessons || []).includes(lesson.id) ? 'secondary' : 'default'"
                                :disabled="(learning.enrollment.completed_lessons || []).includes(lesson.id)"
                                @click="completeLearningLesson(lesson.id)"
                                class="shrink-0 font-bold"
                            >
                                <CheckCircle2 class="mr-1 size-3.5" />
                                {{ (learning.enrollment.completed_lessons || []).includes(lesson.id) ? 'Đã học' : 'Đánh dấu đã học' }}
                            </Button>
                        </div>
                    </div>
                </section>

                <!-- Quizzes Section -->
                <section v-for="quiz in learning.quizzes" :key="quiz.id" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                    <h3 class="mb-4 flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                        <Award class="size-4 text-indigo-600" /> {{ quiz.title }}
                    </h3>
                    <div v-for="(question, qIndex) in quiz.questions" :key="qIndex" class="mb-5 space-y-2">
                        <p class="text-xs font-bold text-slate-900 dark:text-white">
                            {{ Number(qIndex) + 1 }}. {{ question.question }}
                        </p>
                        <div class="space-y-1.5 pl-2">
                            <label
                                v-for="(option, optionIndex) in question.options"
                                :key="optionIndex"
                                class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-white p-2.5 text-xs text-slate-800 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-900"
                            >
                                <input
                                    v-model.number="quizAnswersFor(quiz)[Number(qIndex)]"
                                    type="radio"
                                    :value="Number(optionIndex)"
                                    class="size-4 text-indigo-600"
                                />
                                <span>{{ option }}</span>
                            </label>
                        </div>
                    </div>
                    <Button @click="submitLearningQuiz(quiz)" class="w-full bg-indigo-600 font-bold text-white hover:bg-indigo-700">
                        Nộp bài kiểm tra trắc nghiệm
                    </Button>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
