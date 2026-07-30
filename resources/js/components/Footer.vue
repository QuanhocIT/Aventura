<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Clock,
    Facebook,
    Phone,
    Star,
    MapPin,
    Mail,
    Building2,
    ShieldCheck,
    FileText,
    ExternalLink,
    ChevronRight,
} from 'lucide-vue-next';
</script>

<template>
    <footer
        class="relative z-10 border-t border-white/10 bg-[#070b14] px-4 pt-16 pb-8 font-sans text-zinc-300"
    >
        <div class="mx-auto max-w-7xl">
            <!-- Main Grid: 4 Cột thông tin chi tiết -->
            <div class="grid grid-cols-1 gap-10 pb-12 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
                <!-- Cột 1: Thương hiệu & Thông tin công ty -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 shadow-lg shadow-amber-500/20">
                            <Star class="size-5 fill-slate-950 text-slate-950" />
                        </div>
                        <span class="font-serif text-2xl font-bold tracking-wide text-white">
                            Aventura
                        </span>
                    </div>
                    <p class="text-xs leading-relaxed text-zinc-400">
                        Nền tảng SaaS quản lý nhà hàng & đa chi nhánh toàn diện. Tối ưu vận hành từ gọi món QR, điều phối bếp/bar, quản lý tồn kho đến phân tích doanh thu bằng AI.
                    </p>
                    <div class="pt-2">
                        <div class="inline-flex items-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-1.5 text-xs font-medium text-amber-400">
                            <ShieldCheck class="size-4 shrink-0" />
                            <span>Bảo mật dữ liệu chuẩn PCI-DSS & SSL 256-bit</span>
                        </div>
                    </div>
                </div>

                <!-- Cột 2: Trụ sở & Địa chỉ chi nhánh -->
                <div class="space-y-4">
                    <h3 class="font-sans text-xs font-bold tracking-wider text-[#dfb26c] uppercase">
                        VĂN PHÒNG & ĐỊA CHỈ
                    </h3>
                    <ul class="space-y-3 text-xs leading-relaxed text-zinc-300">
                        <li class="flex items-start gap-2.5">
                            <MapPin class="mt-0.5 size-4 shrink-0 text-amber-400" />
                            <div>
                                <strong class="text-white block font-medium">Trụ sở chính (TP.HCM):</strong>
                                Tòa nhà Aventura Tower, Số 123 Nam Kỳ Khởi Nghĩa, Phường Bến Thành, Quận 1, TP. Hồ Chí Minh
                            </div>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <Building2 class="mt-0.5 size-4 shrink-0 text-amber-400" />
                            <div>
                                <strong class="text-white block font-medium">Chi nhánh Hà Nội:</strong>
                                Tòa nhà Handico Tower, Đường Phạm Hùng, Phường Mễ Trì, Quận Nam Từ Liêm, Hà Nội
                            </div>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <Building2 class="mt-0.5 size-4 shrink-0 text-amber-400" />
                            <div>
                                <strong class="text-white block font-medium">Chi nhánh Đà Nẵng:</strong>
                                Tòa nhà Software Park, Số 456 Nguyễn Văn Linh, Quận Thanh Khê, TP. Đà Nẵng
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Cột 3: Liên hệ hỗ trợ & Pháp lý -->
                <div class="space-y-4">
                    <h3 class="font-sans text-xs font-bold tracking-wider text-[#dfb26c] uppercase">
                        LIÊN HỆ & HỖ TRỢ
                    </h3>
                    <ul class="space-y-3 text-xs">
                        <li class="flex items-center gap-2.5">
                            <Phone class="size-4 shrink-0 text-amber-400" />
                            <div>
                                <span class="text-zinc-400">Hotline tư vấn (24/7): </span>
                                <a href="tel:0346858035" class="font-semibold text-white transition-colors hover:text-amber-400">
                                    0346 858 035
                                </a>
                            </div>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Mail class="size-4 shrink-0 text-amber-400" />
                            <div>
                                <span class="text-zinc-400">Email: </span>
                                <a href="mailto:support@aventura.vn" class="text-white transition-colors hover:text-amber-400">
                                    support@aventura.vn
                                </a>
                            </div>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Clock class="size-4 shrink-0 text-amber-400" />
                            <span>Giờ làm việc: 7:00 – 22:00 (Thứ 2 – CN)</span>
                        </li>
                        <li class="flex items-center gap-2.5 pt-1">
                            <FileText class="size-4 shrink-0 text-amber-400" />
                            <span>MST: 0317892345 – Cấp bởi Sở KH&ĐT TP.HCM</span>
                        </li>
                    </ul>
                </div>

                <!-- Cột 4: Liên kết nhanh & Giải pháp -->
                <div class="space-y-4">
                    <h3 class="font-sans text-xs font-bold tracking-wider text-[#dfb26c] uppercase">
                        LIÊN KẾT & GIẢI PHÁP
                    </h3>
                    <ul class="space-y-2.5 text-xs">
                        <li>
                            <Link href="/" class="group flex items-center transition-colors hover:text-white">
                                <ChevronRight class="mr-1.5 size-3.5 text-zinc-500 transition-transform group-hover:translate-x-0.5 group-hover:text-amber-400" />
                                <span>Trang chủ Aventura</span>
                            </Link>
                        </li>
                        <li>
                            <a href="#features" class="group flex items-center transition-colors hover:text-white">
                                <ChevronRight class="mr-1.5 size-3.5 text-zinc-500 transition-transform group-hover:translate-x-0.5 group-hover:text-amber-400" />
                                <span>Tính năng quản lý nhà hàng</span>
                            </a>
                        </li>
                        <li>
                            <a href="#pricing" class="group flex items-center transition-colors hover:text-white">
                                <ChevronRight class="mr-1.5 size-3.5 text-zinc-500 transition-transform group-hover:translate-x-0.5 group-hover:text-amber-400" />
                                <span>Bảng giá & Gói dịch vụ</span>
                            </a>
                        </li>
                        <li>
                            <Link href="/status" class="group flex items-center transition-colors hover:text-white">
                                <ChevronRight class="mr-1.5 size-3.5 text-zinc-500 transition-transform group-hover:translate-x-0.5 group-hover:text-amber-400" />
                                <span>Trạng thái hệ thống (Status)</span>
                            </Link>
                        </li>
                        <li>
                            <a href="#privacy" class="group flex items-center transition-colors hover:text-white">
                                <ChevronRight class="mr-1.5 size-3.5 text-zinc-500 transition-transform group-hover:translate-x-0.5 group-hover:text-amber-400" />
                                <span>Chính sách bảo mật & Điều khoản</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Thanh bản quyền & Mạng xã hội ở dưới cùng -->
            <div class="flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-8 sm:flex-row">
                <p class="text-xs font-medium tracking-wide text-zinc-500 text-center sm:text-left">
                    © 2026 Aventura Inc. Bản quyền thuộc về Công ty Cổ phần Công nghệ Aventura Việt Nam.
                </p>
                <div class="flex items-center gap-4 text-zinc-400">
                    <a
                        href="https://facebook.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-900 border border-white/10 transition-colors hover:border-amber-500/50 hover:bg-amber-500/10 hover:text-amber-400"
                        title="Trang Facebook"
                    >
                        <Facebook class="size-4" />
                    </a>
                    <a
                        href="mailto:contact@aventura.vn"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-900 border border-white/10 transition-colors hover:border-amber-500/50 hover:bg-amber-500/10 hover:text-amber-400"
                        title="Gửi Email"
                    >
                        <Mail class="size-4" />
                    </a>
                </div>
            </div>
        </div>
    </footer>
</template>
