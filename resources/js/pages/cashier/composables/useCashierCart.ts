import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { ref, computed } from 'vue';
import type { OrderItem, ProductItem, TableItem } from '../types';

export function useCashierCart(
    activeTable: Ref<TableItem | null>,
    products: () => ProductItem[],
    tablesData: () => TableItem[],
    toast: (msg: string, type?: 'success' | 'error') => void,
) {
    const isCartOpen = ref(false);
    const cartItems = ref<OrderItem[]>([]);
    const cartNote = ref('');
    const cartBounce = ref(false);
    const isNotified = ref(false);
    const isSubmitting = ref(false);

    const triggerCartBounce = () => {
        cartBounce.value = true;
        setTimeout(() => {
            cartBounce.value = false;
        }, 300);
    };

    const addToCart = (product: ProductItem) => {
        isNotified.value = false;
        triggerCartBounce();
        const existing = cartItems.value.find(
            (item) => item.product_id === product.id && !item.id,
        );

        if (existing) {
            existing.quantity += 1;
        } else {
            cartItems.value.push({
                product_id: product.id,
                product_name: product.name,
                price: product.price,
                quantity: 1,
                notes: '',
            });
        }
    };

    const getCartItemQty = (productId: number) => {
        const items = cartItems.value.filter(
            (item) => item.product_id === productId && !item.id,
        );

        return items.reduce((sum, item) => sum + item.quantity, 0);
    };

    const handleProductCardClick = (product: ProductItem) => {
        const qty = getCartItemQty(product.id);

        if (qty === 0) {
            addToCart(product);
        } else {
            increaseProductQty(product.id);
        }
    };

    const increaseProductQty = (productId: number) => {
        isNotified.value = false;
        const item = cartItems.value.find(
            (item) => item.product_id === productId && !item.id,
        );

        if (item) {
            item.quantity += 1;
        } else {
            const product = products().find((p) => p.id === productId);

            if (product) {
                addToCart(product);
            }
        }
    };

    const decreaseProductQty = (productId: number) => {
        isNotified.value = false;
        const itemIndex = cartItems.value.findIndex(
            (i) => i.product_id === productId && !i.id,
        );

        if (itemIndex !== -1) {
            const item = cartItems.value[itemIndex];

            if (item.quantity > 1) {
                item.quantity -= 1;
            } else {
                cartItems.value.splice(itemIndex, 1);
            }
        }
    };

    const increaseQty = (item: OrderItem) => {
        isNotified.value = false;
        item.quantity += 1;
        triggerCartBounce();
    };

    const decreaseQty = (item: OrderItem) => {
        if (item.id) {
            toast('Không thể giảm số lượng món đã gửi bếp.', 'error');

            return;
        }

        isNotified.value = false;

        if (item.quantity > 1) {
            item.quantity -= 1;
        } else {
            removeItem(item);
        }
    };

    const removeItem = (item: OrderItem) => {
        if (item.id) {
            toast('Không thể xóa món đã gửi bếp.', 'error');

            return;
        }

        isNotified.value = false;
        cartItems.value = cartItems.value.filter((i) => i !== item);
    };

    const totalCartAmount = computed(() => {
        return cartItems.value.reduce(
            (sum, item) => sum + item.price * item.quantity,
            0,
        );
    });

    const totalCartQty = computed(() =>
        cartItems.value.reduce((s, i) => s + i.quantity, 0),
    );

    const submitOrder = () => {
        if (isSubmitting.value) {
            return;
        }

        isSubmitting.value = true;

        if (!activeTable.value) {
            toast('Vui lòng chọn một bàn!', 'error');
            isSubmitting.value = false;

            return;
        }

        if (cartItems.value.length === 0) {
            toast('Vui lòng thêm ít nhất một món ăn!', 'error');
            isSubmitting.value = false;

            return;
        }

        const requestData = {
            note: cartNote.value,
            items: cartItems.value.map((item) => ({
                id: item.id || null,
                product_id: item.product_id,
                quantity: item.quantity,
                unit_price: item.price,
                notes: item.notes || '',
            })),
        };

        if (activeTable.value.active_order) {
            router.patch(
                `/orders/${activeTable.value.active_order.id}`,
                requestData,
                {
                    preserveState: true,
                    onSuccess: () => {
                        isNotified.value = true;
                        setTimeout(() => {
                            const updated = tablesData().find(
                                (t) => t.id === activeTable.value!.id,
                            );

                            if (updated) {
                                activeTable.value = updated;
                                cartItems.value =
                                    updated.active_order?.items.map((item) => ({
                                        ...item,
                                    })) ?? [];
                            }
                        }, 200);
                        toast('Đã gửi bổ sung món xuống nhà bếp thành công!');
                    },
                    onError: (errors: any) => {
                        const errorMessage =
                            (Object.values(errors).flat() as string[]).join(
                                ', ',
                            ) || 'Có lỗi xảy ra khi cập nhật đơn hàng!';
                        toast('Lỗi cập nhật đơn: ' + errorMessage, 'error');
                    },
                    onFinish: () => {
                        isSubmitting.value = false;
                    },
                },
            );
        } else {
            router.post(
                '/orders',
                { table_id: activeTable.value.id, ...requestData },
                {
                    preserveState: true,
                    onSuccess: () => {
                        isNotified.value = true;
                        setTimeout(() => {
                            const updated = tablesData().find(
                                (t) => t.id === activeTable.value!.id,
                            );

                            if (updated) {
                                activeTable.value = updated;
                                cartItems.value =
                                    updated.active_order?.items.map((item) => ({
                                        ...item,
                                    })) ?? [];
                            }
                        }, 200);
                        toast('Đã tạo đơn mới thành công!');
                    },
                    onError: (errors: any) => {
                        const errorMessage =
                            (Object.values(errors).flat() as string[]).join(
                                ', ',
                            ) || 'Có lỗi xảy ra khi tạo đơn hàng!';
                        toast('Lỗi tạo đơn: ' + errorMessage, 'error');
                    },
                    onFinish: () => {
                        isSubmitting.value = false;
                    },
                },
            );
        }
    };

    const sendToKitchen = () => {
        if (!activeTable.value?.active_order) {
            return;
        }

        router.patch(
            `/orders/${activeTable.value.active_order.id}/status`,
            { status: 'confirmed' },
            {
                onSuccess: () => {
                    isCartOpen.value = false;
                    toast('Đơn hàng đã đẩy xuống bếp và khóa thành công!');
                },
            },
        );
    };

    return {
        isCartOpen,
        cartItems,
        cartNote,
        cartBounce,
        isNotified,
        isSubmitting,
        totalCartAmount,
        totalCartQty,
        addToCart,
        getCartItemQty,
        handleProductCardClick,
        increaseProductQty,
        decreaseProductQty,
        increaseQty,
        decreaseQty,
        removeItem,
        submitOrder,
        sendToKitchen,
    };
}
