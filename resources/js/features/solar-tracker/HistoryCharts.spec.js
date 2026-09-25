import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
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
beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(7200000));
});
afterEach(() => vi.useRealTimers());
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
    await wrapper.get('#history-view').setValue('custom');
    await wrapper.get('#history-from').setValue('1969-12-31T17:00:00');
    await flushPromises();
    expect(wrapper.text()).toContain('1 raw readings');
    expect(Chart.mock.calls.at(-1)[1].data.datasets).toHaveLength(1);
    expect(destroy).toHaveBeenCalledTimes(1);
    await wrapper.get('#history-metric').setValue('panels');
    await flushPromises();
    expect(Chart.mock.calls.at(-1)[1].data.datasets.map((set) => set.label)).toEqual([
        'PS1',
        'PS2',
    ]);
    await wrapper.setProps({ active: false });
    expect(destroy).toHaveBeenCalledTimes(3);
    await wrapper.setProps({ active: true });
    await flushPromises();
    expect(wrapper.get('#history-metric').element.value).toBe('panels');
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

it('defaults to now, validates datetimes, and restores the current last 24 hours', async () => {
    const wrapper = mount(HistoryCharts, {
        props: {
            points: [
                { epoch_ms: -86400001, temp: 1 },
                { epoch_ms: 0, temp: 2 },
                { epoch_ms: 7200001, temp: 3 },
            ],
        },
    });
    await flushPromises();
    expect(wrapper.get('#history-ending').element.value).toBe('1969-12-31T18:00');
    expect(wrapper.find('#history-from').exists()).toBe(false);
    await wrapper.get('#history-view').setValue('custom');
    expect(wrapper.get('#history-from').element.value).toBe('1969-12-30T18:00');
    expect(wrapper.get('#history-to').element.value).toBe('1969-12-31T18:00');
    expect(wrapper.text()).toContain('1 raw readings');
    await wrapper.get('#history-from').setValue('1970-01-01T18:00:00');
    expect(wrapper.get('[role="alert"]').text()).toContain('before or equal');
    expect(wrapper.get('canvas').isVisible()).toBe(false);
    await wrapper.get('#history-from').setValue('');
    expect(wrapper.get('[role="alert"]').text()).toContain('Enter valid');
    const reset = wrapper.findAll('button').find((button) => button.text() === 'Clear');
    vi.setSystemTime(new Date(10800000));
    await reset.trigger('click');
    expect(wrapper.get('#history-ending').element.value).toBe('1969-12-31T19:00');
    expect(wrapper.get('[role="alert"]').text()).toBe('');
    expect(wrapper.get('#history-view').element.value).toBe('day');
    wrapper.unmount();
});
it('navigates periods, offers available history and disables period arrows for custom ranges', async () => {
    const wrapper = mount(HistoryCharts, {
        props: { points: [{ epoch_ms: 0 }, { epoch_ms: 7200000 }] },
    });
    await wrapper.get('[aria-label="Previous time range"]').trigger('click');
    expect(wrapper.get('#history-ending').element.value).toBe('1969-12-30T18:00');
    await wrapper.get('[aria-label="Next time range"]').trigger('click');
    expect(wrapper.get('#history-ending').element.value).toBe('1969-12-31T18:00');
    await wrapper.get('#history-view').setValue('week');
    expect(wrapper.get('#history-period-date').element.value).toBe('1969-12-29');
    await wrapper.get('#history-view').setValue('all');
    expect(wrapper.text()).toContain('2 raw readings');
    expect(wrapper.get('[aria-label="Next time range"]').element.disabled).toBe(true);
    await wrapper.get('#history-view').setValue('custom');
    await wrapper.get('#history-to').setValue('1969-12-31T17:00:00');
    expect(wrapper.get('#history-view').element.value).toBe('custom');
    expect(wrapper.text()).toContain('1 raw readings');
    wrapper.unmount();
});

it('uses a single period date and Today without changing the selected calendar view', async () => {
    const wrapper = mount(HistoryCharts);
    await wrapper.get('#history-view').setValue('week');
    expect(wrapper.find('#history-from').exists()).toBe(false);
    await wrapper.get('#history-period-date').setValue('2026-09-25');
    expect(wrapper.get('#history-period-date').element.value).toBe('2026-09-21');
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Today')
        .trigger('click');
    expect(wrapper.get('#history-view').element.value).toBe('week');
    expect(wrapper.get('#history-period-date').element.value).toBe('1969-12-29');
    await wrapper.get('#history-view').setValue('month');
    await wrapper.get('#history-period-date').setValue('2026-02-20');
    expect(wrapper.get('#history-period-date').element.value).toBe('2026-02-01');
    await wrapper.get('#history-view').setValue('day');
    await wrapper.get('#history-ending').setValue('1969-12-30T18:00');
    expect(wrapper.get('#history-view').element.value).toBe('day');
    await wrapper.get('#history-view').setValue('custom');
    expect(wrapper.get('#history-from').element.value).toBe('1969-12-29T18:00');
    wrapper.unmount();
});
it('plots the recorded PDS and PS average values with independent trends and missing values', async () => {
    const wrapper = mount(HistoryCharts, {
        props: {
            points: [
                { epoch_ms: 0, ps1: 1, ps2: 3, ps_avg: 50, pds: 6 },
                { epoch_ms: 3600000, ps1: 2, ps2: 4, ps_avg: null, pds: null },
                { epoch_ms: 7200000, ps1: 3, ps2: 5, ps_avg: 90, pds: 12 },
            ],
        },
    });
    await vi.dynamicImportSettled();
    await flushPromises();
    for (const [metric, label, values] of [
        ['pds', 'PDS', [6, null, 12]],
        ['ps_avg', 'PS average', [50, null, 90]],
    ]) {
        await wrapper.get('#history-metric').setValue(metric);
        await flushPromises();
        const options = Chart.mock.calls.at(-1)[1];
        expect(options.data.datasets[0].label).toBe(label);
        expect(options.data.datasets[0].data.map(({ y }) => y)).toEqual(values);
        expect(options.data.datasets[1].isTrend).toBe(true);
        expect(options.data.datasets[1].data.map(({ y }) => y)).toEqual([values[0], values[2]]);
        expect(wrapper.get('canvas').attributes('aria-label')).toContain(label);
    }
    wrapper.unmount();
});

it('keeps invalid period input announced when entering a custom span', async () => {
    const wrapper = mount(HistoryCharts, { props: { points: [{ epoch_ms: 0, temp: 2 }] } });
    await flushPromises();
    await wrapper.get('#history-ending').setValue('');
    expect(wrapper.get('[role="alert"]').text()).toContain('Enter a valid');
    await wrapper.get('#history-view').setValue('custom');
    expect(wrapper.get('[role="alert"]').text()).toContain('Enter valid From and To');
    expect(wrapper.get('#history-to').element.value).toBe('');
    expect(wrapper.get('canvas').isVisible()).toBe(false);
    wrapper.unmount();
});
