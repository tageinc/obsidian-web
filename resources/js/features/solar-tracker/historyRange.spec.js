import { describe, expect, it } from 'vitest';
import {
    calendarRange,
    datetimeInput,
    lastDay,
    lastNHours,
    parseDatetime,
    pointsInRange,
    shiftRange,
} from './historyRange';
import { chartOptions } from './timeSeries';

describe('Pacific datetime ranges', () => {
    it('defaults to 24 elapsed hours from now, including across DST', () => {
        const now = Date.parse('2026-03-08T10:30:00Z');
        const range = lastDay(now);
        expect(range).toEqual({ start: now - 86400000, end: now });
        expect(datetimeInput(range.start)).toBe('2026-03-07T02:30:00');
        expect(datetimeInput(range.end)).toBe('2026-03-08T03:30:00');
        expect(
            pointsInRange(
                [
                    { epoch_ms: range.start - 1 },
                    { epoch_ms: range.start },
                    { epoch_ms: now },
                    { epoch_ms: now + 1 },
                ],
                range,
            ),
        ).toEqual([{ epoch_ms: range.start }, { epoch_ms: now }]);
    });
    it('rejects empty, invalid, and skipped times and resolves repeated boundaries', () => {
        for (const value of ['', 'bad', '2026-02-30T12:00', '2026-03-08T02:30'])
            expect(parseDatetime(value)).toBeNull();
        expect(parseDatetime('2026-11-01T01:30')).toBe(Date.parse('2026-11-01T08:30:00Z'));
        expect(parseDatetime('2026-11-01T01:30', 'end')).toBe(Date.parse('2026-11-01T09:30:00Z'));
        expect(parseDatetime('2026-07-01T12:30:15')).toBe(Date.parse('2026-07-01T19:30:15Z'));
    });
    it('uses Monday weeks and calendar months with navigation across DST and years', () => {
        const week = calendarRange('week', Date.parse('2026-03-08T15:00:00Z'));
        expect(datetimeInput(week.start)).toBe('2026-03-02T00:00:00');
        expect(datetimeInput(week.end)).toBe('2026-03-08T23:59:59');
        expect(datetimeInput(shiftRange(week, 'week', 1).start)).toBe('2026-03-09T00:00:00');
        expect(datetimeInput(shiftRange(week, 'week', -1).start)).toBe('2026-02-23T00:00:00');
        const month = calendarRange('month', Date.parse('2026-12-31T15:00:00Z'));
        expect(datetimeInput(shiftRange(month, 'month', 1).start)).toBe('2027-01-01T00:00:00');
        expect(datetimeInput(shiftRange(month, 'month', -1).end)).toBe('2026-11-30T23:59:59');
    });
    it('keeps chart bounds on the selected interval even for sparse readings', () => {
        const range = lastDay(Date.parse('2026-09-25T19:00:00Z'));
        const chart = chartOptions(
            [{ epoch_ms: range.end - 60000, temp: 3 }],
            [['temp', 'Temperature', 'blue']],
            range,
        );
        expect(chart.options.scales.x.min).toBe(range.start);
        expect(chart.options.scales.x.max).toBe(range.end);
    });
});

describe('shorter time-window options (OB-19)', () => {
    it.each([1, 2, 12, 6, 48])('lastNHours returns the correct elapsed hours (%s)', (hourCount) => {
        const now = Date.parse('2026-03-08T15:00:00Z');
        const range = lastNHours(hourCount, now);
        expect(range).toEqual({ start: now - hourCount * 3600000, end: now });
    });

    it('shifts hour-mode ranges by one hour', () => {
        const range = lastNHours(1, Date.parse('2026-03-08T15:00:00Z'));
        expect(shiftRange(range, 'hour', 1)).toEqual({
            start: range.start + 3600000,
            end: range.end + 3600000,
        });
    });

    it('shifts twelve-hours mode ranges by one full window', () => {
        const range = lastNHours(12, Date.parse('2026-03-08T15:00:00Z'));
        expect(shiftRange(range, 'twelveHours', 1)).toEqual({
            start: range.start + 12 * 3600000,
            end: range.end + 12 * 3600000,
        });
    });

    it('keeps fixed durations across DST boundaries', () => {
        const hourRange = lastNHours(1, Date.parse('2026-03-08T15:00:00Z'));
        const twelveHourRange = lastNHours(12, Date.parse('2026-03-08T15:00:00Z'));
        expect(
            shiftRange(hourRange, 'hour', -1).end - shiftRange(hourRange, 'hour', -1).start,
        ).toBe(3600000);
        expect(
            shiftRange(twelveHourRange, 'twelveHours', -1).end -
                shiftRange(twelveHourRange, 'twelveHours', -1).start,
        ).toBe(12 * 3600000);
    });
});
