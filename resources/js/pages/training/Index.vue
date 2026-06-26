<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Award, BookOpen, CheckCircle2, GraduationCap,
    Plus, Users,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps<{
    courses: any[];
    enrollments: any[];
    stats: { total_courses: number; total_enrollments: number; completed: number; in_progress: number };
}>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
        toast.success(flash.success);
    }

    if (flash?.error) {
        toast.error(flash.error);
    }
});

const activeTab = ref<'courses' | 'enrollments'>('courses');

// Course form
const showCourseDialog = ref(false);
const courseForm = useForm({ title: '', description: '', type: 'onboarding' as string, is_required: false });
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
const lessonForm = useForm({ title: '', content_type: 'text' as string, content: '', duration_minutes: null as number | null });
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
    title: '', pass_score: 70, max_attempts: 3,
    questions: [{ question: '', options: ['', ''], correct: 0 }] as { question: string; options: string[]; correct: number }[],
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
    onboarding: 'Onboarding', menu: 'Thực đơn', attp: 'ATTP', operations: 'Vận hành', custom: 'Tùy chỉnh',
};
const typeColor: Record<string, string> = {
    onboarding: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-none shadow-none',
    menu: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-none shadow-none',
    attp: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-none shadow-none',
    operations: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-none shadow-none',
    custom: 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border-none shadow-none',
};
const statusLabel: Record<string, string> = {
    enrolled: 'Đã ghi danh', in_progress: 'Đang học', completed: 'Hoàn thành', failed: 'Không đạt',
};
const statusColor: Record<string, string> = {
    enrolled: 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border-none shadow-none',
    in_progress: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-none shadow-none',
    completed: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-none shadow-none',
    failed: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-none shadow-none',
};
</script>

<template>
    <Head title="Đào tạo nhân viên" />

    <div class="flex flex-col gap-5 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <GraduationCap class="size-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Đào Tạo & Onboarding</h1>
                    <p class="text-xs text-muted-foreground">Quản lý khóa đào tạo, bài kiểm tra, và chứng chỉ nhân viên.</p>
                </div>
            </div>
            <button
                @click="showCourseDialog = true"
                class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold transition hover:bg-muted active:scale-95 text-foreground self-start sm:self-center"
            >
                <Plus class="size-3.5" /> Tạo khóa học
            </button>
        </div>

        <!-- KPI -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <Card class="overflow-hidden">
                <CardContent class="p-4 relative">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Khóa học</span>
                        <BookOpen class="size-4 text-muted-foreground/70" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-xl font-bold">{{ stats.total_courses }}</span>
                    </div>
                    <p class="text-[10px] text-muted-foreground mt-1">Tổng số khóa học hiện có</p>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <CardContent class="p-4 relative">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Ghi danh</span>
                        <Users class="size-4 text-muted-foreground/70" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-xl font-bold">{{ stats.total_enrollments }}</span>
                    </div>
                    <p class="text-[10px] text-muted-foreground mt-1">Lượt ghi danh tham gia</p>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <CardContent class="p-4 relative">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Hoàn thành</span>
                        <CheckCircle2 class="size-4 text-emerald-500/80" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-xl font-bold text-emerald-600 dark:text-emerald-500">{{ stats.completed }}</span>
                    </div>
                    <p class="text-[10px] text-muted-foreground mt-1">Đã hoàn thành đào tạo</p>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <CardContent class="p-4 relative">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Đang học</span>
                        <GraduationCap class="size-4 text-blue-500/80" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-xl font-bold text-blue-600 dark:text-blue-500">{{ stats.in_progress }}</span>
                    </div>
                    <p class="text-[10px] text-muted-foreground mt-1">Nhân viên đang học tập</p>
                </CardContent>
            </Card>
        </div>

        <!-- Tabs Switcher -->
        <div class="flex border-b border-border/65 pb-0">
            <div class="flex p-0.5 bg-muted/60 dark:bg-muted/30 rounded-lg">
                <button
                    v-for="tab in [{key:'courses',label:'Khóa học'},{key:'enrollments',label:'Tiến độ nhân viên'}]"
                    :key="tab.key"
                    @click="activeTab = tab.key as any"
                    :class="[
                        'px-4 py-1.5 rounded-md text-xs font-semibold transition-all duration-200 active:scale-95',
                        activeTab === tab.key
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Courses tab -->
        <div v-if="activeTab === 'courses'" class="space-y-4">
            <Card v-for="course in courses" :key="course.id" class="overflow-hidden border border-border/50 shadow-sm transition hover:shadow-md">
                <CardContent class="p-5 space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground tracking-tight">{{ course.title }}</h3>
                                <Badge :class="typeColor[course.type]" class="text-[10px] px-2 py-0.5">{{ typeLabel[course.type] }}</Badge>
                                <Badge v-if="course.is_required" variant="destructive" class="text-[10px] px-2 py-0.5">Bắt buộc</Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ course.lessons_count }} bài học · {{ course.enrollments_count }} người đăng ký</p>
                        </div>
                        <div class="flex gap-1.5 self-start sm:self-center">
                            <button
                                @click="openLessonDialog(course.id)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-medium transition hover:bg-muted active:scale-95 text-foreground"
                            >
                                <Plus class="size-3" /> Bài học
                            </button>
                            <button
                                @click="openQuizDialog(course.id)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-medium transition hover:bg-muted active:scale-95 text-foreground"
                            >
                                <Award class="size-3" /> Quiz
                            </button>
                        </div>
                    </div>
                    <div v-if="course.quizzes?.length" class="flex flex-wrap gap-2 pt-2 border-t border-border/40">
                        <Badge v-for="q in course.quizzes" :key="q.id" variant="secondary" class="text-[10px] gap-1 px-2 py-0.5 border-none bg-muted/60 dark:bg-muted/20">
                            <Award class="size-3 text-muted-foreground" /> {{ q.title }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
            <p v-if="!courses.length" class="text-center text-muted-foreground py-12 text-sm">Chưa có khóa đào tạo nào.</p>
        </div>

        <!-- Enrollments tab -->
        <div v-if="activeTab === 'enrollments'">
            <Card class="overflow-hidden border border-border/50 shadow-sm">
                <CardContent class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="border-b border-border/55 bg-muted/20 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">
                                <th class="px-5 py-3">Nhân viên</th>
                                <th class="px-5 py-3">Khóa học</th>
                                <th class="px-5 py-3 text-center">Tiến độ</th>
                                <th class="px-5 py-3">Trạng thái</th>
                                <th class="px-5 py-3">Chứng chỉ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr v-for="e in enrollments" :key="e.id" class="transition hover:bg-muted/10 group">
                                <td class="px-5 py-3.5 text-xs font-medium text-foreground">{{ e.employee?.full_name ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-xs text-muted-foreground">{{ e.course?.title }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2 justify-center">
                                        <div class="h-1.5 w-16 rounded-full bg-muted overflow-hidden">
                                            <div class="h-full bg-blue-500 rounded-full transition-all duration-300" :style="{ width: e.progress_percent + '%' }"></div>
                                        </div>
                                        <span class="text-[11px] font-bold w-8 text-right">{{ e.progress_percent }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <Badge :class="statusColor[e.status]" class="text-[10px] px-2 py-0.5">{{ statusLabel[e.status] }}</Badge>
                                </td>
                                <td class="px-5 py-3.5 text-xs">
                                    <span v-if="e.certificate_code" class="font-mono text-emerald-600 dark:text-emerald-500 font-bold bg-emerald-500/5 px-2 py-1 rounded-md">{{ e.certificate_code }}</span>
                                    <span v-else class="text-muted-foreground/60">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!enrollments.length" class="text-center text-muted-foreground py-12 text-sm">Chưa có ghi danh nào.</p>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Create Course -->
    <Dialog v-model:open="showCourseDialog">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle>Tạo Khóa Đào Tạo</DialogTitle></DialogHeader>
            <form @submit.prevent="submitCourse" class="space-y-4">
                <div class="grid gap-1.5"><Label>Tên khóa học</Label><Input v-model="courseForm.title" required /></div>
                <div class="grid gap-1.5"><Label>Mô tả</Label><Input v-model="courseForm.description" /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select v-model="courseForm.type" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="onboarding">Onboarding</option><option value="menu">Thực đơn</option>
                            <option value="attp">ATTP</option><option value="operations">Vận hành</option><option value="custom">Tùy chỉnh</option>
                        </select>
                    </div>
                    <div class="flex items-end pb-1"><label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" v-model="courseForm.is_required" class="rounded" /><span class="text-sm">Bắt buộc</span></label></div>
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showCourseDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="courseForm.processing">{{ courseForm.processing ? 'Đang tạo...' : 'Tạo khóa học' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add Lesson -->
    <Dialog v-model:open="showLessonDialog">
        <DialogContent class="max-w-md">
            <DialogHeader><DialogTitle>Thêm Bài Học</DialogTitle></DialogHeader>
            <form @submit.prevent="submitLesson" class="space-y-4">
                <div class="grid gap-1.5"><Label>Tiêu đề</Label><Input v-model="lessonForm.title" required /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Loại nội dung</Label>
                        <select v-model="lessonForm.content_type" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="text">Văn bản</option><option value="video">Video</option>
                            <option value="pdf">PDF</option><option value="link">Link</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5"><Label>Thời lượng (phút)</Label><Input type="number" v-model="lessonForm.duration_minutes" /></div>
                </div>
                <div class="grid gap-1.5"><Label>Nội dung / URL</Label><Input v-model="lessonForm.content" placeholder="Nội dung hoặc URL..." /></div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showLessonDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="lessonForm.processing">Thêm bài học</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add Quiz -->
    <Dialog v-model:open="showQuizDialog">
        <DialogContent class="max-w-lg max-h-[80vh] overflow-y-auto">
            <DialogHeader><DialogTitle>Tạo Bài Kiểm Tra</DialogTitle></DialogHeader>
            <form @submit.prevent="submitQuiz" class="space-y-4">
                <div class="grid gap-1.5"><Label>Tiêu đề</Label><Input v-model="quizForm.title" required /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5"><Label>Điểm đạt (%)</Label><Input type="number" v-model="quizForm.pass_score" /></div>
                    <div class="grid gap-1.5"><Label>Số lần làm tối đa</Label><Input type="number" v-model="quizForm.max_attempts" /></div>
                </div>
                <div class="space-y-3">
                    <Label>Câu hỏi</Label>
                    <div v-for="(q, qIdx) in quizForm.questions" :key="qIdx" class="border rounded-lg p-3 space-y-2">
                        <Input v-model="q.question" :placeholder="'Câu ' + (qIdx + 1)" required />
                        <div v-for="(opt, oIdx) in q.options" :key="oIdx" class="flex items-center gap-2">
                            <input type="radio" :name="'q'+qIdx" :value="oIdx" v-model="q.correct" />
                            <Input v-model="q.options[oIdx]" :placeholder="'Đáp án ' + (oIdx + 1)" class="flex-1" required />
                        </div>
                        <Button variant="ghost" size="sm" type="button" @click="addOption(qIdx)" class="text-xs">+ Thêm đáp án</Button>
                    </div>
                    <Button variant="outline" size="sm" type="button" @click="addQuestion" class="w-full gap-1.5"><Plus class="size-3.5" /> Thêm câu hỏi</Button>
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showQuizDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="quizForm.processing">Tạo bài kiểm tra</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
