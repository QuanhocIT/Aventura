interface TurnstileOptions {
    sitekey?: string;
    callback?: (token: string) => void;
}

interface TurnstileApi {
    render(target: string | HTMLElement, options: TurnstileOptions): string;
}

interface Window {
    turnstile: TurnstileApi;
    onloadTurnstileCallback?: () => void;
    onloadTurnstileCallbackRegister?: () => void;
    onloadTurnstileCallbackForgotPassword?: () => void;
    onloadTurnstileCallbackFeedback?: () => void;
    onloadTurnstileCallbackStorefront?: () => void;
}
