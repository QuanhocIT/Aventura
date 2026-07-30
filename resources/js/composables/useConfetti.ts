/**
 * Bắn confetti nhẹ không cần thư viện — dùng cho khoảnh khắc "thành công"
 * (đặt hàng xong, hoàn thành mục tiêu...). Tự dọn DOM sau khi rơi xong,
 * tôn trọng prefers-reduced-motion.
 *
 *   import { fireConfetti } from '@/composables/useConfetti';
 *   fireConfetti();
 */

const COLORS = [
    '#f97316',
    '#fbbf24',
    '#34d399',
    '#60a5fa',
    '#f472b6',
    '#a78bfa',
];

export function fireConfetti(count = 90): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const container = document.createElement('div');
    container.style.cssText =
        'position:fixed;inset:0;pointer-events:none;z-index:9999;overflow:hidden;';
    document.body.appendChild(container);

    for (let i = 0; i < count; i++) {
        const piece = document.createElement('span');
        const size = 6 + Math.random() * 6;
        const left = Math.random() * 100;
        const delay = Math.random() * 250;
        const duration = 1800 + Math.random() * 1400;
        const rotate = Math.random() * 720 - 360;
        const drift = (Math.random() - 0.5) * 40;
        const color = COLORS[i % COLORS.length];
        const round = Math.random() > 0.5;

        piece.style.cssText = `
            position:absolute;top:-3vh;left:${left}vw;
            width:${size}px;height:${round ? size : size * 0.45}px;
            background:${color};border-radius:${round ? '50%' : '2px'};
            opacity:0.95;will-change:transform,opacity;
        `;

        piece.animate(
            [
                { transform: 'translate3d(0,0,0) rotate(0deg)', opacity: 1 },
                {
                    transform: `translate3d(${drift}vw,105vh,0) rotate(${rotate}deg)`,
                    opacity: 0.6,
                },
            ],
            {
                duration,
                delay,
                easing: 'cubic-bezier(0.25, 0.6, 0.45, 1)',
                fill: 'forwards',
            },
        );

        container.appendChild(piece);
    }

    setTimeout(() => container.remove(), 4000);
}
