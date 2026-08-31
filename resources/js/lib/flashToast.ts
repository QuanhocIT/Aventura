import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

type InertiaResponse = {
    status?: number;
    data?: string;
};

type InertiaErrorPayload = {
    errors?: Record<string, unknown>;
    message?: string;
    error?: string;
};

function parseResponseData(data: string | undefined): InertiaErrorPayload {
    if (!data) {
        return {};
    }

    try {
        const parsed = JSON.parse(data) as unknown;

        return parsed && typeof parsed === 'object'
            ? (parsed as InertiaErrorPayload)
            : {};
    } catch {
        return {};
    }
}

function errorMessages(errors: Record<string, unknown>): string[] {
    return Object.values(errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter((value): value is string => typeof value === 'string')
        .map((value) => value.trim())
        .filter(Boolean);
}

function httpErrorMessage(response: InertiaResponse): string {
    const body = parseResponseData(response.data);
    const validationMessage = body.errors ? errorMessages(body.errors)[0] : '';

    if (validationMessage) {
        return validationMessage;
    }

    if (body.message || body.error) {
        return body.message || body.error || 'Không thể hoàn thành yêu cầu.';
    }

    switch (response.status) {
        case 401:
            return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
        case 403:
            return 'Tài khoản của bạn không có quyền thực hiện thao tác này.';
        case 404:
            return 'Không tìm thấy dữ liệu yêu cầu.';
        case 419:
            return 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.';
        case 429:
            return 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ít phút.';
        case 500:
        case 502:
        case 503:
            return 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.';
        default:
            return 'Không thể hoàn thành yêu cầu. Vui lòng thử lại.';
    }
}

export function initializeFlashToast(): void {
    router.on('flash', (event: Event) => {
        const flash = (event as CustomEvent<{ flash?: { toast?: FlashToast } }>)
            .detail?.flash;
        const data = flash?.toast;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    // Hiển thị lỗi validation chung để form không có khối lỗi riêng vẫn phản hồi.
    router.on('error', (event: Event) => {
        const errors = (
            event as CustomEvent<{ errors?: Record<string, unknown> }>
        ).detail?.errors;
        const messages = errors ? errorMessages(errors) : [];

        if (messages.length > 0) {
            toast.error(messages.slice(0, 3).join(' • '));
        }
    });

    router.on('httpException', (event: Event) => {
        const response = (event as CustomEvent<{ response?: InertiaResponse }>)
            .detail?.response;

        if (response) {
            const body = parseResponseData(response.data);

            // Ca hết hạn có modal xử lý riêng trong app.ts.
            if (body.error === 'SHIFT_EXPIRED') {
                return;
            }

            toast.error(httpErrorMessage(response));
        }
    });

    router.on('networkError', (event: Event) => {
        const error = (event as CustomEvent<{ error?: Error }>).detail?.error;

        toast.error(
            error?.message ||
                'Không kết nối được máy chủ. Kiểm tra mạng rồi thử lại.',
        );
    });
}
