import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export interface CartItem {
    product_id: number;
    name: string;
    price: number;
    quantity: number;
    notes: string | null;
}

export const useCartStore = defineStore('cart', () => {
    const items = ref<CartItem[]>([]);
    const note = ref<string>('');
    const tableId = ref<number | null>(null);

    const totalItems = computed(() => {
        return items.value.reduce((total, item) => total + item.quantity, 0);
    });

    const totalAmount = computed(() => {
        return items.value.reduce((total, item) => total + (item.price * item.quantity), 0);
    });

    function addItem(product: { id: number; name: string; price: number }, quantity = 1, notes: string | null = null) {
        const existingItem = items.value.find(item => item.product_id === product.id);

        if (existingItem) {
            existingItem.quantity += quantity;

            if (notes !== null) {
                existingItem.notes = notes;
            }
        } else {
            items.value.push({
                product_id: product.id,
                name: product.name,
                price: product.price,
                quantity,
                notes
            });
        }
    }

    function removeItem(productId: number) {
        items.value = items.value.filter(item => item.product_id !== productId);
    }

    function updateQuantity(productId: number, quantity: number) {
        const item = items.value.find(item => item.product_id === productId);

        if (item) {
            item.quantity = Math.max(0.01, quantity);
        }
    }

    function updateNotes(productId: number, notes: string | null) {
        const item = items.value.find(item => item.product_id === productId);

        if (item) {
            item.notes = notes;
        }
    }

    function setTable(id: number | null) {
        tableId.value = id;
    }

    function clearCart() {
        items.value = [];
        note.value = '';
        tableId.value = null;
    }

    return {
        items,
        note,
        tableId,
        totalItems,
        totalAmount,
        addItem,
        removeItem,
        updateQuantity,
        updateNotes,
        setTable,
        clearCart
    };
});
