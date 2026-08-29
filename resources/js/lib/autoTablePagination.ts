const PAGE_SIZE = 10;

type TableState = {
    page: number;
    totalRows: number;
    totalPages: number;
    controls: HTMLDivElement | null;
    summary: HTMLSpanElement | null;
    navigation: HTMLDivElement | null;
};

const states = new WeakMap<HTMLTableElement, TableState>();

function getRows(table: HTMLTableElement): HTMLTableRowElement[] {
    return Array.from(table.tBodies).flatMap((tbody) =>
        Array.from(tbody.rows).filter((row) => row.closest('table') === table),
    );
}

function hasManagedPagination(table: HTMLTableElement): boolean {
    let current: HTMLElement | null = table;
    let depth = 0;

    while (current && depth < 6) {
        if (
            current.dataset.paginationControls ||
            current.querySelector('[data-pagination-controls]')
        ) {
            return true;
        }

        current = current.parentElement;
        depth += 1;
    }

    return false;
}

function createButton(label: string, page: number, disabled = false) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.page = String(page);
    button.textContent = label;
    button.disabled = disabled;
    button.className =
        'inline-flex min-w-7 items-center justify-center rounded-lg border border-border/60 px-2 py-1.5 text-xs font-medium transition-colors hover:bg-muted/60 disabled:pointer-events-none disabled:opacity-40';

    return button;
}

function pageNumbers(page: number, totalPages: number): number[] {
    if (totalPages <= 7) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const first = Math.max(2, Math.min(page - 1, totalPages - 4));
    const numbers = [1, first, first + 1, first + 2, totalPages];

    return [...new Set(numbers)].sort((a, b) => a - b);
}

function updateNavigation(state: TableState) {
    if (!state.navigation) {
        return;
    }

    const renderedPage = state.navigation.dataset.renderedPage;
    const renderedTotalPages = state.navigation.dataset.renderedTotalPages;

    if (
        renderedPage === String(state.page) &&
        renderedTotalPages === String(state.totalPages)
    ) {
        return;
    }

    state.navigation.replaceChildren();
    state.navigation.appendChild(
        createButton('‹ Trước', state.page - 1, state.page === 1),
    );

    let previous = 0;

    for (const page of pageNumbers(state.page, state.totalPages)) {
        if (previous && page - previous > 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-1 text-muted-foreground';
            ellipsis.textContent = '…';
            state.navigation.appendChild(ellipsis);
        }

        const button = createButton(String(page), page);

        if (page === state.page) {
            button.className =
                'inline-flex min-w-7 items-center justify-center rounded-lg border border-primary bg-primary px-2 py-1.5 text-xs font-semibold text-primary-foreground';
        }

        state.navigation.appendChild(button);
        previous = page;
    }

    state.navigation.appendChild(
        createButton('Sau ›', state.page + 1, state.page === state.totalPages),
    );
    state.navigation.dataset.renderedPage = String(state.page);
    state.navigation.dataset.renderedTotalPages = String(state.totalPages);
}

function ensureControls(table: HTMLTableElement, state: TableState) {
    if (state.totalPages <= 1) {
        state.controls?.remove();
        state.controls = null;
        state.summary = null;
        state.navigation = null;

        return;
    }

    if (!state.controls) {
        const controls = document.createElement('div');
        controls.dataset.autoTablePagination = 'true';
        controls.className =
            'flex flex-wrap items-center justify-between gap-3 border-t border-border/60 px-4 py-3';

        const summary = document.createElement('span');
        summary.className = 'text-[11px] font-medium text-muted-foreground';

        const navigation = document.createElement('div');
        navigation.className = 'flex flex-wrap items-center gap-1';
        navigation.addEventListener('click', (event) => {
            const target = event.target as HTMLElement | null;
            const button =
                target?.closest<HTMLButtonElement>('button[data-page]');
            const nextPage = Number(button?.dataset.page);

            if (!button || button.disabled || !Number.isInteger(nextPage)) {
                return;
            }

            const latest = states.get(table);

            if (!latest) {
                return;
            }

            latest.page = Math.max(1, Math.min(nextPage, latest.totalPages));
            applyTable(table);
        });

        controls.append(summary, navigation);
        table.parentElement?.insertBefore(controls, table.nextSibling);
        state.controls = controls;
        state.summary = summary;
        state.navigation = navigation;
    }

    const first = (state.page - 1) * PAGE_SIZE + 1;
    const last = Math.min(state.page * PAGE_SIZE, state.totalRows);
    const summaryText = `Hiển thị ${first}–${last} / ${state.totalRows} dòng`;

    if (state.summary!.textContent !== summaryText) {
        state.summary!.textContent = summaryText;
    }

    updateNavigation(state);
}

function applyTable(table: HTMLTableElement) {
    const rows = getRows(table);
    const state = states.get(table) ?? {
        page: 1,
        totalRows: 0,
        totalPages: 1,
        controls: null,
        summary: null,
        navigation: null,
    };

    if (hasManagedPagination(table) || rows.length <= PAGE_SIZE) {
        rows.forEach((row) => {
            row.style.removeProperty('display');
        });
        state.totalPages = 1;
        state.totalRows = rows.length;
        state.page = 1;
        state.controls?.remove();
        state.controls = null;
        states.set(table, state);

        return;
    }

    state.totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
    state.totalRows = rows.length;
    state.page = Math.max(1, Math.min(state.page, state.totalPages));
    states.set(table, state);

    const start = (state.page - 1) * PAGE_SIZE;
    rows.forEach((row, index) => {
        row.style.display =
            index >= start && index < start + PAGE_SIZE ? '' : 'none';
    });

    ensureControls(table, state);
}

let scanScheduled = false;

function scanTables() {
    scanScheduled = false;

    if (!document.body) {
        return;
    }

    document.querySelectorAll<HTMLTableElement>('table').forEach(applyTable);
}

function scheduleScan() {
    if (scanScheduled) {
        return;
    }

    scanScheduled = true;
    requestAnimationFrame(scanTables);
}

export function initializeAutoTablePagination() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const start = () => {
        scanTables();
        new MutationObserver(scheduleScan).observe(document.body, {
            childList: true,
            subtree: true,
        });
    };

    if (document.body) {
        start();
    } else {
        window.addEventListener('DOMContentLoaded', start, { once: true });
    }
}
