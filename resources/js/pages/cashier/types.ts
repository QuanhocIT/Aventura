export interface OrderItem {
    id?: number;
    product_id: number;
    product_name?: string;
    price: number;
    quantity: number;
    original_quantity?: number;
    max_quantity?: number;
    notes?: string;
    status?: string;
    started_preparing_at?: string | null;
    prepared_at?: string | null;
    served_at?: string | null;
}

export interface TableItem {
    id: number;
    name: string;
    area: string;
    capacity: number;
    status: 'available' | 'occupied' | 'reserved' | 'cleaning' | 'inactive';
    is_payment_requested?: boolean;
    active_order?: {
        id: number;
        order_number: string;
        status: string;
        payment_status: string;
        is_payment_requested?: boolean;
        subtotal: number;
        discount_amount: number;
        total_amount: number;
        note: string;
        is_split: boolean;
        is_red_flagged: boolean;
        items: OrderItem[];
    } | null;
}

export interface ProductItem {
    id: number;
    name: string;
    price: number;
    category_id: number;
    available_portions?: number | null;
    paused_until?: string | null;
    out_of_stock_until?: string | null;
    is_paused?: boolean;
    is_out_of_stock?: boolean;
}

export interface CategoryItem {
    id: number;
    name: string;
}

export interface CustomerItem {
    id: number;
    full_name: string;
    phone: string;
    membership_level?: string;
    loyalty_points?: number;
    is_vip?: boolean;
    is_b2b?: boolean;
    credit_limit?: number;
    current_debt?: number;
}

export type ToastType = 'success' | 'error';

export interface ToastItem {
    id: number;
    message: string;
    type: ToastType;
}
