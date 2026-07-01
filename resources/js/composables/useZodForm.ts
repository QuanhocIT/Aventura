import { useForm } from '@inertiajs/vue3';
import type { ZodType } from 'zod';

type SubmitMethod = 'post' | 'put' | 'patch' | 'delete';

/**
 * Thin wrapper around Inertia's useForm() that runs a Zod schema before submitting,
 * so obviously-invalid input (empty required field, wrong type, out-of-range number)
 * gets caught instantly client-side instead of round-tripping to the server first.
 * The server-side Form Request/validate() rules remain the source of truth — this is
 * a fast pre-check, not a replacement for backend validation.
 */
export function useZodForm<TForm extends Record<string, any>>(schema: ZodType<any>, initialData: TForm) {
    const form = useForm<TForm>(initialData);

    function validate(): boolean {
        const result = schema.safeParse(form.data());

        form.clearErrors();

        if (!result.success) {
            for (const issue of result.error.issues) {
                const field = issue.path.join('.');

                if (field && !(form.errors as Record<string, string>)[field]) {
                    form.setError(field as never, issue.message);
                }
            }

            return false;
        }

        return true;
    }

    function submit(method: SubmitMethod, url: string, options: Record<string, unknown> = {}) {
        if (!validate()) {
            return;
        }

        (form[method] as (url: string, options?: Record<string, unknown>) => void)(url, options);
    }

    return { form, validate, submit };
}
