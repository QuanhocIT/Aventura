import { defineStore } from 'pinia';

// ── Types ────────────────────────────────────────────────────────────────────

interface AuthUser {
    id: number;
    name: string;
    email: string;
    restaurant_id: number | null;
    roles: string[];
    permissions: string[];
}

// ── Store ────────────────────────────────────────────────────────────────────

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null as AuthUser | null,
        permissions: [] as string[],
        roles: [] as string[],
        restaurantId: null as number | null,
    }),

    getters: {
        isAuthenticated: (state) => state.user !== null,

        /** Kiểm tra quyền cụ thể */
        can: (state) => (permission: string) =>
            state.permissions.includes(permission),

        /** Kiểm tra role cụ thể */
        hasRole: (state) => (role: string) =>
            state.roles.includes(role),

        /** Bất kỳ role nào trong danh sách */
        hasAnyRole: (state) => (roles: string[]) =>
            roles.some((r) => state.roles.includes(r)),

        isOwner: (state) => state.roles.includes('owner'),
        isManager: (state) => state.roles.includes('manager'),
        isStaff: (state) => state.roles.includes('staff'),
    },

    actions: {
        setUser(user: AuthUser) {
            this.user = user;
            this.permissions = user.permissions ?? [];
            this.roles = user.roles ?? [];
            this.restaurantId = user.restaurant_id ?? null;
        },

        clearUser() {
            this.user = null;
            this.permissions = [];
            this.roles = [];
            this.restaurantId = null;
        },
    },
});
