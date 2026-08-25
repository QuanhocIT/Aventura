import axios from 'axios';
import type { AxiosRequestConfig } from 'axios';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Bọc một lời gọi API kèm sẵn trạng thái tải, thông báo lỗi và nút thử lại.
 *
 * Lý do tồn tại: rà soát ngày 25/08/2026 tìm thấy 41 chỗ trong dự án bắt lỗi
 * bằng `catch (e) { console.error(e) }` — request hỏng thì màn hình đứng im,
 * người dùng không biết nên chờ hay thử lại. Sửa từng chỗ sẽ tái phát ở màn
 * hình tiếp theo; đưa vào đây thì viết đúng trở thành mặc định.
 *
 * Ví dụ:
 *   const analysis = useApiCall<BasketAnalysis>();
 *   analysis.run(() => axios.get('/api/promotions/basket-analysis'));
 *
 *   <div v-if="analysis.isLoading.value">Đang tải…</div>
 *   <ErrorState v-else-if="analysis.error.value" :message="analysis.error.value"
 *               @retry="analysis.retry()" />
 */

type Options = {
    /** Hiện toast khi lỗi. Tắt đi nếu trang đã tự hiển thị khối lỗi riêng. */
    toastOnError?: boolean;
    /** Thông báo mặc định khi không đọc được lý do cụ thể từ server. */
    fallbackMessage?: string;
    /** Thông báo hiện khi thành công. Bỏ trống thì không hiện gì. */
    successMessage?: string;
};

export type ApiCall<T> = {
    data: Ref<T | null>;
    error: Ref<string | null>;
    isLoading: Ref<boolean>;
    /** Đã chạy xong ít nhất một lần (kể cả lỗi) — để phân biệt với "chưa gọi". */
    hasRun: Ref<boolean>;
    run: (task: () => Promise<{ data: T }>) => Promise<T | null>;
    retry: () => Promise<T | null>;
    reset: () => void;
};

/**
 * Rút thông báo dễ hiểu nhất có thể từ lỗi trả về.
 * Ưu tiên lời giải thích của server, rồi tới mã lỗi, cuối cùng mới tới câu chung.
 */
export function apiErrorMessage(err: unknown, fallback: string): string {
    if (axios.isAxiosError(err)) {
        const body = err.response?.data as
            | {
                  message?: string;
                  error?: string;
                  errors?: Record<string, string[]>;
              }
            | undefined;

        if (body?.errors) {
            const first = Object.values(body.errors)[0];

            if (first?.[0]) {
                return first[0];
            }
        }

        if (body?.message) {
            return body.message;
        }

        if (body?.error) {
            return body.error;
        }

        switch (err.response?.status) {
            case 401:
                return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
            case 403:
                return 'Tài khoản của bạn không có quyền thực hiện thao tác này.';
            case 404:
                return 'Không tìm thấy dữ liệu yêu cầu.';
            case 419:
                return 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.';
            case 422:
                return 'Dữ liệu gửi lên không hợp lệ.';
            case 429:
                return 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ít phút.';
            case 500:
            case 502:
            case 503:
                return 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.';
        }

        if (err.code === 'ECONNABORTED') {
            return 'Yêu cầu quá thời gian chờ. Kiểm tra lại đường truyền rồi thử lại.';
        }

        if (!err.response) {
            return 'Không kết nối được máy chủ. Kiểm tra lại đường truyền mạng.';
        }
    }

    if (err instanceof Error && err.message) {
        return err.message;
    }

    return fallback;
}

export function useApiCall<T = unknown>(options: Options = {}): ApiCall<T> {
    const {
        toastOnError = true,
        fallbackMessage = 'Không thực hiện được thao tác. Vui lòng thử lại.',
        successMessage,
    } = options;

    const data = ref(null) as Ref<T | null>;
    const error = ref<string | null>(null);
    const isLoading = ref(false);
    const hasRun = ref(false);

    let lastTask: (() => Promise<{ data: T }>) | null = null;

    const run = async (task: () => Promise<{ data: T }>): Promise<T | null> => {
        lastTask = task;
        isLoading.value = true;
        error.value = null;

        try {
            const response = await task();
            data.value = response.data;

            if (successMessage) {
                toast.success(successMessage);
            }

            return response.data;
        } catch (err) {
            error.value = apiErrorMessage(err, fallbackMessage);

            if (toastOnError) {
                toast.error(error.value);
            }

            return null;
        } finally {
            isLoading.value = false;
            hasRun.value = true;
        }
    };

    const retry = async (): Promise<T | null> => {
        if (!lastTask) {
            return null;
        }

        return run(lastTask);
    };

    const reset = (): void => {
        data.value = null;
        error.value = null;
        isLoading.value = false;
        hasRun.value = false;
        lastTask = null;
    };

    return { data, error, isLoading, hasRun, run, retry, reset };
}

/** Tiện dụng cho lời gọi GET đơn giản. */
export function useApiGet<T = unknown>(options: Options = {}) {
    const call = useApiCall<T>(options);

    return {
        ...call,
        get: (url: string, config?: AxiosRequestConfig) =>
            call.run(() => axios.get<T>(url, config)),
    };
}
