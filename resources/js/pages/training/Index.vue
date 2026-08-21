<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Award,
    BookOpen,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    GraduationCap,
    Plus,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import axios from 'axios';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
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
        assigned: number;
        overdue: number;
        awaiting_approval: number;
        failed: number;
        certificates_expiring: number;
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

// Giao đào tạo theo nghiệp vụ: nhân viên, hạn hoàn thành và tính bắt buộc.
const showAssignmentDialog = ref(false);
const assignmentCourseId = ref<number | null>(null);
const assignmentForm = useForm({
    employee_ids: [] as number[],
    due_at: '',
    mandatory: false,
    reason: 'Giao đào tạo theo kế hoạch vận hành',
});
function openAssignmentDialog(course: any) {
    assignmentCourseId.value = course.id;
    assignmentForm.reset();
    assignmentForm.mandatory = Boolean(course.is_required || course.required_for_new_hires);
    assignmentForm.due_at = new Date(Date.now() + Number(course.due_days || 14) * 86400000)
        .toISOString()
        .slice(0, 16);
    showAssignmentDialog.value = true;
}
function submitAssignment() {
    if (!assignmentCourseId.value || assignmentForm.employee_ids.length === 0) return;
    assignmentForm.post('/training/enroll', {
        onSuccess: () => {
            showAssignmentDialog.value = false;
        },
    });
}

// Cổng học viên: không trả đáp án đúng xuống trình duyệt, nội dung quiz lấy từ API đã lọc quyền.
const showLearningDialog = ref(false);
const learning = ref<any>(null);
const learningLoading = ref(false);
const quizAnswers = ref<Record<number, number[]>>({});
async function openLearning(enrollment: any) {
    learningLoading.value = true;
    try {
        const response = await axios.get(`/training/courses/${enrollment.course_id || enrollment.course?.id}/content`);
        learning.value = response.data;
        quizAnswers.value = {};
        showLearningDialog.value = true;
    } finally {
        learningLoading.value = false;
    }
}
async function completeLearningLesson(lessonId: number) {
    if (!learning.value?.enrollment?.id) return;
    const response = await axios.post('/training/complete-lesson', {
        enrollment_id: learning.value.enrollment.id,
        lesson_id: lessonId,
    });
    const completed = new Set(learning.value.enrollment.completed_lessons || []);
    completed.add(lessonId);
    learning.value.enrollment.completed_lessons = Array.from(completed);
    learning.value.enrollment.progress_percent = response.data.progress ?? learning.value.enrollment.progress_percent;
}
async function submitLearningQuiz(quiz: any) {
    if (!learning.value?.enrollment?.id) return;
    const response = await axios.post('/training/submit-quiz', {
        enrollment_id: learning.value.enrollment.id,
        quiz_id: quiz.id,
        answers: quizAnswers.value[quiz.id] || [],
    });
    toast.success(`Kết quả: ${response.data.score}%`);
    learning.value.enrollment.progress_percent = Math.max(learning.value.enrollment.progress_percent, response.data.progress || learning.value.enrollment.progress_percent);
    if (response.data.certificate_code) learning.value.enrollment.certificate_code = response.data.certificate_code;
}
function quizAnswersFor(quiz: any): number[] {
    if (!quizAnswers.value[quiz.id]) {
        quizAnswers.value[quiz.id] = [];
    }
    return quizAnswers.value[quiz.id];
}
async function approveEnrollment(enrollment: any) {
    await axios.post(`/training/enrollments/${enrollment.id}/approve`);
    enrollment.awaiting_manager_approval = false;
    enrollment.status = 'completed';
    toast.success('Đã ký duyệt hoàn thành đào tạo.');
}

// Course form
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
function submitCourse() {
    courseForm.post('/training/courses', {
        onSuccess: () => {
            showCourseDialog.value = false;
            courseForm.reset();
        },
    });
}

// Lesson form
const showLessonDialog = ref(false);
const lessonCourseId = ref<number | null>(null);
const lessonForm = useForm({
    title: '',
    content_type: 'text' as string,
    content: '',
    duration_minutes: null as number | null,
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
        },
    });
}

// Quiz form
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
function addOption(qIdx: number) {
    quizForm.questions[qIdx].options.push('');
}
function submitQuiz() {
    if (!quizCourseId.value) {
        return;
    }

    quizForm.post(`/training/courses/${quizCourseId.value}/quizzes`, {
        onSuccess: () => {
            showQuizDialog.value = false;
        },
    });
}

const typeLabel: Record<string, string> = {
    onboarding: 'Onboarding',
    menu: 'Thực đơn',
    attp: 'ATTP',
    operations: 'Vận hành',
    custom: 'Tùy chỉnh',
};
const typeColor: Record<string, string> = {
    onboarding:
        'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-none shadow-none',
    menu: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-none shadow-none',
    attp: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-none shadow-none',
    operations:
        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-none shadow-none',
    custom: 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border-none shadow-none',
};
const statusLabel: Record<string, string> = {
    enrolled: 'Đã ghi danh',
    in_progress: 'Đang học',
    completed: 'Hoàn thành',
    failed: 'Không đạt',
};
const statusColor: Record<string, string> = {
    enrolled:
        'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border-none shadow-none',
    in_progress:
        'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-none shadow-none',
    completed:
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-none shadow-none',
    failed: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-none shadow-none',
};
</script>

<template>
    <Head title="Đào tạo nhân viên" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 lg:p-6">
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400"
                >
                    <GraduationCap class="size-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Đào Tạo & Onboarding
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        Quản lý khóa đào tạo, bài kiểm tra, và chứng chỉ nhân
                        viên.
                    </p>
                </div>
            </div>
            <button
                @click="showCourseDialog = true"
                class="inline-flex items-center justify-center gap-1.5 self-start rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold text-foreground transition hover:bg-muted active:scale-95 sm:self-center"
            >
                <Plus class="size-3.5" /> Tạo khóa học
            </button>
        </div>

        <!-- KPI -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Card class="overflow-hidden">
                <CardContent class="relative p-4">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Khóa học</span
                        >
                        <BookOpen class="size-4 text-muted-foreground/70" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-xl font-bold">{{
                            stats.total_courses
                        }}</span>
                    </div>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Tổng số khóa học hiện có
                    </p>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <CardContent class="relative p-4">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Ghi danh</span
                        >
                        <Users class="size-4 text-muted-foreground/70" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-xl font-bold">{{
                            stats.total_enrollments
                        }}</span>
                    </div>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Lượt ghi danh tham gia
                    </p>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <CardContent class="relative p-4">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Hoàn thành</span
                        >
                        <CheckCircle2 class="size-4 text-emerald-500/80" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span
                            class="text-xl font-bold text-emerald-600 dark:text-emerald-500"
                            >{{ stats.completed }}</span
                        >
                    </div>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Đã hoàn thành đào tạo
                    </p>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <CardContent class="relative p-4">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Đang học</span
                        >
                        <GraduationCap class="size-4 text-blue-500/80" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span
                            class="text-xl font-bold text-blue-600 dark:text-blue-500"
                            >{{ stats.in_progress }}</span
                        >
                    </div>
                    <p class="mt-1 text-[10px] text-muted-foreground">
                        Nhân viên đang học tập
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="canManage && (stats.overdue || stats.awaiting_approval || stats.failed)"
            class="grid gap-2 rounded-xl border border-amber-500/25 bg-amber-500/5 p-3 text-xs sm:grid-cols-3"
        >
            <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300">
                <AlertTriangle class="size-4" />
                <span><strong>{{ stats.overdue }}</strong> khóa đã quá hạn</span>
            </div>
            <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                <ClipboardCheck class="size-4" />
                <span><strong>{{ stats.awaiting_approval }}</strong> hồ sơ chờ ký duyệt</span>
            </div>
            <div class="flex items-center gap-2 text-rose-700 dark:text-rose-300">
                <Clock3 class="size-4" />
                <span><strong>{{ stats.failed }}</strong> hồ sơ chưa đạt</span>
            </div>
        </div>

        <!-- Tabs Switcher -->
        <div class="flex border-b border-border/65 pb-0">
            <div class="flex rounded-lg bg-muted/60 p-0.5 dark:bg-muted/30">
                <button
                    v-for="tab in [
                        { key: 'courses', label: 'Khóa học' },
                        { key: 'enrollments', label: 'Tiến độ nhân viên' },
                    ]"
                    :key="tab.key"
                    @click="activeTab = tab.key as any"
                    :class="[
                        'rounded-md px-4 py-1.5 text-xs font-semibold transition-all duration-200 active:scale-95',
                        activeTab === tab.key
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Courses tab -->
        <div v-if="activeTab === 'courses'" class="animate-fade-in space-y-4">
            <Card
                v-for="course in courses"
                :key="course.id"
                class="overflow-hidden border border-border/50 shadow-sm transition hover:shadow-md"
            >
                <CardContent class="space-y-4 p-5">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3
                                    class="text-sm font-semibold tracking-tight text-foreground"
                                >
                                    {{ course.title }}
                                </h3>
                                <Badge
                                    :class="typeColor[course.type]"
                                    class="px-2 py-0.5 text-[10px]"
                                    >{{ typeLabel[course.type] }}</Badge
                                >
                                <Badge
                                    v-if="course.is_required"
                                    variant="destructive"
                                    class="px-2 py-0.5 text-[10px]"
                                    >Bắt buộc</Badge
                                >
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ course.lessons_count }} bài học ·
                                {{ course.enrollments_count }} người đăng ký
                            </p>
                        </div>
                        <div class="flex gap-1.5 self-start sm:self-center">
                            <button
                                v-if="canManage"
                                @click="openAssignmentDialog(course)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-blue-500/30 bg-blue-500/5 px-2.5 py-1.5 text-xs font-medium text-blue-600 transition hover:bg-blue-500/10 active:scale-95"
                            >
                                <Users class="size-3" /> Giao học
                            </button>
                            <button
                                v-if="canManage"
                                @click="openLessonDialog(course.id)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted active:scale-95"
                            >
                                <Plus class="size-3" /> Bài học
                            </button>
                            <button
                                v-if="canManage"
                                @click="openQuizDialog(course.id)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted active:scale-95"
                            >
                                <Award class="size-3" /> Quiz
                            </button>
                        </div>
                    </div>
                    <div
                        v-if="course.quizzes?.length"
                        class="flex flex-wrap gap-2 border-t border-border/40 pt-2"
                    >
                        <Badge
                            v-for="q in course.quizzes"
                            :key="q.id"
                            variant="secondary"
                            class="gap-1 border-none bg-muted/60 px-2 py-0.5 text-[10px] dark:bg-muted/20"
                        >
                            <Award class="size-3 text-muted-foreground" />
                            {{ q.title }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
            <p
                v-if="!courses.length"
                class="py-12 text-center text-sm text-muted-foreground"
            >
                Chưa có khóa đào tạo nào.
            </p>
        </div>

        <!-- Enrollments tab -->
        <div
            v-if="activeTab === 'enrollments'"
            class="animate-fade-in space-y-4"
        >
            <Card class="overflow-hidden border border-border/50 shadow-sm">
                <CardContent class="overflow-x-auto p-0">
                    <table
                        class="w-full min-w-[600px] border-collapse text-left"
                    >
                        <thead>
                            <tr
                                class="border-b border-border/55 bg-muted/20 text-[10px] font-bold tracking-wider text-muted-foreground/80 uppercase"
                            >
                                <th class="px-5 py-3">Nhân viên</th>
                                <th class="px-5 py-3">Khóa học</th>
                                <th class="px-5 py-3 text-center">Tiến độ</th>
                                <th class="px-5 py-3">Trạng thái</th>
                                <th class="px-5 py-3">Chứng chỉ</th>
                                <th class="px-5 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr
                                v-for="e in enrollments"
                                :key="e.id"
                                class="group transition hover:bg-muted/10"
                            >
                                <td
                                    class="px-5 py-3.5 text-xs font-medium text-foreground"
                                >
                                    {{ e.employee?.full_name ?? '—' }}
                                </td>
                                <td
                                    class="px-5 py-3.5 text-xs text-muted-foreground"
                                >
                                    {{ e.course?.title }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <div
                                        class="flex items-center justify-center gap-2"
                                    >
                                        <div
                                            class="h-1.5 w-16 overflow-hidden rounded-full bg-muted"
                                        >
                                            <div
                                                class="h-full rounded-full bg-blue-500 transition-all duration-300"
                                                :style="{
                                                    width:
                                                        e.progress_percent +
                                                        '%',
                                                }"
                                            ></div>
                                        </div>
                                        <span
                                            class="w-8 text-right text-[11px] font-bold"
                                            >{{ e.progress_percent }}%</span
                                        >
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <Badge
                                        :class="statusColor[e.status]"
                                        class="px-2 py-0.5 text-[10px]"
                                        >{{ statusLabel[e.status] }}</Badge
                                    >
                                </td>
                                <td class="px-5 py-3.5 text-xs">
                                    <span
                                        v-if="e.certificate_code"
                                        class="rounded-md bg-emerald-500/5 px-2 py-1 font-mono font-bold text-emerald-600 dark:text-emerald-500"
                                        >{{ e.certificate_code }}</span
                                    >
                                    <span
                                        v-else
                                        class="text-muted-foreground/60"
                                        >—</span
                                    >
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <Button
                                        v-if="!canManage"
                                        size="sm"
                                        variant="outline"
                                        :disabled="learningLoading"
                                        @click="openLearning(e)"
                                    >
                                        <BookOpen class="mr-1 size-3.5" /> Học
                                    </Button>
                                    <Button
                                        v-else-if="e.awaiting_manager_approval"
                                        size="sm"
                                        class="bg-emerald-600 text-white hover:bg-emerald-700"
                                        @click="approveEnrollment(e)"
                                    >
                                        <ShieldCheck class="mr-1 size-3.5" /> Duyệt
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!enrollments.length"
                        class="py-12 text-center text-sm text-muted-foreground"
                    >
                        Chưa có ghi danh nào.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Assign course -->
    <Dialog v-model:open="showAssignmentDialog">
        <DialogContent class="max-h-[85vh] max-w-xl overflow-y-auto">
            <DialogHeader><DialogTitle>Giao đào tạo cho nhân viên</DialogTitle></DialogHeader>
            <form @submit.prevent="submitAssignment" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Nhân viên thực hiện</Label>
                    <div class="max-h-52 space-y-1 overflow-y-auto rounded-lg border p-2">
                        <label
                            v-for="employee in employees"
                            :key="employee.id"
                            class="flex cursor-pointer items-center justify-between rounded-md px-2 py-2 text-sm hover:bg-muted/50"
                        >
                            <span class="flex items-center gap-2">
                                <input v-model="assignmentForm.employee_ids" type="checkbox" :value="employee.id" />
                                <span>{{ employee.full_name }}</span>
                            </span>
                            <span class="text-[11px] text-muted-foreground">{{ employee.branch_name }} · {{ employee.role || 'Nhân viên' }}</span>
                        </label>
                        <p v-if="!employees.length" class="p-4 text-center text-xs text-muted-foreground">Chưa có nhân viên đang hoạt động.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Hạn hoàn thành</Label>
                        <Input v-model="assignmentForm.due_at" type="datetime-local" required />
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm">
                        <input v-model="assignmentForm.mandatory" type="checkbox" /> Bắt buộc đạt
                    </label>
                </div>
                <div class="grid gap-1.5">
                    <Label>Lý do giao</Label>
                    <Input v-model="assignmentForm.reason" maxlength="120" />
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showAssignmentDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="assignmentForm.processing || !assignmentForm.employee_ids.length">Giao khóa học</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Learner course -->
    <Dialog v-model:open="showLearningDialog">
        <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ learning?.course?.title || 'Nội dung đào tạo' }}</DialogTitle>
            </DialogHeader>
            <div v-if="learning" class="space-y-5">
                <div class="rounded-xl border bg-muted/20 p-4">
                    <div class="flex items-center justify-between text-xs">
                        <span>Tiến độ: <strong>{{ learning.enrollment.progress_percent }}%</strong></span>
                        <span v-if="learning.enrollment.due_at" :class="learning.enrollment.is_overdue ? 'text-rose-600' : 'text-muted-foreground'">
                            <CalendarClock class="mr-1 inline size-3.5" /> Hạn {{ new Date(learning.enrollment.due_at).toLocaleDateString('vi-VN') }}
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-blue-500 transition-all" :style="{ width: `${learning.enrollment.progress_percent}%` }"></div>
                    </div>
                    <p v-if="learning.enrollment.awaiting_manager_approval" class="mt-2 text-xs text-amber-600">Đã hoàn tất nội dung, đang chờ quản lý ký duyệt thực hành.</p>
                </div>

                <section>
                    <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold"><BookOpen class="size-4" /> Bài học</h3>
                    <div class="space-y-2">
                        <div v-for="lesson in learning.lessons" :key="lesson.id" class="flex items-center justify-between rounded-lg border p-3">
                            <div class="min-w-0 pr-3">
                                <p class="text-sm font-medium">{{ lesson.title }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ lesson.content_type }} · {{ lesson.duration_minutes || 0 }} phút</p>
                                <p v-if="lesson.content" class="mt-2 whitespace-pre-wrap text-xs text-muted-foreground">{{ lesson.content }}</p>
                                <a v-if="lesson.file_url" :href="`/storage/${lesson.file_url}`" target="_blank" rel="noreferrer" class="mt-2 inline-block text-xs font-medium text-blue-600 hover:underline">Mở tài liệu</a>
                            </div>
                            <Button
                                size="sm"
                                :variant="(learning.enrollment.completed_lessons || []).includes(lesson.id) ? 'secondary' : 'default'"
                                :disabled="(learning.enrollment.completed_lessons || []).includes(lesson.id)"
                                @click="completeLearningLesson(lesson.id)"
                            >
                                <CheckCircle2 class="mr-1 size-3.5" />
                                {{ (learning.enrollment.completed_lessons || []).includes(lesson.id) ? 'Đã học' : 'Hoàn tất' }}
                            </Button>
                        </div>
                    </div>
                </section>

                <section v-for="quiz in learning.quizzes" :key="quiz.id" class="rounded-xl border p-4">
                    <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold"><Award class="size-4" /> {{ quiz.title }}</h3>
                    <div v-for="(question, qIndex) in quiz.questions" :key="qIndex" class="mb-4 space-y-2">
                        <p class="text-sm font-medium">{{ Number(qIndex) + 1 }}. {{ question.question }}</p>
                        <label v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex cursor-pointer items-center gap-2 text-xs">
                            <input v-model.number="quizAnswersFor(quiz)[Number(qIndex)]" type="radio" :value="Number(optionIndex)" />
                            {{ option }}
                        </label>
                    </div>
                    <Button @click="submitLearningQuiz(quiz)">Nộp bài kiểm tra</Button>
                </section>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Create Course -->
    <Dialog v-model:open="showCourseDialog">
        <DialogContent class="max-w-md">
            <DialogHeader
                ><DialogTitle>Tạo Khóa Đào Tạo</DialogTitle></DialogHeader
            >
            <form @submit.prevent="submitCourse" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Tên khóa học</Label
                    ><Input v-model="courseForm.title" required />
                </div>
                <div class="grid gap-1.5">
                    <Label>Mô tả</Label
                    ><Input v-model="courseForm.description" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label>Mã khóa</Label>
                        <Input v-model="courseForm.course_code" placeholder="ATTP-2026" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Hạn mặc định (ngày)</Label>
                        <Input v-model.number="courseForm.due_days" type="number" min="1" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Áp dụng cho vai trò</Label>
                    <select v-model="courseForm.target_roles" multiple class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="manager">Quản lý</option>
                        <option value="warehouse_manager">Trưởng kho</option>
                        <option value="warehouse_staff">Nhân viên kho</option>
                        <option value="kitchen">Bếp</option>
                        <option value="cashier">Thu ngân</option>
                        <option value="waiter">Phục vụ</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Giới hạn chi nhánh (để trống = toàn chuỗi)</Label>
                    <select v-model="courseForm.target_branch_ids" multiple class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm">
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                    </select>
                </div>
                <div class="grid gap-2 text-sm">
                    <label class="flex items-center gap-2"><input v-model="courseForm.required_for_new_hires" type="checkbox" /> Tự động giao cho nhân viên mới</label>
                    <label class="flex items-center gap-2"><input v-model="courseForm.requires_manager_signoff" type="checkbox" /> Cần quản lý ký duyệt phần thực hành</label>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select
                            v-model="courseForm.type"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="onboarding">Onboarding</option>
                            <option value="menu">Thực đơn</option>
                            <option value="attp">ATTP</option>
                            <option value="operations">Vận hành</option>
                            <option value="custom">Tùy chỉnh</option>
                        </select>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex cursor-pointer items-center gap-2"
                            ><input
                                type="checkbox"
                                v-model="courseForm.is_required"
                                class="rounded"
                            /><span class="text-sm">Bắt buộc</span></label
                        >
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="showCourseDialog = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="courseForm.processing">{{
                        courseForm.processing ? 'Đang tạo...' : 'Tạo khóa học'
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add Lesson -->
    <Dialog v-model:open="showLessonDialog">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle>Thêm Bài Học</DialogTitle></DialogHeader>
            <form @submit.prevent="submitLesson" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Tiêu đề</Label
                    ><Input v-model="lessonForm.title" required />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại nội dung</Label>
                        <select
                            v-model="lessonForm.content_type"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="text">Văn bản</option>
                            <option value="video">Video</option>
                            <option value="pdf">PDF</option>
                            <option value="link">Link</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Thời lượng (phút)</Label
                        ><Input
                            type="number"
                            v-model="lessonForm.duration_minutes"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label>Nội dung / URL</Label
                    ><Input
                        v-model="lessonForm.content"
                        placeholder="Nội dung hoặc URL..."
                    />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="showLessonDialog = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="lessonForm.processing"
                        >Thêm bài học</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add Quiz -->
    <Dialog v-model:open="showQuizDialog">
        <DialogContent class="max-h-[80vh] max-w-lg overflow-y-auto">
            <DialogHeader
                ><DialogTitle>Tạo Bài Kiểm Tra</DialogTitle></DialogHeader
            >
            <form @submit.prevent="submitQuiz" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Tiêu đề</Label
                    ><Input v-model="quizForm.title" required />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Điểm đạt (%)</Label
                        ><Input type="number" v-model="quizForm.pass_score" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Số lần làm tối đa</Label
                        ><Input type="number" v-model="quizForm.max_attempts" />
                    </div>
                </div>
                <div class="space-y-3">
                    <Label>Câu hỏi</Label>
                    <div
                        v-for="(q, qIdx) in quizForm.questions"
                        :key="qIdx"
                        class="space-y-2 rounded-lg border p-3"
                    >
                        <Input
                            v-model="q.question"
                            :placeholder="'Câu ' + (qIdx + 1)"
                            required
                        />
                        <div
                            v-for="(opt, oIdx) in q.options"
                            :key="oIdx"
                            class="flex items-center gap-2"
                        >
                            <input
                                type="radio"
                                :name="'q' + qIdx"
                                :value="oIdx"
                                v-model="q.correct"
                            />
                            <Input
                                v-model="q.options[oIdx]"
                                :placeholder="'Đáp án ' + (oIdx + 1)"
                                class="flex-1"
                                required
                            />
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            type="button"
                            @click="addOption(qIdx)"
                            class="text-xs"
                            >+ Thêm đáp án</Button
                        >
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        type="button"
                        @click="addQuestion"
                        class="w-full gap-1.5"
                        ><Plus class="size-3.5" /> Thêm câu hỏi</Button
                    >
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="showQuizDialog = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="quizForm.processing"
                        >Tạo bài kiểm tra</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
