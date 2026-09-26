import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import HistoryCharts from './HistoryCharts.vue';
import { downloadHistoryReport } from './historyReport';
vi.mock('./historyReport', () => ({ downloadHistoryReport: vi.fn() }));
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
it('downloads all measurements for the selected range and guards pending or invalid requests', async () => {
    let finish;
    downloadHistoryReport.mockReset().mockImplementation(
        () =>
            new Promise((resolve) => {
                finish = resolve;
            }),
    );
    const wrapper = mount(HistoryCharts, { props: { reportUrl: '/devices/1/report' } });
    const button = wrapper.get('[aria-label="Download history report"]');
    await wrapper.get('#history-metric').setValue('pds');
    await button.trigger('click');
    expect(downloadHistoryReport).toHaveBeenCalledWith(
        '/devices/1/report',
        { start: -79200000, end: 7200000 },
        expect.any(AbortSignal),
    );
    expect(button.element.disabled).toBe(true);
    expect(wrapper.text()).toContain('Creating your PDF report');
    finish();
    await flushPromises();
    expect(button.element.disabled).toBe(false);
    expect(wrapper.text()).toContain('report download has started');
    await wrapper.get('#history-ending').setValue('');
    expect(button.element.disabled).toBe(true);
    expect(downloadHistoryReport).toHaveBeenCalledTimes(1);
    wrapper.unmount();
});
it('announces report failures, allows retry and cancels on leaving the device', async () => {
    downloadHistoryReport
        .mockReset()
        .mockRejectedValueOnce(new Error('The report could not be created. Please try again.'))
        .mockImplementation(() => new Promise(() => {}));
    const wrapper = mount(HistoryCharts, { props: { reportUrl: '/devices/1/report' } });
    const button = wrapper.get('[aria-label="Download history report"]');
    await button.trigger('click');
    await flushPromises();
    expect(wrapper.get('.history-report-feedback').text()).toContain('could not be created');
    expect(button.element.disabled).toBe(false);
    await button.trigger('click');
    const signal = downloadHistoryReport.mock.calls.at(-1)[2];
    expect(signal.aborted).toBe(false);
    wrapper.unmount();
    expect(signal.aborted).toBe(true);
});
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
        label: 'Temperature (°F) — linear trend',
        data: [
            { x: 0, y: 35.6 },
            { x: 7200000, y: 37.4 },
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
it('converts temperature readings to Fahrenheit without changing source data or other sensors', async () => {
    const points = [
        { epoch_ms: 0, temp: 0, ps1: 8, ps2: 12 },
        { epoch_ms: 1000, temp: '100', ps1: 10, ps2: 14 },
        { epoch_ms: 2000, temp: 26.1135 },
        { epoch_ms: 3000, temp: -40 },
        { epoch_ms: 4000, temp: null },
        { epoch_ms: 5000, temp: '' },
        { epoch_ms: 6000, temp: 'invalid' },
        { epoch_ms: 7000 },
        { epoch_ms: 8000, temp: true },
        { epoch_ms: 9000, temp: '0x10' },
    ];
    const original = points.map((point) => ({ ...point }));
    const wrapper = mount(HistoryCharts, { props: { points } });
    await vi.dynamicImportSettled();
    await flushPromises();
    const options = Chart.mock.calls.at(-1)[1];
    expect(options.data.datasets[0].label).toBe('Temperature (°F)');
    expect(options.data.datasets[0].data.map(({ y }) => y)).toEqual([
        32,
        212,
        79.0043,
        -40,
        null,
        null,
        null,
        null,
        null,
        null,
    ]);
    expect(wrapper.get('.plot-heading').text()).toContain('°F');
    expect(wrapper.get('canvas').attributes('aria-label')).toContain('degrees Fahrenheit');
    expect(points).toEqual(original);

    await wrapper.get('#history-metric').setValue('panels');
    await flushPromises();
    const panelDatasets = Chart.mock.calls.at(-1)[1].data.datasets;
    expect(panelDatasets[0].data.slice(0, 2).map(({ y }) => y)).toEqual([8, 10]);
    expect(panelDatasets[1].data.slice(0, 2).map(({ y }) => y)).toEqual([12, 14]);
    await wrapper.get('#history-metric').setValue('temperature');
    await wrapper.setProps({ points: [{ epoch_ms: 0, temp: 10 }] });
    await flushPromises();
    expect(Chart.mock.calls.at(-1)[1].data.datasets[0].data[0].y).toBe(50);
    wrapper.unmount();
});
it('uses derived Fahrenheit values and preserves missing measurements from the telemetry API', async () => {
    const wrapper = mount(HistoryCharts, {
        props: {
            points: [
                { epoch_ms: 0, temp: 26.1135, temp_f: 79.0043 },
                { epoch_ms: 1000, temp: 0, temp_f: null },
                { epoch_ms: 2000, temp: 0, temp_f: 32 },
            ],
        },
    });
    await vi.dynamicImportSettled();
    await flushPromises();
    expect(Chart.mock.calls.at(-1)[1].data.datasets[0].data.map(({ y }) => y)).toEqual([
        79.0043,
        null,
        32,
    ]);
    wrapper.unmount();
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

it('shows the zero reference only for PDS and motor speed when switching measurements', async () => {
    const wrapper = mount(HistoryCharts, {
        props: {
            points: [
                { epoch_ms: 0, pds: -4, motor_speed: -20, temp: 21 },
                { epoch_ms: 1000, pds: 2, motor_speed: 10, temp: 22 },
            ],
        },
    });
    await vi.dynamicImportSettled();
    await flushPromises();
    for (const metric of ['pds', 'motor', 'temperature', 'ps_avg', 'panels', 'pds']) {
        await wrapper.get('#history-metric').setValue(metric);
        await flushPromises();
        const showZero = metric === 'pds' || metric === 'motor';
        const options = Chart.mock.calls.at(-1)[1];
        expect(options.options.scales.y.beginAtZero).toBe(showZero);
        expect(wrapper.get('.history-footer').text().includes('marks y = 0')).toBe(showZero);
        expect(wrapper.get('canvas').attributes('aria-label').includes('marks y = 0')).toBe(
            showZero,
        );
        if (showZero) {
            expect(options.options.scales.y.grid.lineWidth({ tick: { value: 0 } })).toBe(2);
        }
    }
    wrapper.unmount();
});

it('advances the live last 24 hours after each successful refresh even without new readings', async () => {
    const wrapper = mount(HistoryCharts, {
        props: {
            points: [
                { epoch_ms: -79200000, temp: 2 },
                { epoch_ms: 7200000, temp: 3 },
            ],
        },
    });
    await wrapper.get('#history-metric').setValue('pds');
    await wrapper.setProps({ refreshedAt: 7260000 });
    expect(wrapper.get('#history-ending').element.value).toBe('1969-12-31T18:01');
    expect(wrapper.get('.plot-heading').text()).toContain('1 raw readings');
    expect(wrapper.get('#history-metric').element.value).toBe('pds');
    expect(wrapper.get('.history-clear').element.disabled).toBe(true);
    await wrapper.get('[aria-label="Previous time range"]').trigger('click');
    const past = wrapper.get('#history-ending').element.value;
    await wrapper.setProps({ refreshedAt: 7320000 });
    expect(wrapper.get('#history-ending').element.value).toBe(past);
    vi.setSystemTime(new Date(7320000));
    await wrapper
        .findAll('button')
        .find((button) => button.text() === 'Today')
        .trigger('click');
    await wrapper.setProps({ refreshedAt: 7380000 });
    expect(wrapper.get('#history-ending').element.value).toBe('1969-12-31T18:03');
    wrapper.unmount();
});

it('preserves manually entered, custom and calendar periods while accepting new points', async () => {
    const wrapper = mount(HistoryCharts);
    await wrapper.get('#history-ending').setValue('1969-12-30T18:00');
    await wrapper.setProps({ refreshedAt: 7260000 });
    expect(wrapper.get('#history-ending').element.value).toBe('1969-12-30T18:00');
    await wrapper.get('#history-view').setValue('custom');
    const from = wrapper.get('#history-from').element.value;
    const to = wrapper.get('#history-to').element.value;
    await wrapper.setProps({ refreshedAt: 7320000, points: [{ epoch_ms: 0, temp: 8 }] });
    expect(wrapper.get('#history-from').element.value).toBe(from);
    expect(wrapper.get('#history-to').element.value).toBe(to);
    for (const view of ['week', 'month']) {
        await wrapper.get('#history-view').setValue(view);
        await wrapper.get('#history-period-date').setValue('2026-08-15');
        const selected = wrapper.get('#history-period-date').element.value;
        await wrapper.setProps({ refreshedAt: Date.parse('2026-09-25T18:00:00Z') });
        expect(wrapper.get('#history-period-date').element.value).toBe(selected);
    }
    await wrapper.get('#history-view').setValue('custom');
    await wrapper.get('#history-from').setValue('');
    await wrapper.setProps({ refreshedAt: Date.parse('2026-09-25T18:01:00Z') });
    expect(wrapper.get('#history-from').element.value).toBe('');
    expect(wrapper.get('[role="alert"]').text()).toContain('Enter valid');
    await wrapper.get('.history-clear').trigger('click');
    await wrapper.setProps({ refreshedAt: 7380000 });
    expect(wrapper.get('#history-ending').element.value).toBe('1969-12-31T18:03');
    wrapper.unmount();
});

it('extends available history to include new telemetry without changing its view', async () => {
    const wrapper = mount(HistoryCharts, {
        props: {
            points: [
                { epoch_ms: 0, temp: 2 },
                { epoch_ms: 7200000, temp: 3 },
            ],
        },
    });
    await wrapper.get('#history-view').setValue('all');
    await wrapper.setProps({
        refreshedAt: 7260000,
        points: [
            { epoch_ms: 7200000, temp: 3 },
            { epoch_ms: 7260000, temp: 4 },
        ],
    });
    expect(wrapper.get('#history-view').element.value).toBe('all');
    expect(wrapper.get('.plot-heading').text()).toContain('2 raw readings');
    expect(wrapper.get('.history-range-display').text()).toContain('6:01 PM');
    wrapper.unmount();
});
