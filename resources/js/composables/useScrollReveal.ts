import { ref, onMounted, onUnmounted, type Ref } from 'vue';

interface ScrollRevealOptions {
    threshold?: number;
    rootMargin?: string;
    once?: boolean;
}

export function useScrollReveal(
    target: Ref<HTMLElement | null> | string,
    options: ScrollRevealOptions = {},
) {
    const { threshold = 0.1, rootMargin = '0px', once = true } = options;
    const isVisible = ref(false);
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        const el =
            typeof target === 'string'
                ? document.querySelector(target)
                : target.value;

        if (!el) return;

        observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    isVisible.value = true;
                    el.classList.add('revealed');
                    if (once) observer?.unobserve(entry.target);
                } else if (!once) {
                    isVisible.value = false;
                    el.classList.remove('revealed');
                }
            },
            { threshold, rootMargin },
        );

        observer.observe(el);
    });

    onUnmounted(() => {
        observer?.disconnect();
    });

    return { isVisible };
}

export function useScrollRevealAll(
    selector = '.reveal-on-scroll',
    options: ScrollRevealOptions = {},
) {
    const { threshold = 0.1, rootMargin = '0px', once = true } = options;
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        if (once) observer?.unobserve(entry.target);
                    }
                });
            },
            { threshold, rootMargin },
        );

        document.querySelectorAll(selector).forEach((el) => {
            observer?.observe(el);
        });
    });

    onUnmounted(() => {
        observer?.disconnect();
    });
}
