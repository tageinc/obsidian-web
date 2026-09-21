import { mount, flushPromises } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import HistoryCharts from './HistoryCharts.vue';
const { Chart, destroy } = vi.hoisted(() => {
    const destroy = vi.fn();
    return {
        destroy,
        Chart: vi.fn(function () {
            this.destroy = destroy;
        }),
    };
});
vi.mock('chart.js/auto', () => ({ default: Chart }));
it('announces empty data without constructing charts', async () => {
    Chart.mockClear();
    const wrapper = mount(HistoryCharts, { props: { points: [] } });
    await flushPromises();
    expect(wrapper.text()).toContain('No telemetry was recorded');
    expect(Chart).not.toHaveBeenCalled();
});
it('redraws selected ranges and destroys every chart on navigation', async () => {
    Chart.mockClear();
    destroy.mockClear();
    const wrapper = mount(HistoryCharts, {
        props: {
            points: [
                { epoch_ms: 0, temp: 2 },
                { epoch_ms: 7200000, temp: 3 },
            ],
        },
    });
    await vi.dynamicImportSettled();
    await flushPromises();
    expect(Chart).toHaveBeenCalledTimes(3);
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('1 raw readings');
    expect(destroy).toHaveBeenCalledTimes(3);
    wrapper.unmount();
    expect(destroy).toHaveBeenCalledTimes(6);
});
