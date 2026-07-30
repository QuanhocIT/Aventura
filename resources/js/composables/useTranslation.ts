import { usePage } from '@inertiajs/vue3';

const translations: Record<string, Record<string, string>> = {};

export function useTranslation() {
    const page = usePage();
    const locale = (page.props as any).locale ?? 'vi';

    function t(key: string, replacements: Record<string, string> = {}): string {
        const value = translations[locale]?.[key] ?? key;

        return Object.entries(replacements).reduce(
            (str, [k, v]) => str.replace(`:${k}`, v),
            value,
        );
    }

    function setTranslations(lang: string, data: Record<string, string>) {
        translations[lang] = { ...translations[lang], ...data };
    }

    return { t, locale, setTranslations };
}
