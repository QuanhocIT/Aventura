declare module '@inertiajs/vue3' {
    import type { Component } from 'vue';

    export const Form: Component;
    export const Head: Component;
    export const Link: Component;
    export const router: any;

    export function createInertiaApp(options: any): any;
    export function setLayoutProps(...args: any[]): any;
    export function useForm<T extends Record<string, any> = Record<string, any>>(
        data?: T,
    ): any;
    export function useHttp(...args: any[]): any;
    export function usePage<T = any>(): T;

    export type InertiaLinkProps = Record<string, any>;
}
