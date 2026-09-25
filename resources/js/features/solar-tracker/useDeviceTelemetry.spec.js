import { defineComponent, ref } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { requestJson } from '../../shared/api/client';
import { useDeviceTelemetry } from './useDeviceTelemetry';

vi.mock('../../shared/api/client', () => ({ requestJson: vi.fn() }));
let page;
let isHidden;
beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'] });
    vi.setSystemTime(new Date('2026-09-25T18:00:00Z'));
    requestJson.mockReset();
    isHidden = false;
    vi.spyOn(document, 'hidden', 'get').mockImplementation(() => isHidden);
});
afterEach(() => {
    page?.unmount();
    vi.restoreAllMocks();
    vi.useRealTimers();
});
function mountPolling() {
    const url = ref('/devices/1/telemetry');
    const deviceKey = ref('TEST-1');
    const initialStatus = ref({ Updated: 'Earlier', PS1: 10 });
    const initialPoints = ref([{ id: 1, epoch_ms: 1, temp: 2 }]);
    let live;
    page = mount(
        defineComponent({
            setup() {
                live = useDeviceTelemetry({
                    url: () => url.value,
                    deviceKey: () => deviceKey.value,
                    initialStatus: () => initialStatus.value,
                    initialPoints: () => initialPoints.value,
                });
                return () => null;
            },
        }),
    );
    return { live, url, deviceKey, initialStatus, initialPoints };
}
function snapshot(id = 2) {
    return {
        status: { Updated: 'Now', PS1: 20 },
        graph: { points: [{ id, epoch_ms: Date.now(), temp: 3 }] },
        latest_reading: { id, epoch_ms: Date.now() },
    };
}
async function visibility(hidden) {
    isHidden = hidden;
    document.dispatchEvent(new Event('visibilitychange'));
    await vi.advanceTimersByTimeAsync(0);
}

it('refreshes both snapshots every minute and marks successful empty or unchanged reads as fresh', async () => {
    const update = snapshot();
    requestJson.mockResolvedValue(update);
    const { live } = mountPolling();
    expect(requestJson).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(59999);
    expect(requestJson).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(1);
    expect(requestJson).toHaveBeenCalledWith('/devices/1/telemetry', {
        signal: expect.any(AbortSignal),
    });
    expect(live.status.value).toEqual(update.status);
    expect(live.points.value).toEqual(update.graph.points);
    const first = live.refreshedAt.value;
    await vi.advanceTimersByTimeAsync(60000);
    expect(live.refreshedAt.value).toBe(first + 60000);
    requestJson.mockResolvedValue({ status: { Updated: 'N/A' }, graph: { points: [] } });
    await vi.advanceTimersByTimeAsync(60000);
    expect(live.points.value).toEqual([]);
    expect(live.status.value.Updated).toBe('N/A');
});

it('never overlaps requests and aborts then ignores a response after leaving the device', async () => {
    let finish;
    requestJson.mockImplementation(() => new Promise((resolve) => (finish = resolve)));
    const { live } = mountPolling();
    await vi.advanceTimersByTimeAsync(180000);
    expect(requestJson).toHaveBeenCalledTimes(1);
    const signal = requestJson.mock.calls[0][1].signal;
    page.unmount();
    expect(signal.aborted).toBe(true);
    finish(snapshot());
    await flushPromises();
    expect(live.status.value.PS1).toBe(10);
    expect(live.refreshedAt.value).toBe(null);
    await vi.advanceTimersByTimeAsync(180000);
    expect(requestJson).toHaveBeenCalledTimes(1);
});

it('pauses while hidden, cancels an active request, and checks on return only when due', async () => {
    let finish;
    requestJson.mockImplementationOnce(() => new Promise((resolve) => (finish = resolve)));
    const { live } = mountPolling();
    await vi.advanceTimersByTimeAsync(30000);
    await visibility(true);
    await vi.advanceTimersByTimeAsync(60000);
    expect(requestJson).not.toHaveBeenCalled();
    await visibility(false);
    expect(requestJson).toHaveBeenCalledTimes(1);
    await visibility(true);
    expect(requestJson.mock.calls[0][1].signal.aborted).toBe(true);
    finish(snapshot(2));
    await flushPromises();
    expect(live.points.value[0].id).toBe(1);
    requestJson.mockResolvedValue(snapshot(3));
    await visibility(false);
    expect(requestJson).toHaveBeenCalledTimes(1);
    await vi.advanceTimersByTimeAsync(60000);
    expect(requestJson).toHaveBeenCalledTimes(2);
    expect(live.points.value[0].id).toBe(3);
});

it('preserves data after network and malformed-response failures and retries next minute', async () => {
    requestJson.mockRejectedValueOnce(new Error('Offline')).mockResolvedValueOnce({});
    const { live } = mountPolling();
    await vi.advanceTimersByTimeAsync(60000);
    expect(live.message.value).toContain('retrying in one minute');
    expect(live.status.value.PS1).toBe(10);
    expect(live.points.value[0].id).toBe(1);
    await vi.advanceTimersByTimeAsync(60000);
    expect(live.status.value.PS1).toBe(10);
    requestJson.mockResolvedValue(snapshot());
    await vi.advanceTimersByTimeAsync(60000);
    expect(live.status.value.PS1).toBe(20);
    expect(live.message.value).toContain('Readings checked');
});

it.each([401, 403, 404, 410, 419])(
    'stops on access failure %s without discarding visible data',
    async (status) => {
        requestJson.mockRejectedValue({ status });
        const { live } = mountPolling();
        await vi.advanceTimersByTimeAsync(60000);
        expect(live.message.value).toContain('Automatic updates stopped');
        await visibility(true);
        await vi.advanceTimersByTimeAsync(180000);
        await visibility(false);
        expect(requestJson).toHaveBeenCalledTimes(1);
        expect(live.status.value.PS1).toBe(10);
    },
);

it('switches device snapshots without accepting late data from the previous device', async () => {
    let finish;
    requestJson.mockImplementationOnce(() => new Promise((resolve) => (finish = resolve)));
    const { live, url, deviceKey, initialStatus, initialPoints } = mountPolling();
    await vi.advanceTimersByTimeAsync(60000);
    url.value = '/devices/2/telemetry';
    deviceKey.value = 'TEST-2';
    initialStatus.value = { Updated: 'New device', PS1: 30 };
    initialPoints.value = [];
    await flushPromises();
    expect(requestJson.mock.calls[0][1].signal.aborted).toBe(true);
    finish(snapshot());
    await flushPromises();
    expect(live.status.value.PS1).toBe(30);
    expect(live.points.value).toEqual([]);
    requestJson.mockResolvedValue(snapshot(4));
    await vi.advanceTimersByTimeAsync(60000);
    expect(requestJson.mock.calls.at(-1)[0]).toBe('/devices/2/telemetry');
    expect(live.points.value[0].id).toBe(4);
});
