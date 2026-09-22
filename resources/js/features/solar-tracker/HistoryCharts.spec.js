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
    expect(Chart).toHaveBeenCalledTimes(1);
    const initial = Chart.mock.calls[0][1];
    expect(initial.type).toBe('scatter');
    expect(initial.data.datasets[0].showLine).toBe(false);
    expect(initial.data.datasets[1]).toMatchObject({
        isTrend: true,
        label: 'Temperature (°C) — linear trend',
        data: [
            { x: 0, y: 2 },
            { x: 7200000, y: 3 },
        ],
    });
    expect(wrapper.get('canvas').attributes('aria-label')).toContain('scatter plot');
    expect(wrapper.text()).toContain('Dashed lines fit all valid readings in the selected');
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('1 raw readings');
    expect(Chart.mock.calls.at(-1)[1].data.datasets).toHaveLength(1);
    expect(destroy).toHaveBeenCalledTimes(1);
    await wrapper.get('select').setValue('panels');
    await flushPromises();
    expect(Chart.mock.calls.at(-1)[1].data.datasets.map((set) => set.label)).toEqual([
        'PS1',
        'PS2',
    ]);
    await wrapper.setProps({ active: false });
    expect(destroy).toHaveBeenCalledTimes(3);
    await wrapper.setProps({ active: true });
    await flushPromises();
    expect(wrapper.get('select').element.value).toBe('panels');
    expect(wrapper.text()).toContain('1 raw readings');
    wrapper.unmount();
    expect(destroy).toHaveBeenCalledTimes(4);
});
it('waits until the history section is opened before initializing a chart', async () => {
    Chart.mockClear();
    const wrapper = mount(HistoryCharts, {
        props: { active: false, points: [{ epoch_ms: 0, temp: 2 }] },
    });
    await flushPromises();
    expect(Chart).not.toHaveBeenCalled();
    await wrapper.setProps({ active: true });
    await vi.dynamicImportSettled();
    await flushPromises();
    expect(Chart).toHaveBeenCalledTimes(1);
    wrapper.unmount();
});
