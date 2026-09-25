export const TIME_ZONE = 'America/Los_Angeles';
const clock = { hour: 'numeric', minute: '2-digit', hour12: true, timeZone: TIME_ZONE };
const date = { month: 'short', day: 'numeric', year: 'numeric', timeZoneName: 'short' };
export const formatTime = (value) => new Intl.DateTimeFormat('en-US', clock).format(value);
export const formatTimestamp = (value) =>
    new Intl.DateTimeFormat('en-US', { ...clock, ...date }).format(value);
function numeric(value) {
    if (!['string', 'number'].includes(typeof value) || String(value).trim() === '') return null;
    return Number.isFinite(Number(value)) ? Number(value) : null;
}
export function normalizePoints(points) {
    return (Array.isArray(points) ? points : [])
        .map((point, index) => {
            if (!point) return null;
            const epoch = numeric(point.epoch_ms) ?? Date.parse(point.timestamp);
            if (!Number.isFinite(epoch) || !Number.isFinite(new Date(epoch).getTime())) return null;
            return {
                id: point.id,
                epoch_ms: epoch,
                index,
                ...Object.fromEntries(
                    ['temp', 'ps1', 'ps2', 'ps_avg', 'pds', 'motor_speed'].map((key) => [
                        key,
                        numeric(point[key]),
                    ]),
                ),
            };
        })
        .filter(Boolean)
        .sort(
            (a, b) =>
                a.epoch_ms - b.epoch_ms ||
                (numeric(a.id) ?? Infinity) - (numeric(b.id) ?? Infinity) ||
                a.index - b.index,
        );
}
export function filterRange(points, hours) {
    if (!hours || !points.length) return points;
    const start = points.at(-1).epoch_ms - hours * 3600000;
    return points.filter((point) => point.epoch_ms >= start);
}
export function rangeLabel(points) {
    if (!points.length) return 'No telemetry recorded';
    return `${formatTimestamp(points[0].epoch_ms)} – ${formatTimestamp(points.at(-1).epoch_ms)} (Pacific Time)`;
}
export function axisFormatter(points) {
    const day = new Intl.DateTimeFormat('en-CA', {
        timeZone: TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
    const zone = new Intl.DateTimeFormat('en-US', { timeZone: TIME_ZONE, timeZoneName: 'short' });
    const offset = (value) =>
        zone.formatToParts(value).find((part) => part.type === 'timeZoneName')?.value;
    const first = points[0]?.epoch_ms;
    const last = points.at(-1)?.epoch_ms;
    return points.length > 1 &&
        (day.format(first) !== day.format(last) || offset(first) !== offset(last))
        ? formatTimestamp
        : formatTime;
}
export function linearRegression(data) {
    const valid = (Array.isArray(data) ? data : []).filter(
        (point) => point && Number.isFinite(point.x) && Number.isFinite(point.y),
    );
    if (valid.length < 2) return [];
    let first = Infinity;
    let last = -Infinity;
    for (const point of valid) {
        first = Math.min(first, point.x);
        last = Math.max(last, point.x);
    }
    if (first === last) return [];

    // Fit elapsed hours around their mean, avoiding cancellation from large epoch values.
    // Every valid reading contributes independently, including repeated timestamps.
    const elapsed = (x) => (x - first) / 3600000;
    const meanX = valid.reduce((sum, point) => sum + elapsed(point.x) / valid.length, 0);
    const meanY = valid.reduce((sum, point) => sum + point.y / valid.length, 0);
    let covariance = 0;
    let variance = 0;
    for (const point of valid) {
        const centeredX = elapsed(point.x) - meanX;
        covariance += centeredX * (point.y - meanY);
        variance += centeredX * centeredX;
    }
    if (!(variance > 0)) return [];
    const slope = covariance / variance;
    const endpoints = [first, last].map((x) => ({
        x,
        y: meanY + slope * (elapsed(x) - meanX),
    }));
    return endpoints.every((point) => Number.isFinite(point.y)) ? endpoints : [];
}
export function chartOptions(points, series, range = null) {
    const first = range?.start ?? points[0]?.epoch_ms;
    const last = range?.end ?? points.at(-1)?.epoch_ms;
    const label = axisFormatter(range ? [{ epoch_ms: first }, { epoch_ms: last }] : points);
    const min = first === last ? first - 1800000 : first;
    const max = first === last ? last + 1800000 : last;
    const readings = series.map(([field, title, color]) => ({
        label: title,
        data: points.map((point) => ({ x: point.epoch_ms, y: point[field], id: point.id })),
        borderColor: color,
        backgroundColor: color,
        pointRadius: 2,
        pointHoverRadius: 4,
        pointStyle: 'circle',
        showLine: false,
        tension: 0,
        spanGaps: false,
        order: 0,
    }));
    const trends = readings.flatMap((reading) => {
        const data = linearRegression(reading.data);
        return data.length
            ? [
                  {
                      type: 'line',
                      label: `${reading.label} — linear trend`,
                      data,
                      borderColor: reading.borderColor,
                      backgroundColor: reading.backgroundColor,
                      borderDash: [6, 4],
                      borderWidth: 2,
                      pointRadius: 0,
                      pointHoverRadius: 0,
                      pointStyle: 'line',
                      showLine: true,
                      tension: 0,
                      fill: false,
                      order: 1,
                      isTrend: true,
                  },
              ]
            : [];
    });
    return {
        type: 'scatter',
        data: {
            datasets: [...readings, ...trends],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            parsing: false,
            interaction: { intersect: false, mode: 'nearest' },
            scales: {
                x: {
                    type: 'linear',
                    min,
                    max,
                    afterBuildTicks: (scale) => {
                        scale.ticks = timeTicks(scale.min, scale.max).map((value) => ({ value }));
                    },
                    ticks: {
                        maxTicksLimit: 7,
                        maxRotation: 0,
                        callback: (value) => {
                            const text = label(Number(value));
                            const split = text.lastIndexOf(', ');
                            return label === formatTimestamp
                                ? [text.slice(0, split), text.slice(split + 2)]
                                : text;
                        },
                    },
                    title: { display: true, text: 'Recorded time (Pacific Time)' },
                },
                y: { beginAtZero: false },
            },
            plugins: {
                legend: { display: true, labels: { usePointStyle: true } },
                tooltip: {
                    filter: (item) => !item.dataset.isTrend,
                    callbacks: {
                        title: (items) => (items.length ? formatTimestamp(items[0].parsed.x) : ''),
                        afterLabel: (item) =>
                            item.raw.id != null ? `Reading #${item.raw.id}` : '',
                    },
                },
            },
        },
    };
}

// Keep numeric epoch positioning, but choose clock-aligned ticks instead of decimal epoch rounding.
export function timeTicks(min, max) {
    if (!Number.isFinite(min) || !Number.isFinite(max) || max < min) return [];
    const target = (max - min) / 6;
    const steps = [1, 5, 15, 30, 60, 120, 240, 360, 720, 1440, 2880, 10080].map(
        (minutes) => minutes * 60000,
    );
    const step =
        steps.find((duration) => duration >= target) || Math.ceil(target / 86400000) * 86400000;
    const ticks = [];
    for (let time = Math.ceil(min / step) * step; time <= max; time += step) ticks.push(time);
    return ticks.length ? ticks : [min];
}
