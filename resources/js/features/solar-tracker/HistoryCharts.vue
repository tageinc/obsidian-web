<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { normalizePoints, rangeLabel, chartOptions, formatTimestamp } from './timeSeries';
import { downloadHistoryReport } from './historyReport';
import { celsiusToFahrenheit } from './sensorReadings';

import {
    lastDay,
    datetimeInput,
    parseDatetime,
    calendarRange,
    shiftRange,
    pointsInRange,
} from './historyRange';

const props = defineProps({
    points: { type: Array, default: () => [] },
    active: { type: Boolean, default: true },
    reportUrl: { type: String, default: '' },
    refreshedAt: { type: Number, default: null },
});
const reportBusy = ref(false);
const reportError = ref('');
const reportMessage = ref('');
let reportController;
async function createReport() {
    if (reportBusy.value || rangeError.value || !props.reportUrl) return;
    reportBusy.value = true;
    reportError.value = '';
    reportMessage.value = '';
    reportController = new AbortController();
    try {
        await downloadHistoryReport(props.reportUrl, range.value, reportController.signal);
        reportMessage.value = 'Your report download has started. It includes all measurements.';
    } catch (error) {
        if (error.name !== 'AbortError') reportError.value = error.message;
    } finally {
        reportBusy.value = false;
    }
}
const initialRange = lastDay();
const defaultRange = ref(initialRange);
const followingNow = ref(true);
const view = ref('day');
const range = ref(initialRange);
const from = ref(datetimeInput(initialRange.start));
const to = ref(datetimeInput(initialRange.end));
const selectedDate = ref(datetimeInput(initialRange.end).slice(0, 10));
const rangeError = ref('');
const customView = computed(() => view.value === 'custom');
const periodNavigation = computed(() => !customView.value && view.value !== 'all');
const hasRangeFilters = computed(
    () =>
        view.value !== 'day' ||
        !!rangeError.value ||
        range.value.start !== defaultRange.value.start ||
        range.value.end !== defaultRange.value.end,
);
const rangeSummary = computed(
    () => `${formatTimestamp(range.value.start)} \u2013 ${formatTimestamp(range.value.end)}`,
);
function setRange(value) {
    range.value = value;
    from.value = datetimeInput(value.start);
    to.value = datetimeInput(value.end);
    selectedDate.value = datetimeInput(value.start).slice(0, 10);
    rangeError.value = '';
}
function chooseView() {
    if (view.value === 'custom') {
        editRange();
        return;
    }
    rangeError.value = '';
    if (view.value === 'day') resetRange();
    else if (view.value === 'all') {
        followingNow.value = false;
        if (normalized.value.length)
            setRange({
                start: normalized.value[0].epoch_ms,
                end: normalized.value.at(-1).epoch_ms,
            });
    } else setRange(calendarRange(view.value, range.value.end));
}
function editPeriod() {
    followingNow.value = false;
    const anchor = parseDatetime(
        view.value === 'day' ? to.value : `${selectedDate.value}T12:00:00`,
        'end',
    );
    rangeError.value = anchor === null ? 'Enter a valid date and time in Pacific Time.' : '';
    if (rangeError.value) return;
    setRange(view.value === 'day' ? lastDay(anchor) : calendarRange(view.value, anchor));
}
function editRange() {
    followingNow.value = false;
    view.value = 'custom';
    const start = parseDatetime(from.value);
    const end = parseDatetime(to.value, 'end');
    rangeError.value =
        start === null || end === null
            ? 'Enter valid From and To datetimes in Pacific Time. Skipped daylight-saving times are not valid.'
            : start > end
              ? 'From datetime must be before or equal to To datetime.'
              : '';
    if (!rangeError.value && (range.value.start !== start || range.value.end !== end)) {
        range.value = { start, end };
    }
}
function shift(direction) {
    followingNow.value = false;
    setRange(shiftRange(range.value, view.value, direction));
}
function resetRange() {
    followingNow.value = true;
    view.value = 'day';
    defaultRange.value = lastDay();
    setRange(defaultRange.value);
}
function goToday() {
    if (!periodNavigation.value) return;
    followingNow.value = true;
    if (view.value === 'day') resetRange();
    else setRange(calendarRange(view.value, Date.now()));
}
const metric = ref('temperature');
const canvas = ref(null);
const state = ref('ready');
const metrics = {
    temperature: {
        title: 'Temperature',
        unit: '°F',
        series: [['temp', 'Temperature (°F)', '#275bb5']],
    },
    panels: {
        title: 'Panel sensors',
        unit: 'PS1 and PS2',
        series: [
            ['ps1', 'PS1', '#198754'],
            ['ps2', 'PS2', '#6f42c1'],
        ],
    },
    pds: {
        title: 'PDS',
        unit: 'Reported reading',
        series: [['pds', 'PDS', '#087e8b']],
    },
    ps_avg: {
        title: 'PS average',
        unit: 'Reported average',
        series: [['ps_avg', 'PS average', '#7c3e94']],
    },
    motor: {
        title: 'Motor speed',
        unit: 'Reported speed',
        series: [['motor_speed', 'Motor speed', '#ad5608']],
    },
};
const selected = computed(() => metrics[metric.value]);
const showZeroReference = computed(() => metric.value === 'pds' || metric.value === 'motor');
const normalized = computed(() =>
    normalizePoints(
        props.points.map((point) =>
            point
                ? {
                      ...point,
                      temp: Object.hasOwn(point, 'temp_f')
                          ? point.temp_f
                          : celsiusToFahrenheit(point.temp),
                  }
                : point,
        ),
    ),
);
watch([normalized, () => props.refreshedAt], () => {
    if (view.value === 'all' && normalized.value.length) {
        setRange({
            start: normalized.value[0].epoch_ms,
            end: normalized.value.at(-1).epoch_ms,
        });
    } else if (followingNow.value && props.refreshedAt !== null && !rangeError.value) {
        defaultRange.value = lastDay(props.refreshedAt);
        setRange(
            view.value === 'day'
                ? defaultRange.value
                : calendarRange(view.value, props.refreshedAt),
        );
    }
});
const visible = computed(() =>
    rangeError.value ? [] : pointsInRange(normalized.value, range.value),
);
let chart;
let Chart;
let alive = true;
let revision = 0;

function destroy() {
    chart?.destroy();
    chart = null;
}
async function draw() {
    const current = ++revision;
    destroy();
    if (!props.active || !visible.value.length) {
        state.value = 'ready';
        return;
    }
    state.value = 'loading';
    try {
        Chart ||= (await import('chart.js/auto')).default;
        await nextTick();
        if (!alive || current !== revision || !props.active) return;
        chart = new Chart(
            canvas.value,
            chartOptions(visible.value, selected.value.series, range.value),
        );
        state.value = 'ready';
    } catch {
        if (alive && current === revision) {
            destroy();
            state.value = 'error';
        }
    }
}
watch([visible, metric, () => props.active], draw);
onMounted(draw);
onBeforeUnmount(() => {
    reportController?.abort();
    alive = false;
    revision++;
    destroy();
});
</script>

<template>
    <section class="history-section" aria-labelledby="history-title">
        <header class="history-heading">
            <div>
                <h2 id="history-title">Reading history</h2>
                <p>Compare raw readings with their overall linear trend.</p>
            </div>
            <span class="history-badge">Raw data · Pacific Time</span>
        </header>
        <div class="history-surface">
            <div class="history-toolbar" role="group" aria-label="Graph time range">
                <div class="history-controls-header">
                    <div>
                        <h3>Time period</h3>
                        <p class="history-range-display" aria-live="polite">
                            {{ rangeError ? 'Invalid time range' : rangeSummary }}
                        </p>
                    </div>
                    <div class="history-date-nav">
                        <button
                            v-if="reportUrl"
                            type="button"
                            class="history-nav-icon"
                            aria-label="Download history report"
                            title="Download PDF report with all measurements"
                            :disabled="reportBusy || !!rangeError"
                            :aria-busy="reportBusy"
                            @click="createReport"
                        >
                            <span
                                v-if="reportBusy"
                                class="spinner-border spinner-border-sm"
                                aria-hidden="true"
                            />
                            <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M7 8V3h10v5M7 17H4V9h16v8h-3M7 14h10v7H7zM17 11h.01" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="history-nav-icon"
                            aria-label="Previous time range"
                            title="Previous time range"
                            :disabled="!periodNavigation || !!rangeError"
                            @click="shift(-1)"
                        >
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="m12 4-6 6 6 6" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="history-nav-icon"
                            aria-label="Next time range"
                            title="Next time range"
                            :disabled="!periodNavigation || !!rangeError"
                            @click="shift(1)"
                        >
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="m8 4 6 6-6 6" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            :disabled="!periodNavigation"
                            @click="goToday"
                        >
                            Today
                        </button>
                    </div>
                </div>
                <div class="history-filter-group">
                    <div class="history-filter-title">Filters</div>
                    <div class="history-filter-row">
                        <div class="history-filter-field">
                            <label for="history-view">View</label>
                            <select
                                id="history-view"
                                v-model="view"
                                class="form-select form-select-sm"
                                @change="chooseView"
                            >
                                <option value="day">24 hours</option>
                                <option value="week">Week</option>
                                <option value="month">Month</option>
                                <option value="all" :disabled="!normalized.length">
                                    Available history
                                </option>
                                <option value="custom">Custom span</option>
                            </select>
                        </div>
                        <div v-if="view === 'day'" class="history-filter-field">
                            <label for="history-ending">Ending at</label>
                            <input
                                id="history-ending"
                                v-model="to"
                                type="datetime-local"
                                step="1"
                                class="form-control form-control-sm"
                                aria-describedby="history-timezone history-range-error"
                                :aria-invalid="!!rangeError"
                                @input="editPeriod"
                            />
                        </div>
                        <div
                            v-else-if="view === 'week' || view === 'month'"
                            class="history-filter-field"
                        >
                            <label for="history-period-date">{{
                                view === 'week' ? 'Week' : 'Month'
                            }}</label>
                            <input
                                id="history-period-date"
                                v-model="selectedDate"
                                type="date"
                                class="form-control form-control-sm"
                                aria-describedby="history-timezone history-range-error"
                                :aria-invalid="!!rangeError"
                                @input="editPeriod"
                            />
                        </div>
                        <template v-else-if="customView">
                            <div class="history-filter-field">
                                <label for="history-from">From</label>
                                <input
                                    id="history-from"
                                    v-model="from"
                                    type="datetime-local"
                                    step="1"
                                    class="form-control form-control-sm"
                                    aria-describedby="history-timezone history-range-error"
                                    :aria-invalid="!!rangeError"
                                    @input="editRange"
                                />
                            </div>
                            <div class="history-filter-field">
                                <label for="history-to">To</label>
                                <input
                                    id="history-to"
                                    v-model="to"
                                    type="datetime-local"
                                    step="1"
                                    class="form-control form-control-sm"
                                    aria-describedby="history-timezone history-range-error"
                                    :aria-invalid="!!rangeError"
                                    @input="editRange"
                                />
                            </div>
                        </template>
                    </div>
                    <div class="history-filter-row">
                        <div class="history-filter-field history-metric">
                            <label for="history-metric">Measurement</label>
                            <select
                                id="history-metric"
                                v-model="metric"
                                class="form-select form-select-sm"
                            >
                                <option v-for="(value, key) in metrics" :key="key" :value="key">
                                    {{ value.title }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="btn btn-link btn-sm history-clear"
                            :disabled="!hasRangeFilters"
                            title="Reset to the last 24 hours"
                            @click="resetRange"
                        >
                            Clear
                        </button>
                    </div>
                </div>
                <small id="history-timezone">Pacific Time (PST/PDT)</small>
                <p id="history-range-error" class="text-danger" role="alert">{{ rangeError }}</p>
                <p v-if="reportError" class="history-report-feedback text-danger" role="alert">
                    {{ reportError }}
                </p>
                <p
                    v-else-if="reportBusy || reportMessage"
                    class="history-report-feedback"
                    role="status"
                >
                    {{
                        reportBusy
                            ? 'Creating your PDF report with all measurements…'
                            : reportMessage
                    }}
                </p>
            </div>
        </div>
        <div class="history-surface history-chart-card">
            <div class="history-plot" :aria-busy="state === 'loading'">
                <div class="plot-heading">
                    <h3>
                        {{ selected.title }} <span>{{ selected.unit }}</span>
                    </h3>
                    <p aria-live="polite">{{ visible.length }} raw readings</p>
                </div>
                <p v-if="!rangeError && !visible.length" class="history-empty" role="status">
                    No telemetry was recorded for this time range.
                </p>
                <p v-if="state === 'loading'" role="status">Loading chart…</p>
                <div v-if="state === 'error'" role="alert">
                    The chart could not load.
                    <button type="button" class="btn btn-link" @click="draw">Try again</button>
                </div>
                <div v-show="visible.length" class="chart-container">
                    <canvas
                        ref="canvas"
                        role="img"
                        :aria-label="
                            selected.title +
                            (metric === 'temperature' ? ' in degrees Fahrenheit' : '') +
                            ', scatter plot with linear trend lines, ' +
                            visible.length +
                            ' raw readings. ' +
                            (showZeroReference ? 'The y-axis includes zero. ' : '') +
                            rangeLabel(visible)
                        "
                    />
                </div>
            </div>
            <footer class="history-footer">
                <p v-if="!rangeError">Selected range: {{ rangeSummary }} (Pacific Time)</p>
                <p>Recorded readings: {{ rangeLabel(visible) }}</p>
                <p>
                    Points show raw readings. Dashed lines fit all valid readings in the selected
                    range; a trend needs at least two distinct timestamps.
                </p>
                <p v-if="showZeroReference">
                    The y-axis includes zero so you can see readings cross zero.
                </p>
                <p>
                    The default is the last 24 hours ending now. Ranges filter the latest 9,000
                    available readings. Missing measurements are omitted.
                </p>
            </footer>
        </div>
    </section>
</template>

<style scoped>
.history-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}
.history-heading h2 {
    font-size: 1.05rem;
    font-weight: 650;
    margin: 0 0 0.4rem;
}
.history-heading p {
    font-size: 0.85rem;
    color: #5c6879;
    margin: 0;
}
.history-badge {
    font-size: 0.75rem;
    color: #43546d;
    background: #eaf0f8;
    padding: 0.4rem 0.65rem;
    border-radius: 0.4rem;
}
.history-surface {
    border: 1px solid #e0e6ee;
    background: #fff;
    border-radius: 0.8rem;
    overflow: hidden;
}
.history-toolbar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
}
.history-chart-card {
    margin-top: 1.25rem;
}
.history-report-feedback {
    margin: 0;
    font-size: 0.85rem;
}
.history-controls-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.history-controls-header h3 {
    font-size: 0.95rem;
    font-weight: 700;
    margin: 0 0 0.25rem;
}
.history-range-display {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: #35445b;
}
.history-date-nav {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}
.history-nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 0.3rem;
    background: transparent;
    color: #526176;
}
.history-nav-icon svg {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.history-nav-icon:hover:not(:disabled) {
    background: #eaf0f8;
    color: #084298;
}
.history-nav-icon:focus-visible {
    outline: 2px solid #275bb5;
    outline-offset: 2px;
}
.history-nav-icon:disabled {
    opacity: 0.4;
}
.history-filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-width: 0;
}
.history-filter-title {
    color: #5c6879;
    font-size: 0.72rem;
    font-weight: 800;
    line-height: 1;
    text-transform: uppercase;
}
.history-filter-row {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.history-filter-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 150px;
    max-width: 100%;
}
.history-filter-field label {
    color: #5c6879;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}
.history-filter-field input {
    min-width: 0;
    max-width: 100%;
}
.history-metric {
    min-width: 250px;
}
.history-clear {
    padding-left: 0;
    padding-right: 0;
    text-decoration: none;
}
#history-timezone {
    font-size: 0.75rem;
    color: #5c6879;
}
#history-range-error {
    margin: 0;
    font-size: 0.8rem;
}
#history-range-error:empty {
    display: none;
}
.history-plot {
    padding: 1.5rem;
}
.plot-heading {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}
.plot-heading h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
}
.plot-heading h3 span {
    color: #5c6879;
    font-size: 0.75rem;
    font-weight: 400;
    margin-left: 0.5rem;
}
.plot-heading p {
    font-size: 0.75rem;
    color: #5c6879;
    margin: 0;
}
.chart-container {
    height: 340px;
}
.history-empty {
    padding: 4rem 1rem;
    text-align: center;
    color: #5c6879;
}
.history-footer {
    border-top: 1px solid #e0e6ee;
    padding: 1rem 1.5rem;
    color: #5c6879;
    font-size: 0.75rem;
    line-height: 1.6;
}
.history-footer p {
    margin: 0;
}
.history-footer p + p {
    margin-top: 0.3rem;
}
@media (max-width: 767px) {
    .history-controls-header {
        flex-direction: column;
        align-items: stretch;
    }
    .history-date-nav {
        width: 100%;
    }
    .history-filter-row {
        align-items: stretch;
    }
    .history-filter-field {
        width: 100%;
        min-width: 0;
    }
    .history-date-nav button,
    .history-filter-field input,
    .history-filter-field select {
        min-height: 44px;
        min-width: 44px;
    }
    .history-clear {
        align-self: flex-start;
        min-height: 44px;
    }
    .history-toolbar,
    .history-plot,
    .history-footer {
        padding: 1rem;
    }
    .chart-container {
        height: 280px;
    }
}
</style>
