<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Award, BookOpen, CheckCircle2, FileText, GraduationCap, Loader2,
    Plus, Trash2, Users, Video,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    courses: any[];
    enrollments: any[];
    stats: { total_courses: number; total_enrollments: number; completed: number; in_progress: number };
}>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
});

const activeTab = ref<'courses' | 'enrollments'>('courses');

// Course form
const showCourseDialog = ref(false);
const courseForm = useForm({ title: '', description: '', type: 'onboarding' as string, is_required: false });
function submitCourse() {
    courseForm.post('/training/courses', { onSuccess: () => { showCourseDialog.value = false; courseForm.reset(); } });
}

// Lesson form
const showLessonDialog = ref(false);
const lessonCourseId = ref<number | null>(null);
const lessonForm = useForm({ title: '', content_type: 'text' as string, content: '', duration_minutes: null as number | null });
function openLessonDialog(courseId: number) { lessonCourseId.value = courseId; lessonForm.reset(); showLessonDialog.value = true; }
function submitLesson() {
    if (!lessonCourseId.value) return;
    lessonForm.post(`/training/courses/${lessonCourseId.value}/lessons`, { onSuccess: () => { showLessonDialog.value = false; } });
}

// Quiz form
const showQuizDialog = ref(false);
const quizCourseId = ref<number | null>(null);
const quizForm = useForm({
    title: '', pass_score: 70, max_attempts: 3,
    questions: [{ question: '', options: ['', ''], correct: 0 }] as { question: string; options: string[]; correct: number }[],
});
function openQuizDialog(courseId: number) { quizCourseId.value = courseId; quizForm.reset(); quizForm.questions = [{ question: '', options: ['', ''], correct: 0 }]; showQuizDialog.value = true; }
function addQuestion() { quizForm.questions.push({ question: '', options: ['', ''], correct: 0 }); }
function addOption(qIdx: number) { quizForm.questions[qIdx].options.push(''); }
function submitQuiz() {
    if (!quizCourseId.value) return;
    quizForm.post(`/training/courses/${quizCourseId.value}/quizzes`, { onSuccess: () => { showQuizDialog.value = false; } });
}

const typeLabel: Record<string, string> = {
    onboarding: 'Onboarding', menu: 'Thực đơn', attp: 'ATTP', operations: 'Vận hành', custom: 'Tùy chỉnh',
};
const typeColor: Record<string, string> = {
    onboarding: 'bg-blue-100 text-blue-800', menu: 'bg-green-100 text-green-800',
    attp: 'bg-red-100 text-red-800', operations: 'bg-amber-100 text-amber-800', custom: 'bg-gray-100 text-gray-700',
};
const statusLabel: Record<string, string> = {
    enrolled: 'Đã ghi danh', in_progress: 'Đang học', completed: 'Hoàn thành', failed: 'Không đạt',
};
const statusColor: Record<string, string> = {
    enrolled: 'bg-gray-100 text-gray-700', in_progress: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800', failed: 'bg-red-100 text-red-800',
};
const contentIcon: Record<string, any> = { text: FileText, video: Video, pdf: FileText, link: BookOpen };
</script>

<template>
    <Head title="Đào tạo nhân viên" />

    <div class="flex flex-col gap-6 p-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600">
                    <GraduationCap class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Đào Tạo & Onboarding</h1>
                    <p class="text-sm text-muted-foreground">Quản lý khóa đào tạo, bài kiểm tra, và chứng chỉ nhân viên.</p>
                </div>
            </div>
            <Button @click="showCourseDialog = true" class="gap-1.5"><Plus class="size-4" /> Tạo khóa học</Button>
        </div>

        <!-- KPI -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Khóa học</p><p class="text-2xl font-bold">{{ stats.total_courses }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Ghi danh</p><p class="text-2xl font-bold">{{ stats.total_enrollments }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Hoàn thành</p><p class="text-2xl font-bold text-green-600">{{ stats.completed }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Đang học</p><p class="text-2xl font-bold text-blue-600">{{ stats.in_progress }}</p></CardContent></Card>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 border-b pb-2">
            <button v-for="tab in [{key:'courses',label:'Khóa học'},{key:'enrollments',label:'Tiến độ nhân viên'}]" :key="tab.key"
                @click="activeTab = tab.key as any"
                :class="['px-4 py-2 rounded-lg text-sm font-semibold', activeTab === tab.key ? 'bg-blue-600 text-white' : 'hover:bg-muted']"
            >{{ tab.label }}</button>
        </div>

        <!-- Courses tab -->
        <div v-if="activeTab === 'courses'" class="space-y-4">
            <Card v-for="course in courses" :key="course.id">
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base flex items-center gap-2">
                                {{ course.title }}
                                <Badge :class="typeColor[course.type]" class="text-xs">{{ typeLabel[course.type] }}</Badge>
                                <Badge v-if="course.is_required" variant="destructive" class="text-xs">Bắt buộc</Badge>
                            </CardTitle>
                            <CardDescription>{{ course.lessons_count }} bài học · {{ course.enrollments_count }} người đăng ký</CardDescription>
                        </div>
                        <div class="flex gap-1">
                            <Button variant="outline" size="sm" @click="openLessonDialog(course.id)" class="gap-1 text-xs"><Plus class="size-3" /> Bài học</Button>
                            <Button variant="outline" size="sm" @click="openQuizDialog(course.id)" class="gap-1 text-xs"><Award class="size-3" /> Quiz</Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent v-if="course.quizzes?.length" class="pt-0">
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="q in course.quizzes" :key="q.id" variant="secondary" class="text-xs gap-1">
                            <Award class="size-3" /> {{ q.title }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
            <p v-if="!courses.length" class="text-center text-muted-foreground py-12">Chưa có khóa đào tạo nào.</p>
        </div>

        <!-- Enrollments tab -->
        <div v-if="activeTab === 'enrollments'">
            <Card>
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="px-4 py-3">Nhân viên</th>
                                <th class="px-4 py-3">Khóa học</th>
                                <th class="px-4 py-3 text-center">Tiến độ</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3">Chứng chỉ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in enrollments" :key="e.id" class="border-b last:border-0">
                                <td class="px-4 py-3 font-medium">{{ e.employee?.full_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs">{{ e.course?.title }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2 justify-center">
                                        <div class="h-2 w-16 rounded-full bg-muted overflow-hidden">
                                            <div class="h-full bg-blue-500" :style="{ width: e.progress_percent + '%' }"></div>
                                        </div>
                                        <span class="text-xs font-bold">{{ e.progress_percent }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :class="statusColor[e.status]" class="text-xs">{{ statusLabel[e.status] }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <span v-if="e.certificate_code" class="font-mono text-green-600 font-bold">{{ e.certificate_code }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!enrollments.length" class="text-center text-muted-foreground py-12">Chưa có ghi danh nào.</p>
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
