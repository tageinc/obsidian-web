<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { normalizePoints, filterRange, rangeLabel, chartOptions } from './timeSeries';

const props = defineProps({
    points: { type: Array, default: () => [] },
    active: { type: Boolean, default: true },
});
const hours = ref(0);
const metric = ref('temperature');
const canvas = ref(null);
const state = ref('ready');
const metrics = {
    temperature: {
        title: 'Temperature',
        unit: '°C',
        series: [['temp', 'Temperature (°C)', '#275bb5']],
    },
    panels: {
        title: 'Panel sensors',
        unit: 'PS1 and PS2',
        series: [
            ['ps1', 'PS1', '#198754'],
            ['ps2', 'PS2', '#6f42c1'],
        ],
    },
    motor: {
        title: 'Motor speed',
        unit: 'Reported speed',
        series: [['motor_speed', 'Motor speed', '#ad5608']],
    },
};
const selected = computed(() => metrics[metric.value]);
const normalized = computed(() => normalizePoints(props.points));
const visible = computed(() => filterRange(normalized.value, hours.value));
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
        chart = new Chart(canvas.value, chartOptions(visible.value, selected.value.series));
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
            <div class="history-toolbar">
                <div class="history-metric">
                    <label for="history-metric">Measurement</label>
                    <select id="history-metric" v-model="metric" class="form-select">
                        <option v-for="(value, key) in metrics" :key="key" :value="key">
                            {{ value.title }}
                        </option>
                    </select>
                </div>
                <div class="history-range" role="group" aria-label="Graph time range">
                    <button
                        v-for="range in [1, 12, 24, 0]"
                        :key="range"
                        type="button"
                        :aria-pressed="hours === range"
                        @click="hours = range"
                    >
                        {{ range ? range + ' hour' + (range === 1 ? '' : 's') : 'All' }}
                    </button>
                </div>
            </div>
            <div class="history-plot" :aria-busy="state === 'loading'">
                <div class="plot-heading">
                    <h3>
                        {{ selected.title }} <span>{{ selected.unit }}</span>
                    </h3>
                    <p aria-live="polite">{{ visible.length }} raw readings</p>
                </div>
                <p v-if="!visible.length" class="history-empty" role="status">
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
                            ', scatter plot with linear trend lines, ' +
                            visible.length +
                            ' raw readings. ' +
                            rangeLabel(visible)
                        "
                    />
                </div>
            </div>
            <footer class="history-footer">
                <p>{{ rangeLabel(visible) }}</p>
                <p>
                    Points show raw readings. Dashed lines fit all valid readings in the selected
                    range; a trend needs at least two distinct timestamps.
                </p>
                <p>Ranges end at the latest reading. Missing measurements are omitted.</p>
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
    justify-content: space-between;
    align-items: end;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e0e6ee;
}
.history-metric {
    min-width: 200px;
}
.history-metric label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #5c6879;
    display: block;
    margin-bottom: 0.4rem;
}
.history-metric select {
    font-size: 0.9rem;
}
.history-range {
    display: flex;
    padding: 0.2rem;
    background: #f0f3f7;
    border: 1px solid #e0e6ee;
    border-radius: 0.5rem;
}
.history-range button {
    border: 0;
    background: none;
    color: #526176;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.5rem 0.8rem;
    border-radius: 0.3rem;
}
.history-range button[aria-pressed='true'] {
    color: #084298;
    background: #fff;
    box-shadow: 0 1px 4px #17253d20;
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
@media (max-width: 575px) {
    .history-toolbar,
    .history-plot,
    .history-footer {
        padding: 1rem;
    }
    .history-metric {
        width: 100%;
    }
    .history-range {
        width: 100%;
        justify-content: space-between;
    }
    .history-range button {
        padding: 0.5rem 0.6rem;
    }
    .chart-container {
        height: 280px;
    }
}
</style>
