<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Facebook, Phone, Star } from 'lucide-vue-next';
</script>

<template>
    <footer class="relative z-10 border-t border-white/5 bg-[#090e1a] px-4 pt-16 pb-8 text-zinc-300 font-sans">
        <div class="mx-auto max-w-7xl">
            <!-- Main grid -->
            <div class="grid grid-cols-1 gap-12 md:grid-cols-3 md:gap-8 pb-12">
                
                <!-- Brand block -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <Star class="size-5 fill-amber-400 text-amber-400 shrink-0" />
                        <span class="font-serif text-2xl font-bold text-white tracking-wide">DuLichPro</span>
                    </div>
                    <p class="text-sm leading-relaxed text-zinc-400 max-w-sm">
                        Hành trình đẳng cấp – trải nghiệm đích thực. Chúng tôi đồng hành cùng bạn trên mọi nẻo đường.
                    </p>
                </div>

                <!-- Quick links block -->
                <div class="space-y-4">
                    <h3 class="font-sans font-bold text-[#dfb26c] tracking-wider uppercase text-sm">
                        LIÊN KẾT NHANH
                    </h3>
                    <ul class="space-y-2.5 text-sm">
                        <li>
                            <Link href="/" class="group flex items-center hover:text-white transition-colors">
                                <span class="text-zinc-500 mr-2 group-hover:text-amber-400 transition-colors">&gt;</span>
                                <span>Trang chủ</span>
                            </Link>
                        </li>
                        <li>
                            <a href="#features" class="group flex items-center hover:text-white transition-colors">
                                <span class="text-zinc-500 mr-2 group-hover:text-amber-400 transition-colors">&gt;</span>
                                <span>Tour nổi bật</span>
                            </a>
                        </li>
                        <li>
                            <a href="#testimonials" class="group flex items-center hover:text-white transition-colors">
                                <span class="text-zinc-500 mr-2 group-hover:text-amber-400 transition-colors">&gt;</span>
                                <span>Đánh giá</span>
                            </a>
                        </li>
                        <li>
                            <a href="#privacy" class="group flex items-center hover:text-white transition-colors">
                                <span class="text-zinc-500 mr-2 group-hover:text-amber-400 transition-colors">&gt;</span>
                                <span>Chính sách bảo mật</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Support contact block -->
                <div class="space-y-4">
                    <h3 class="font-sans font-bold text-[#dfb26c] tracking-wider uppercase text-sm">
                        LIÊN HỆ HỖ TRỢ
                    </h3>
                    <ul class="space-y-3.5 text-sm">
                        <li class="flex items-center gap-3">
                            <Phone class="size-4 text-amber-400 shrink-0" />
                            <a href="tel:0346858035" class="hover:text-white transition-colors">0346 858 035</a>
                        </li>
                        <li class="flex items-center gap-3">
                            <Facebook class="size-4 text-amber-400 shrink-0" />
                            <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="hover:text-white transition-colors">
                                Trang Facebook
                            </a>
                        </li>
                        <li class="flex items-center gap-3">
                            <Clock class="size-4 text-amber-400 shrink-0" />
                            <span>Hỗ trợ 7:00 – 22:00 hàng ngày</span>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- Bottom divider and Copyright -->
            <div class="border-t border-white/5 pt-8 mt-4 text-center">
                <p class="text-xs text-zinc-500 font-medium tracking-wide">
                    © 2026 DuLichPro – Bản quyền thuộc về DuLichPro. Thiết kế với <span class="text-[#dfb26c] mx-0.5">💛</span> tại Việt Nam.
                </p>
            </div>
        </div>
    </footer>
</template>
