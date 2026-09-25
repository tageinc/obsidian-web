import { TIME_ZONE } from './timeSeries.js';

const hour = 3600000;
const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
});

export function datetimeInput(epoch) {
    const parts = Object.fromEntries(
        formatter.formatToParts(epoch).map(({ type, value }) => [type, value]),
    );
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}`;
}

export function parseDatetime(value, edge = 'start') {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(value)) return null;
    const full = value.length === 16 ? `${value}:00` : value;
    const naive = Date.parse(`${full}Z`);
    if (!Number.isFinite(naive)) return null;
    // Resolve Pacific wall time independently of the browser timezone. Reject DST gaps;
    // include both occurrences of repeated fall-back times at the range boundaries.
    const offsets = [-24, 0, 24].map((hours) => {
        const probe = naive + hours * hour;
        return Date.parse(`${datetimeInput(probe)}Z`) - probe;
    });
    const matches = [...new Set(offsets)]
        .map((offset) => naive - offset)
        .filter((epoch) => datetimeInput(epoch) === full)
        .sort((a, b) => a - b);
    return (edge === 'end' ? matches.at(-1) : matches[0]) ?? null;
}

export function lastDay(now = Date.now()) {
    const end = Math.floor(now / 1000) * 1000;
    return { start: end - 24 * hour, end };
}

export function calendarRange(mode, anchor) {
    const day = new Date(`${datetimeInput(anchor).slice(0, 10)}T00:00:00Z`);
    if (mode === 'week') day.setUTCDate(day.getUTCDate() - ((day.getUTCDay() + 6) % 7));
    else day.setUTCDate(1);
    const start = parseDatetime(day.toISOString().slice(0, 19));
    if (mode === 'week') day.setUTCDate(day.getUTCDate() + 7);
    else day.setUTCMonth(day.getUTCMonth() + 1);
    return { start, end: parseDatetime(day.toISOString().slice(0, 19)) - 1000 };
}

export function shiftRange(range, mode, direction) {
    if (mode === 'day')
        return {
            start: range.start + direction * 24 * hour,
            end: range.end + direction * 24 * hour,
        };
    if (mode === 'week')
        return calendarRange(mode, range.start + direction * 7 * 24 * hour + 12 * hour);
    const day = new Date(`${datetimeInput(range.start).slice(0, 10)}T12:00:00Z`);
    day.setUTCMonth(day.getUTCMonth() + direction);
    return calendarRange(mode, parseDatetime(day.toISOString().slice(0, 19)));
}

export function pointsInRange(points, range) {
    return points.filter(({ epoch_ms }) => epoch_ms >= range.start && epoch_ms <= range.end);
}
