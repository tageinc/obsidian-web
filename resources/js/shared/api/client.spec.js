import { beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import { requestJson } from './client';
vi.mock('axios', () => ({
    default: { request: vi.fn(), isCancel: (error) => error?.code === 'ERR_CANCELED' },
}));
beforeEach(() => vi.clearAllMocks());
it('uses same-origin cookies, CSRF and JSON; never requests an external origin', async () => {
    axios.request.mockResolvedValue({
        data: { ok: true },
        headers: { 'content-type': 'application/json' },
    });
    expect(await requestJson('/status', { csrfToken: 'transient' })).toEqual({ ok: true });
    expect(axios.request).toHaveBeenCalledWith(
        expect.objectContaining({
            withCredentials: true,
            headers: expect.objectContaining({ 'X-CSRF-TOKEN': 'transient' }),
        }),
    );
    await expect(requestJson('https://external.invalid/')).rejects.toThrow('stay on this site');
    expect(axios.request).toHaveBeenCalledTimes(1);
});
it.each([401, 403, 404, 410, 419, 422, 429, 500])(
    'normalizes %s without exposing server details or retrying',
    async (status) => {
        axios.request.mockRejectedValue({
            response: {
                status,
                data: { message: 'private stack trace', errors: { name: ['Required'] } },
                headers: {},
            },
        });
        await expect(requestJson('/status')).rejects.toMatchObject({
            status,
            fieldErrors: status === 422 ? { name: ['Required'] } : {},
        });
        expect(axios.request).toHaveBeenCalledTimes(1);
    },
);
it('detects redirected login HTML and preserves cancellation', async () => {
    axios.request.mockResolvedValue({ data: '<html>', headers: { 'content-type': 'text/html' } });
    await expect(requestJson('/status')).rejects.toMatchObject({ status: 419 });
    axios.request.mockRejectedValue({ code: 'ERR_CANCELED' });
    await expect(requestJson('/status')).rejects.toMatchObject({ name: 'AbortError' });
});
