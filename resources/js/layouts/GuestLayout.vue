<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Moon, Sun, Menu } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Footer from '@/components/Footer.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useAppearance } from '@/composables/useAppearance';
import { login, register } from '@/routes';

const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleDarkMode() {
    updateAppearance(resolvedAppearance.value === 'light' ? 'dark' : 'light');
}

const navLinks = [
    { label: 'Tính năng', href: '/#features' },
    { label: 'Cách hoạt động', href: '/#how-it-works' },
    { label: 'Bảng giá', href: '/#pricing' },
];
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <!-- NAVBAR -->
        <header
            class="sticky top-0 z-50 border-b border-border/60 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div class="mx-auto flex h-16 max-w-7xl items-center px-4 lg:px-8">
                <!-- Logo -->
                <Link href="/" class="flex shrink-0 items-center gap-2">
                    <AppLogoIcon class="size-7 fill-current text-foreground" />
                    <span class="text-lg font-bold">Aventura</span>
                </Link>

                <!-- Desktop nav links -->
                <nav class="ml-10 hidden items-center gap-6 lg:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <!-- Right side actions -->
                <div class="ml-auto flex items-center gap-2">
                    <!-- Dark mode toggle -->
                    <Button variant="ghost" size="icon" @click="toggleDarkMode">
                        <Sun v-if="resolvedAppearance === 'dark'" class="size-4" />
                        <Moon v-else class="size-4" />
                    </Button>

                    <!-- Login button (desktop) -->
                    <Button as-child variant="outline" class="hidden lg:inline-flex">
                        <Link :href="login()">Đăng nhập</Link>
                    </Button>

                    <!-- Register CTA (desktop) -->
                    <Button as-child class="hidden lg:inline-flex">
                        <Link :href="register()">Dùng thử miễn phí</Link>
                    </Button>

                    <!-- Hamburger (mobile) -->
                    <Sheet>
                        <SheetTrigger as-child>
                            <Button variant="ghost" size="icon" class="lg:hidden">
                                <Menu class="size-5" />
                                <span class="sr-only">Mở menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="w-72 p-6">
                            <SheetTitle class="sr-only">Menu điều hướng</SheetTitle>

                            <!-- Mobile Logo -->
                            <div class="mb-8 flex items-center gap-2">
                                <AppLogoIcon class="size-7 fill-current text-foreground" />
                                <span class="font-bold">Aventura</span>
                            </div>

                            <!-- Mobile nav links -->
                            <nav class="mb-8 flex flex-col gap-4">
                                <a
                                    v-for="link in navLinks"
                                    :key="link.href"
                                    :href="link.href"
                                    class="text-sm font-medium transition-colors hover:text-primary"
                                >
                                    {{ link.label }}
                                </a>
                            </nav>

                            <Separator class="mb-6" />

                            <!-- Mobile CTAs -->
                            <div class="flex flex-col gap-3">
                                <Button as-child variant="outline" class="w-full">
                                    <Link :href="login()">Đăng nhập</Link>
                                </Button>
                                <Button as-child class="w-full">
                                    <Link :href="register()">Dùng thử miễn phí</Link>
                                </Button>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- FOOTER -->
        <Footer />
    </div>
</template>
