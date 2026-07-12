<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Facebook, Phone, Star } from 'lucide-vue-next';
</script>

<template>
    <footer
        class="relative z-10 border-t border-white/5 bg-[#090e1a] px-4 pt-16 pb-8 font-sans text-zinc-300"
    >
        <div class="mx-auto max-w-7xl">
            <!-- Main grid -->
            <div class="grid grid-cols-1 gap-12 pb-12 md:grid-cols-3 md:gap-8">
                <!-- Brand block -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <Star
                            class="size-5 shrink-0 fill-amber-400 text-amber-400"
                        />
                        <span
                            class="font-serif text-2xl font-bold tracking-wide text-white"
                            >Aventura</span
                        >
                    </div>
                    <p class="max-w-sm text-sm leading-relaxed text-zinc-400">
                        SaaS quản lý nhà hàng toàn diện – từ order, bếp, đến
                        phân tích doanh thu. Vận hành thông minh hơn mỗi ngày.
                    </p>
                </div>

                <!-- Quick links block -->
                <div class="space-y-4">
                    <h3
                        class="font-sans text-sm font-bold tracking-wider text-[#dfb26c] uppercase"
                    >
                        LIÊN KẾT NHANH
                    </h3>
                    <ul class="space-y-2.5 text-sm">
                        <li>
                            <Link
                                href="/"
                                class="group flex items-center transition-colors hover:text-white"
                            >
                                <span
                                    class="mr-2 text-zinc-500 transition-colors group-hover:text-amber-400"
                                    >&gt;</span
                                >
                                <span>Trang chủ</span>
                            </Link>
                        </li>
                        <li>
                            <Link
                                href="/status"
                                class="group flex items-center transition-colors hover:text-white"
                            >
                                <span
                                    class="mr-2 text-zinc-500 transition-colors group-hover:text-amber-400"
                                    >&gt;</span
                                >
                                <span>Trạng thái hệ thống</span>
                            </Link>
                        </li>
                        <li>
                            <a
                                href="#features"
                                class="group flex items-center transition-colors hover:text-white"
                            >
                                <span
                                    class="mr-2 text-zinc-500 transition-colors group-hover:text-amber-400"
                                    >&gt;</span
                                >
                                <span>Tính năng</span>
                            </a>
                        </li>
                        <li>
                            <a
                                href="#testimonials"
                                class="group flex items-center transition-colors hover:text-white"
                            >
                                <span
                                    class="mr-2 text-zinc-500 transition-colors group-hover:text-amber-400"
                                    >&gt;</span
                                >
                                <span>Đánh giá</span>
                            </a>
                        </li>
                        <li>
                            <a
                                href="#privacy"
                                class="group flex items-center transition-colors hover:text-white"
                            >
                                <span
                                    class="mr-2 text-zinc-500 transition-colors group-hover:text-amber-400"
                                    >&gt;</span
                                >
                                <span>Chính sách bảo mật</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Support contact block -->
                <div class="space-y-4">
                    <h3
                        class="font-sans text-sm font-bold tracking-wider text-[#dfb26c] uppercase"
                    >
                        LIÊN HỆ HỖ TRỢ
                    </h3>
                    <ul class="space-y-3.5 text-sm">
                        <li class="flex items-center gap-3">
                            <Phone class="size-4 shrink-0 text-amber-400" />
                            <a
                                href="tel:0346858035"
                                class="transition-colors hover:text-white"
                                >0346 858 035</a
                            >
                        </li>
                        <li class="flex items-center gap-3">
                            <Facebook class="size-4 shrink-0 text-amber-400" />
                            <a
                                href="https://facebook.com"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="transition-colors hover:text-white"
                            >
                                Trang Facebook
                            </a>
                        </li>
                        <li class="flex items-center gap-3">
                            <Clock class="size-4 shrink-0 text-amber-400" />
                            <span>Hỗ trợ 7:00 – 22:00 hàng ngày</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom divider and Copyright -->
            <div class="mt-4 border-t border-white/5 pt-8 text-center">
                <p class="text-xs font-medium tracking-wide text-zinc-500">
                    © 2026 Aventura – Bản quyền thuộc về Aventura. Thiết kế với
                    <span class="mx-0.5 text-[#dfb26c]">💛</span> tại Việt Nam.
                </p>
            </div>
        </div>
    </footer>
</template>
