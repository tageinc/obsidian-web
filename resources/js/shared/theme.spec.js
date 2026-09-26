import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { applyThemeMode, resolveTheme, startThemeRuntime } from './theme';

let stop;
beforeEach(() => {
    vi.useFakeTimers();
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    document.documentElement.dataset.themeMode = 'adaptive';
    delete document.documentElement.dataset.theme;
    delete document.documentElement.dataset.bsTheme;
});
afterEach(() => {
    stop?.();
    stop = null;
    vi.useRealTimers();
    vi.restoreAllMocks();
    document.documentElement.removeAttribute('data-theme-mode');
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.removeAttribute('data-bs-theme');
    document.documentElement.style.colorScheme = '';
});

it.each([
    [5, 59, 'dark'],
    [6, 0, 'light'],
    [17, 59, 'light'],
    [18, 0, 'dark'],
])('resolves Adaptive at %s:%s using local time', (hour, minute, expected) => {
    expect(resolveTheme('adaptive', new Date(2026, 8, 26, hour, minute))).toBe(expected);
});

it('keeps explicit modes independent of the clock and safely defaults unknown modes', () => {
    expect(resolveTheme('light', new Date(2026, 8, 26, 23))).toBe('light');
    expect(resolveTheme('dark', new Date(2026, 8, 26, 12))).toBe('dark');
    expect(resolveTheme('invalid', new Date(2026, 8, 26, 12))).toBe('light');
});

it('switches at local 06:00 and 18:00 boundaries and announces changes for charts', async () => {
    vi.setSystemTime(new Date(2026, 8, 26, 5, 59, 59));
    const changes = [];
    const listener = (event) => changes.push(event.detail);
    window.addEventListener('obsidian:theme-changed', listener);
    stop = startThemeRuntime();
    expect(document.documentElement.dataset.theme).toBe('dark');
    await vi.advanceTimersByTimeAsync(1000);
    expect(document.documentElement.dataset.theme).toBe('light');
    expect(document.documentElement.dataset.bsTheme).toBe('light');
    await vi.advanceTimersByTimeAsync(12 * 60 * 60 * 1000);
    expect(document.documentElement.dataset.theme).toBe('dark');
    expect(changes).toEqual([
        { mode: 'adaptive', theme: 'dark' },
        { mode: 'adaptive', theme: 'light' },
        { mode: 'adaptive', theme: 'dark' },
    ]);
    window.removeEventListener('obsidian:theme-changed', listener);
});

it('rechecks the local clock on visibility and page restoration and removes timers on cleanup', () => {
    vi.setSystemTime(new Date(2026, 8, 26, 17));
    stop = startThemeRuntime();
    expect(document.documentElement.dataset.theme).toBe('light');
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    document.dispatchEvent(new Event('visibilitychange'));
    expect(vi.getTimerCount()).toBe(0);
    vi.setSystemTime(new Date(2026, 8, 26, 19));
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    document.dispatchEvent(new Event('visibilitychange'));
    expect(document.documentElement.dataset.theme).toBe('dark');
    window.dispatchEvent(new Event('pagehide'));
    expect(vi.getTimerCount()).toBe(0);
    vi.setSystemTime(new Date(2026, 8, 27, 8));
    window.dispatchEvent(new Event('pageshow'));
    expect(document.documentElement.dataset.theme).toBe('light');
    stop();
    expect(vi.getTimerCount()).toBe(0);
    vi.setSystemTime(new Date(2026, 8, 27, 20));
    window.dispatchEvent(new Event('pageshow'));
    expect(document.documentElement.dataset.theme).toBe('light');
});

it('applies a saved explicit mode and cancels Adaptive scheduling without duplicate runtimes', () => {
    vi.setSystemTime(new Date(2026, 8, 26, 12));
    startThemeRuntime();
    stop = startThemeRuntime();
    expect(vi.getTimerCount()).toBe(1);
    applyThemeMode('dark');
    expect(document.documentElement.dataset.themeMode).toBe('dark');
    expect(document.documentElement.dataset.theme).toBe('dark');
    expect(document.documentElement.style.colorScheme).toBe('dark');
    expect(vi.getTimerCount()).toBe(0);
    applyThemeMode('adaptive');
    expect(document.documentElement.dataset.theme).toBe('light');
    expect(vi.getTimerCount()).toBe(1);
});
