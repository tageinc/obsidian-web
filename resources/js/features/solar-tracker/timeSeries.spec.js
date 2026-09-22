import { describe, expect, it } from 'vitest';
import {
    normalizePoints,
    formatTime,
    formatTimestamp,
    filterRange,
    axisFormatter,
    chartOptions,
    linearRegression,
    timeTicks,
} from './timeSeries';
describe('raw solar tracker time series', () => {
    it('places a bounded number of clock-aligned ticks inside the plotted range', () => {
        const start = Date.parse('2026-01-01T15:45:00Z');
        const end = start + 12 * 3600000;
        const ticks = timeTicks(start, end);
        expect(ticks.length).toBeGreaterThan(1);
        expect(ticks.length).toBeLessThanOrEqual(7);
        expect(ticks.every((tick) => tick >= start && tick <= end && tick % 3600000 === 0)).toBe(
            true,
        );
        const range = normalizePoints([{ epoch_ms: start }, { epoch_ms: end + 86400000 }]);
        const chart = chartOptions(range, []);
        expect(chart.options.scales.x.ticks.callback(start).join(', ')).toBe(
            formatTimestamp(start),
        );
        expect(chart.options.scales.x.min).toBe(start);
    });
    it('sorts timestamps and duplicate IDs without averaging, inventing points or treating blanks as zero', () => {
        const points = normalizePoints([
            { id: 3, timestamp: '2026-01-01T21:00:00Z', temp: '' },
            { id: 2, epoch_ms: 10, temp: '3.5' },
            { id: 1, epoch_ms: 10, temp: null },
            { timestamp: 'invalid' },
            null,
        ]);
        expect(points.map((p) => p.id)).toEqual([1, 2, 3]);
        expect(points.map((p) => p.temp)).toEqual([null, 3.5, null]);
    });
    it('formats noon and midnight with AM/PM in Pacific Time regardless of browser timezone', () => {
        expect(formatTime(Date.parse('2026-01-01T20:00:00Z'))).toBe('12:00 PM');
        expect(formatTime(Date.parse('2026-01-01T08:00:00Z'))).toBe('12:00 AM');
        expect(formatTimestamp(Date.parse('2026-07-01T19:30:00Z'))).toContain('12:30 PM PDT');
    });
    it('disambiguates repeated fall-back hours and shows the spring-forward jump', () => {
        const fall = normalizePoints([
            { timestamp: '2026-11-01T08:30:00Z' },
            { timestamp: '2026-11-01T09:30:00Z' },
        ]);
        const format = axisFormatter(fall);
        expect(format(fall[0].epoch_ms)).toContain('1:30 AM PDT');
        expect(format(fall[1].epoch_ms)).toContain('1:30 AM PST');
        expect(formatTime(Date.parse('2026-03-08T09:30:00Z'))).toBe('1:30 AM');
        expect(formatTime(Date.parse('2026-03-08T10:30:00Z'))).toBe('3:30 AM');
    });
    it('handles short, twelve-hour, multiday and midnight ranges against source instants', () => {
        const points = normalizePoints(
            [0, 12, 24, 36, 48].map((hours) => ({
                epoch_ms: Date.parse('2026-01-01T07:30:00Z') + hours * 3600000,
            })),
        );
        expect(filterRange(points, 1)).toHaveLength(1);
        expect(filterRange(points, 12)).toHaveLength(2);
        expect(filterRange(points, 0)).toHaveLength(5);
        expect(axisFormatter(points)(points[0].epoch_ms)).toContain('Dec 31, 2025');
        expect(normalizePoints([])).toEqual([]);
    });
    it('plots exact epochs, preserves missing measurements and uses identical tooltip timezone policy', () => {
        const points = normalizePoints([
            { id: 9, timestamp: '2026-07-01T19:30:00Z', temp: 21 },
            { timestamp: '2026-07-01T20:00:00Z', temp: null },
        ]);
        const chart = chartOptions(points, [['temp', 'Temperature', 'blue']]);
        expect(chart.data.datasets[0].data).toEqual([
            { x: points[0].epoch_ms, y: 21, id: 9 },
            { x: points[1].epoch_ms, y: null, id: undefined },
        ]);
        expect(chart.data.datasets[0].spanGaps).toBe(false);
        expect(chart.type).toBe('scatter');
        expect(chart.data.datasets[0].showLine).toBe(false);
        expect(chart.data.datasets).toHaveLength(1);
        expect(
            chart.options.plugins.tooltip.callbacks.title([{ parsed: { x: points[0].epoch_ms } }]),
        ).toContain('12:30 PM PDT');
    });
});

describe('least-squares trends over all visible raw readings', () => {
    const epoch = Date.parse('2026-11-01T08:30:00Z');
    const atHour = (hours) => epoch + hours * 3600000;

    it('fits an irregularly spaced noisy series instead of joining its first and last readings', () => {
        const data = Object.freeze([
            Object.freeze({ x: atHour(4), y: 3, id: 3 }),
            Object.freeze({ x: atHour(0), y: 1, id: 1 }),
            Object.freeze({ x: atHour(1), y: 4, id: 2 }),
        ]);
        const line = linearRegression(data);
        expect(line.map((point) => point.x)).toEqual([atHour(0), atHour(4)]);
        expect(line[0].y).toBeCloseTo(28 / 13, 12);
        expect(line[1].y).toBeCloseTo(44 / 13, 12);
        expect(data.map((point) => point.id)).toEqual([3, 1, 2]);
        expect(data.map((point) => point.y)).toEqual([3, 1, 4]);
    });

    it('weights duplicate timestamps as separate observations without averaging them first', () => {
        const line = linearRegression([
            { x: atHour(0), y: 0, id: 1 },
            { x: atHour(0), y: 6, id: 2 },
            { x: atHour(1), y: 0, id: 3 },
            { x: atHour(2), y: 0, id: 4 },
        ]);
        expect(line[0].y).toBeCloseTo(30 / 11, 12);
        expect(line[1].y).toBeCloseTo(-6 / 11, 12);
    });

    it('skips missing and non-finite coordinates without treating them as zero', () => {
        const line = linearRegression([
            null,
            { x: atHour(-1), y: null },
            { x: atHour(0), y: 2 },
            { x: atHour(1), y: NaN },
            { x: atHour(1), y: Infinity },
            { x: atHour(1), y: undefined },
            { x: atHour(1), y: '' },
            { x: null, y: 50 },
            { x: NaN, y: 50 },
            { x: Infinity, y: 50 },
            { x: atHour(2), y: 6 },
            { x: atHour(3), y: null },
        ]);
        expect(line).toEqual([
            { x: atHour(0), y: 2 },
            { x: atHour(2), y: 6 },
        ]);
    });

    it.each([
        undefined,
        [],
        [{ x: 1, y: 2 }],
        [
            { x: 1, y: 2 },
            { x: 2, y: null },
        ],
        [
            { x: 1, y: 2 },
            { x: 1, y: 4 },
        ],
    ])('omits a trend when fewer than two valid timestamps exist: %j', (data) => {
        expect(linearRegression(data)).toEqual([]);
    });

    it('supports constant measurements and millisecond spacing at modern epoch values', () => {
        expect(
            linearRegression([
                { x: atHour(0), y: 7 },
                { x: atHour(1), y: 7 },
                { x: atHour(4), y: 7 },
            ]),
        ).toEqual([
            { x: atHour(0), y: 7 },
            { x: atHour(4), y: 7 },
        ]);
        const precise = linearRegression([
            { x: epoch, y: 3 },
            { x: epoch + 1, y: 5 },
            { x: epoch + 3, y: 9 },
        ]);
        expect(precise[0].x).toBe(epoch);
        expect(precise[1].x).toBe(epoch + 3);
        expect(precise[0].y).toBeCloseTo(3, 12);
        expect(precise[1].y).toBeCloseTo(9, 12);
    });

    it('fits elapsed instants across repeated and skipped Pacific hours', () => {
        for (const timestamps of [
            ['2026-11-01T08:30:00Z', '2026-11-01T09:30:00Z', '2026-11-01T10:30:00Z'],
            ['2026-03-08T09:30:00Z', '2026-03-08T10:30:00Z', '2026-03-08T11:30:00Z'],
        ]) {
            const line = linearRegression(
                timestamps.map((timestamp, index) => ({
                    x: Date.parse(timestamp),
                    y: index * 2,
                })),
            );
            expect(line).toEqual([
                { x: Date.parse(timestamps[0]), y: 0 },
                { x: Date.parse(timestamps[2]), y: 4 },
            ]);
        }
    });

    it('keeps raw readings first and fits each metric independently with identifiable trend styling', () => {
        const points = normalizePoints([
            { id: 1, epoch_ms: atHour(0), ps1: 1, ps2: 5 },
            { id: 2, epoch_ms: atHour(1), ps1: 4, ps2: null },
            { id: 3, epoch_ms: atHour(4), ps1: 3, ps2: 13 },
        ]);
        const snapshot = structuredClone(points);
        const chart = chartOptions(points, [
            ['ps1', 'PS1', 'red'],
            ['ps2', 'PS2', 'blue'],
        ]);
        const [ps1, ps2, ps1Trend, ps2Trend] = chart.data.datasets;
        expect(chart.type).toBe('scatter');
        expect(chart.data.datasets.map((dataset) => dataset.label)).toEqual([
            'PS1',
            'PS2',
            'PS1 — linear trend',
            'PS2 — linear trend',
        ]);
        for (const [dataset, field] of [
            [ps1, 'ps1'],
            [ps2, 'ps2'],
        ]) {
            expect(dataset.data).toEqual(
                points.map((point) => ({
                    x: point.epoch_ms,
                    y: point[field],
                    id: point.id,
                })),
            );
            expect(dataset).toMatchObject({
                showLine: false,
                pointRadius: 2,
                pointHoverRadius: 4,
                pointStyle: 'circle',
                order: 0,
            });
        }
        for (const [trend, color] of [
            [ps1Trend, 'red'],
            [ps2Trend, 'blue'],
        ]) {
            expect(trend).toMatchObject({
                type: 'line',
                showLine: true,
                isTrend: true,
                borderColor: color,
                borderDash: [6, 4],
                borderWidth: 2,
                tension: 0,
                fill: false,
                pointRadius: 0,
                pointHoverRadius: 0,
                pointStyle: 'line',
                order: 1,
            });
            expect(trend.data.map((point) => point.x)).toEqual([atHour(0), atHour(4)]);
        }
        expect(ps1Trend.data[0].y).toBeCloseTo(28 / 13, 12);
        expect(ps1Trend.data[1].y).toBeCloseTo(44 / 13, 12);
        expect(ps2Trend.data).toEqual([
            { x: atHour(0), y: 5 },
            { x: atHour(4), y: 13 },
        ]);
        expect(chart.options.plugins.legend).toEqual({
            display: true,
            labels: { usePointStyle: true },
        });
        const tooltip = chart.options.plugins.tooltip;
        expect(tooltip.filter({ dataset: ps1 })).toBe(true);
        expect(tooltip.filter({ dataset: ps1Trend })).toBe(false);
        expect(tooltip.callbacks.afterLabel({ raw: ps1.data[0] })).toBe('Reading #1');
        expect(points).toEqual(snapshot);
    });

    it('refits only the visible range and never extends beyond the valid metric timestamps', () => {
        const points = normalizePoints([
            { epoch_ms: atHour(-1), temp: null },
            { epoch_ms: atHour(0), temp: 1 },
            { epoch_ms: atHour(1), temp: 4 },
            { epoch_ms: atHour(4), temp: 3 },
            { epoch_ms: atHour(5), temp: null },
        ]);
        const series = [['temp', 'Temperature', 'blue']];
        const all = chartOptions(points, series).data.datasets[1].data;
        expect(all.map((point) => point.x)).toEqual([atHour(0), atHour(4)]);
        expect(all[0].y).toBeCloseTo(28 / 13, 12);
        const visible = chartOptions(filterRange(points, 4), series).data.datasets[1].data;
        expect(visible).toEqual([
            { x: atHour(1), y: 4 },
            { x: atHour(4), y: 3 },
        ]);
        expect(chartOptions(filterRange(points, 1), series).data.datasets).toHaveLength(1);
        expect(chartOptions([], series).data.datasets[0].data).toEqual([]);
        expect(chartOptions([], series).data.datasets).toHaveLength(1);
    });
});
