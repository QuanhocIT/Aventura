<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Moon, Sun, Menu } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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
    { label: 'TÃ­nh nÄƒng', href: '#tinh-nang' },
    { label: 'CÃ¡ch hoáº¡t Ä‘á»™ng', href: '#cach-hoat-dong' },
    { label: 'Báº£ng giÃ¡', href: '#bang-gia' },
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
                        <Link :href="login()">ÄÄƒng nháº­p</Link>
                    </Button>

                    <!-- Register CTA (desktop) -->
                    <Button as-child class="hidden lg:inline-flex">
                        <Link :href="register()">DÃ¹ng thá»­ miá»…n phÃ­</Link>
                    </Button>

                    <!-- Hamburger (mobile) -->
                    <Sheet>
                        <SheetTrigger as-child>
                            <Button variant="ghost" size="icon" class="lg:hidden">
                                <Menu class="size-5" />
                                <span class="sr-only">Má»Ÿ menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="w-72 p-6">
                            <SheetTitle class="sr-only">Menu Ä‘iá»u hÆ°á»›ng</SheetTitle>

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
                                    <Link :href="login()">ÄÄƒng nháº­p</Link>
                                </Button>
                                <Button as-child class="w-full">
                                    <Link :href="register()">DÃ¹ng thá»­ miá»…n phÃ­</Link>
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
        <footer class="border-t border-border bg-muted/30 py-12">
            <div class="mx-auto max-w-7xl px-4 lg:px-8">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <!-- Brand -->
                    <div>
                        <div class="mb-3 flex items-center gap-2">
                            <AppLogoIcon class="size-6 fill-current text-foreground" />
                            <span class="font-bold">Aventura</span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Ná»n táº£ng quáº£n lÃ½ nhÃ  hÃ ng SaaS hiá»‡n Ä‘áº¡i, giÃºp tá»‘i Æ°u váº­n hÃ nh vÃ  tÄƒng trÆ°á»Ÿng doanh thu.
                        </p>
                    </div>

                    <!-- Links -->
                    <div>
                        <p class="mb-3 text-sm font-semibold">Sáº£n pháº©m</p>
                        <ul class="space-y-2">
                            <li>
                                <a href="#tinh-nang" class="text-sm text-muted-foreground hover:text-foreground">
                                    TÃ­nh nÄƒng
                                </a>
                            </li>
                            <li>
                                <a href="#bang-gia" class="text-sm text-muted-foreground hover:text-foreground">
                                    Báº£ng giÃ¡
                                </a>
                            </li>
                            <li>
                                <Link :href="login()" class="text-sm text-muted-foreground hover:text-foreground">
                                    ÄÄƒng nháº­p
                                </Link>
                            </li>
                            <li>
                                <Link :href="register()" class="text-sm text-muted-foreground hover:text-foreground">
                                    ÄÄƒng kÃ½
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Contact -->
                    <div>
                        <p class="mb-3 text-sm font-semibold">LiÃªn há»‡</p>
                        <ul class="space-y-2">
                            <li class="text-sm text-muted-foreground">support@aventura.vn</li>
                            <li class="text-sm text-muted-foreground">1800 000 000 (Miá»…n phÃ­)</li>
                            <li class="text-sm text-muted-foreground">Thá»© 2 â€“ Thá»© 7, 8:00 â€“ 18:00</li>
                        </ul>
                    </div>
                </div>

                <Separator class="my-8" />

                <p class="text-center text-xs text-muted-foreground">Â© 2026 Aventura. Báº£n quyá»n thuá»™c vá» Aventura.</p>
            </div>
        </footer>
    </div>
</template>
