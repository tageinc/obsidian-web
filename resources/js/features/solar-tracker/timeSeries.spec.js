import { describe, expect, it } from 'vitest';
import {
    normalizePoints,
    formatTime,
    formatTimestamp,
    filterRange,
    axisFormatter,
    chartOptions,
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
        expect(
            chart.options.plugins.tooltip.callbacks.title([{ parsed: { x: points[0].epoch_ms } }]),
        ).toContain('12:30 PM PDT');
    });
});
