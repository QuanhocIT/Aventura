declare function defineOptions(options: Record<string, unknown>): void;
declare function defineProps<T = Record<string, unknown>>(): T;
declare function withDefaults<T, B>(props: T, defaults: B): T & B;
