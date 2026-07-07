import { reactive } from 'vue';

/**
 * Dialog xác nhận toàn cục thay cho window.confirm() — dùng:
 *
 *   if (!(await confirmDialog({ title: 'Xóa thiết bị?', description: '...' }))) return;
 *
 * <ConfirmDialog /> được mount một lần trong AppSidebarLayout nên mọi trang
 * trong app dùng được ngay. Trang standalone (storefront...) tự mount thêm.
 */

export interface ConfirmOptions {
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    /** destructive: nút xác nhận màu đỏ (mặc định), default: màu primary */
    variant?: 'destructive' | 'default';
}

interface ConfirmState extends ConfirmOptions {
    open: boolean;
    resolve: ((value: boolean) => void) | null;
}

export const confirmState = reactive<ConfirmState>({
    open: false,
    title: '',
    description: '',
    confirmText: '',
    cancelText: '',
    variant: 'destructive',
    resolve: null,
});

export function confirmDialog(options: ConfirmOptions): Promise<boolean> {
    // Nếu đang có dialog mở (hiếm), coi như từ chối dialog cũ
    if (confirmState.resolve) {
        confirmState.resolve(false);
    }

    confirmState.title = options.title;
    confirmState.description = options.description ?? '';
    confirmState.confirmText = options.confirmText ?? 'Xác nhận';
    confirmState.cancelText = options.cancelText ?? 'Hủy';
    confirmState.variant = options.variant ?? 'destructive';
    confirmState.open = true;

    return new Promise<boolean>((resolve) => {
        confirmState.resolve = resolve;
    });
}

export function resolveConfirm(value: boolean): void {
    confirmState.open = false;
    confirmState.resolve?.(value);
    confirmState.resolve = null;
}
