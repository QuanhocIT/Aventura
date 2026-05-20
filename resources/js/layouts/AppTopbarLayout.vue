<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLogo from '@/components/AppLogo.vue';
import AppearanceToggleInline from '@/components/AppearanceToggleInline.vue';

const page = usePage();
const user = page.props.auth?.user ?? null;
</script>

<template>
    <header class="flex items-center gap-8 px-6 py-4 border-b border-border bg-background">
        <h1 class="font-bold text-lg">Aventura</h1>
        <nav class="flex items-center gap-4 flex-1">
            <a href="#tinh-nang" class="hover:underline px-3 py-2">Tính năng</a>
            <a href="#cach-hoat-dong" class="hover:underline px-3 py-2">Cách hoạt động</a>
            <a href="#bang-gia" class="hover:underline px-3 py-2">Bảng giá</a>
            <div class="flex-1"></div>
            <AppearanceToggleInline />
            <Button v-if="!user" as-child variant="outline">
                <Link href="/login">Đăng nhập</Link>
            </Button>
            <Button v-if="!user" as-child>
                <Link href="/register">Dùng thử miễn phí</Link>
            </Button>
            <Button v-if="user" as-child variant="outline">
                <Link href="/dashboard">Vào hệ thống</Link>
            </Button>
        </nav>
    </header>
    <main>
        <slot />
    </main>
</template>

<style scoped>
main {
  min-height: 80vh;
}
</style>
