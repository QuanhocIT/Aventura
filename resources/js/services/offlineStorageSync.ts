import { ref } from 'vue';

export interface PendingOfflineOrder {
  id: string;
  table_id?: number | null;
  customer_name?: string;
  items: Array<{
    product_id: number;
    name: string;
    quantity: number;
    price: number;
    note?: string;
  }>;
  total_amount: number;
  payment_method: string;
  created_at: string;
  status: 'pending_sync' | 'synced' | 'failed';
}

const STORAGE_KEY_PENDING_ORDERS = 'aventura_pending_offline_orders';
const STORAGE_KEY_MENU_CACHE = 'aventura_menu_catalog_cache';

export const isOnline = ref<boolean>(navigator.onLine);
export const pendingOrderCount = ref<number>(0);
export const isSyncing = ref<boolean>(false);

function loadPendingOrders(): PendingOfflineOrder[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY_PENDING_ORDERS);

    return raw ? JSON.parse(raw) : [];
  } catch (e) {
    console.error('Failed to load pending offline orders:', e);

    return [];
  }
}

function savePendingOrders(orders: PendingOfflineOrder[]): void {
  try {
    localStorage.setItem(STORAGE_KEY_PENDING_ORDERS, JSON.stringify(orders));
    pendingOrderCount.value = orders.filter(o => o.status === 'pending_sync').length;
  } catch (e) {
    console.error('Failed to save pending offline orders:', e);
  }
}

// Initialize count
pendingOrderCount.value = loadPendingOrders().filter(o => o.status === 'pending_sync').length;

// Monitor Network Status
window.addEventListener('online', () => {
  isOnline.value = true;
  syncOfflineOrders();
});

window.addEventListener('offline', () => {
  isOnline.value = false;
});

export function cacheMenuCatalog(menu: any[]): void {
  try {
    localStorage.setItem(STORAGE_KEY_MENU_CACHE, JSON.stringify({
      timestamp: Date.now(),
      data: menu,
    }));
  } catch (e) {
    console.error('Failed to cache menu catalog:', e);
  }
}

export function getCachedMenuCatalog(): any[] | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY_MENU_CACHE);

    if (!raw) {
return null;
}

    const parsed = JSON.parse(raw);

    return parsed.data || null;
  } catch (e) {
    return null;
  }
}

export function storeOfflineOrder(orderData: Omit<PendingOfflineOrder, 'id' | 'created_at' | 'status'>): PendingOfflineOrder {
  const pendingOrders = loadPendingOrders();
  const newOrder: PendingOfflineOrder = {
    ...orderData,
    id: 'OFFLINE_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7),
    created_at: new Date().toISOString(),
    status: 'pending_sync',
  };

  pendingOrders.push(newOrder);
  savePendingOrders(pendingOrders);

  return newOrder;
}

export async function syncOfflineOrders(): Promise<{ synced: number; failed: number }> {
  if (!navigator.onLine || isSyncing.value) {
    return { synced: 0, failed: 0 };
  }

  const pendingOrders = loadPendingOrders();
  const toSync = pendingOrders.filter(o => o.status === 'pending_sync');

  if (toSync.length === 0) {
    return { synced: 0, failed: 0 };
  }

  isSyncing.value = true;
  let synced = 0;
  let failed = 0;

  for (const order of toSync) {
    try {
      const response = await fetch('/api/orders/offline-sync', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
        },
        body: JSON.stringify(order),
      });

      if (response.ok) {
        order.status = 'synced';
        synced++;
      } else {
        order.status = 'failed';
        failed++;
      }
    } catch (e) {
      order.status = 'failed';
      failed++;
    }
  }

  const remaining = pendingOrders.filter(o => o.status !== 'synced');
  savePendingOrders(remaining);
  isSyncing.value = false;

  return { synced, failed };
}
