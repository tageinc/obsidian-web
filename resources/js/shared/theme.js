const modes = new Set(['adaptive', 'light', 'dark']);
let stopRuntime;

export function normalizeThemeMode(mode) {
    return modes.has(mode) ? mode : 'adaptive';
}

export function resolveTheme(mode, date = new Date()) {
    const normalized = normalizeThemeMode(mode);
    if (normalized !== 'adaptive') return normalized;
    const hour = date.getHours();
    return hour >= 6 && hour < 18 ? 'light' : 'dark';
}

export function applyThemeMode(mode) {
    const root = document.documentElement;
    const normalized = normalizeThemeMode(mode);
    const theme = resolveTheme(normalized);
    const changed = root.dataset.themeMode !== normalized || root.dataset.theme !== theme;
    root.dataset.themeMode = normalized;
    root.dataset.theme = theme;
    root.dataset.bsTheme = theme;
    root.style.colorScheme = theme;
    if (changed)
        window.dispatchEvent(
            new CustomEvent('obsidian:theme-changed', { detail: { mode: normalized, theme } }),
        );
}

export function startThemeRuntime() {
    stopRuntime?.();
    let timer;
    function cancelTimer() {
        window.clearTimeout(timer);
    }
    function schedule() {
        cancelTimer();
        if (
            document.documentElement.dataset.themeMode !== 'adaptive' ||
            document.visibilityState === 'hidden'
        )
            return;
        const now = new Date();
        const boundary = new Date(now);
        if (now.getHours() < 6) boundary.setHours(6, 0, 0, 0);
        else if (now.getHours() < 18) boundary.setHours(18, 0, 0, 0);
        else {
            boundary.setDate(boundary.getDate() + 1);
            boundary.setHours(6, 0, 0, 0);
        }
        timer = window.setTimeout(refresh, Math.max(1, boundary.getTime() - now.getTime()));
    }
    function refresh() {
        applyThemeMode(document.documentElement.dataset.themeMode);
        schedule();
    }
    function visibilityChanged() {
        if (document.visibilityState === 'hidden') cancelTimer();
        else refresh();
    }
    document.addEventListener('visibilitychange', visibilityChanged);
    window.addEventListener('pageshow', refresh);
    window.addEventListener('pagehide', cancelTimer);
    window.addEventListener('obsidian:theme-changed', schedule);
    refresh();
    const stop = () => {
        cancelTimer();
        document.removeEventListener('visibilitychange', visibilityChanged);
        window.removeEventListener('pageshow', refresh);
        window.removeEventListener('pagehide', cancelTimer);
        window.removeEventListener('obsidian:theme-changed', schedule);
        if (stopRuntime === stop) stopRuntime = null;
    };
    stopRuntime = stop;
    return stop;
}
