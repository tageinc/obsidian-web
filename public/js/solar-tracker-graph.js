/* global Chart */
(function (root, factory) {
    const api = factory(root);
    if (typeof module === 'object' && module.exports) module.exports = api;
    root.SolarTrackerGraph = api;
}(typeof window !== 'undefined' ? window : globalThis, function (root) {
    const TIME_ZONE = 'America/Los_Angeles';
    const FORMATTER_OPTIONS = { hour: 'numeric', minute: '2-digit', hour12: true };

    function asDate(value) { return new Date(typeof value === 'object' ? value.epoch_ms : value); }

    function formatTime(value) {
        return new Intl.DateTimeFormat('en-US', { ...FORMATTER_OPTIONS, timeZone: TIME_ZONE }).format(asDate(value));
    }

    function formatTimestamp(value, includeDate) {
        const options = includeDate
            ? { month: 'short', day: 'numeric', year: 'numeric', ...FORMATTER_OPTIONS, timeZoneName: 'short', timeZone: TIME_ZONE }
            : { ...FORMATTER_OPTIONS, timeZone: TIME_ZONE };
        return new Intl.DateTimeFormat('en-US', options).format(asDate(value));
    }

    function dateKey(value) {
        return new Intl.DateTimeFormat('en-CA', { timeZone: TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit' }).format(asDate(value));
    }

    function shouldIncludeDate(points) {
        return points.length > 1 && (points[points.length - 1].epoch_ms - points[0].epoch_ms > 24 * 60 * 60 * 1000 || dateKey(points[0]) !== dateKey(points[points.length - 1]));
    }

    function normalizePoints(points) {
        function numericValue(value) {
            if (typeof value !== 'number' && typeof value !== 'string') return null;
            if (typeof value === 'string' && value.trim() === '') return null;
            const number = Number(value);
            return Number.isFinite(number) ? number : null;
        }
        return (points || []).map(function (point, index) {
            const epoch = numericValue(point.epoch_ms) ?? Date.parse(point.timestamp);
            if (!Number.isFinite(epoch) || !Number.isFinite(new Date(epoch).getTime())) return null;
            const result = { id: point.id, epoch_ms: epoch };
            ['temp', 'ps1', 'ps2', 'motor_speed'].forEach(function (field) {
                result[field] = numericValue(point[field]);
            });
            return { point: result, index: index, id: numericValue(point.id) };
        }).filter(Boolean).sort(function (a, b) {
            if (a.point.epoch_ms !== b.point.epoch_ms) return a.point.epoch_ms - b.point.epoch_ms;
            if (a.id !== null && b.id !== null) return a.id - b.id || a.index - b.index;
            if (a.id !== null) return -1;
            if (b.id !== null) return 1;
            return a.index - b.index;
        }).map(function (entry) {
            return entry.point;
        });
    }

    function rangeLabel(points) {
        if (!points.length) return 'No telemetry recorded';
        return formatTimestamp(points[0], true) + ' – ' + formatTimestamp(points[points.length - 1], true) + ' (Pacific Time)';
    }

    function filteredPoints(points, hours) {
        if (!hours || !points.length) return points;
        const latest = points[points.length - 1].epoch_ms;
        return points.filter(function (point) { return point.epoch_ms >= latest - hours * 60 * 60 * 1000; });
    }

    function makeChart(canvas, series, points) {
        if (!root.Chart) return null;
        const includeDate = shouldIncludeDate(points);
        return new root.Chart(canvas.getContext('2d'), {
            type: 'line',
            data: { datasets: series.map(function (metric) {
                return { label: metric.label, data: points.map(function (point) { return { x: point.epoch_ms, y: point[metric.field], id: point.id }; }), borderColor: metric.color, backgroundColor: metric.color, pointRadius: 2, tension: 0, spanGaps: false };
            }) },
            options: { responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'nearest' }, scales: {
                x: { type: 'linear', ticks: { maxTicksLimit: 7, callback: function (value) { return includeDate ? formatTimestamp(Number(value), true) : formatTime(Number(value)); } }, title: { display: true, text: 'Recorded time (Pacific Time)' } },
                y: { beginAtZero: false }
            }, plugins: { tooltip: { callbacks: {
                title: function (items) { return items.length ? formatTimestamp(items[0].parsed.x, true) : ''; },
                afterLabel: function (item) { return item.raw.id != null ? 'Reading #' + item.raw.id : ''; }
            } }, legend: { display: series.length > 1 } } }
        });
    }

    function render(container, rawPoints) {
        const allPoints = normalizePoints(rawPoints);
        let charts = [];
        function redraw(hours) {
            charts.forEach(function (chart) { chart.destroy(); });
            const points = filteredPoints(allPoints, hours);
            container.querySelector('[data-graph-range-label]').textContent = rangeLabel(points);
            const empty = container.querySelector('[data-graph-empty]');
            empty.hidden = points.length > 0;
            charts = points.length ? [
                makeChart(container.querySelector('[data-chart="temperature"]'), [{ label: 'Temperature (°C)', color: '#0d6efd', field: 'temp' }], points),
                makeChart(container.querySelector('[data-chart="panel"]'), [
                    { label: 'PS1', color: '#198754', field: 'ps1' },
                    { label: 'PS2', color: '#6f42c1', field: 'ps2' }
                ], points),
                makeChart(container.querySelector('[data-chart="motor"]'), [{ label: 'Motor speed', color: '#fd7e14', field: 'motor_speed' }], points)
            ].filter(Boolean) : [];
        }
        container.querySelectorAll('[data-graph-hours]').forEach(function (button) { button.addEventListener('click', function () { redraw(Number(button.dataset.graphHours)); }); });
        redraw(0);
    }

    return { TIME_ZONE: TIME_ZONE, formatTime: formatTime, formatTimestamp: formatTimestamp, normalizePoints: normalizePoints, rangeLabel: rangeLabel, filteredPoints: filteredPoints, shouldIncludeDate: shouldIncludeDate, render: render };
}));
