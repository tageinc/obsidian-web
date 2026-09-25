import { afterEach, expect, it, vi } from 'vitest';
import { downloadHistoryReport } from './historyReport';

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

it('downloads a PDF with explicit UTC bounds and the server filename', async () => {
    vi.useFakeTimers();
    const blob = new Blob(['%PDF-1.7'], { type: 'application/pdf' });
    const fetchMock = vi.fn().mockResolvedValue({
        ok: true,
        headers: new Headers({
            'content-type': 'application/pdf',
            'content-disposition': 'attachment; filename="tracker-report.pdf"',
        }),
        blob: () => Promise.resolve(blob),
    });
    vi.stubGlobal('fetch', fetchMock);
    class DownloadURL extends URL {
        static createObjectURL = vi.fn(() => 'blob:test-report');
        static revokeObjectURL = vi.fn();
    }
    vi.stubGlobal('URL', DownloadURL);
    let download;
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
        download = { href: this.href, filename: this.download };
    });
    const controller = new AbortController();
    await downloadHistoryReport('/devices/7/report', { start: 0, end: 7200000 }, controller.signal);
    const [address, options] = fetchMock.mock.calls[0];
    const url = new URL(address);
    expect(url.pathname).toBe('/devices/7/report');
    expect(url.searchParams.get('from')).toBe('1970-01-01T00:00:00.000Z');
    expect(url.searchParams.get('to')).toBe('1970-01-01T02:00:00.000Z');
    expect(options.credentials).toBe('same-origin');
    expect(options.signal).toBe(controller.signal);
    expect(download).toEqual({ href: 'blob:test-report', filename: 'tracker-report.pdf' });
    vi.runAllTimers();
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:test-report');
});

it('rejects redirected login pages and authorization errors rather than downloading them', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce({ ok: true, headers: new Headers({ 'content-type': 'text/html' }) })
        .mockResolvedValueOnce({ ok: false, status: 403 });
    vi.stubGlobal('fetch', fetchMock);
    await expect(downloadHistoryReport('/devices/7/report', { start: 0, end: 1 })).rejects.toThrow(
        'session may have expired',
    );
    await expect(downloadHistoryReport('/devices/7/report', { start: 0, end: 1 })).rejects.toThrow(
        'do not have access',
    );
    await expect(
        downloadHistoryReport('https://outside.example/report', { start: 0, end: 1 }),
    ).rejects.toThrow('must stay on this site');
    expect(fetchMock).toHaveBeenCalledTimes(2);
});
