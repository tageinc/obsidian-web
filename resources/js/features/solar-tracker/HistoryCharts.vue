<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { normalizePoints, filterRange, rangeLabel, chartOptions } from './timeSeries';
const props = defineProps({ points: { type: Array, default: () => [] } });
const hours = ref(0);
const canvases = ref([]);
const state = ref('loading');
const visible = computed(() => filterRange(normalizePoints(props.points), hours.value));
let charts = [];
let Chart;
let alive = true;
let revision = 0;
function destroy() {
    charts.forEach((chart) => chart.destroy());
    charts = [];
}
async function draw() {
    const current = ++revision;
    destroy();
    if (!visible.value.length) {
        state.value = 'ready';
        return;
    }
    state.value = 'loading';
    try {
        Chart ||= (await import('chart.js/auto')).default;
        await nextTick();
        if (!alive || current !== revision) return;
        const series = [
            [['temp', 'Temperature (°C)', '#0d6efd']],
            [
                ['ps1', 'PS1', '#198754'],
                ['ps2', 'PS2', '#6f42c1'],
            ],
            [['motor_speed', 'Motor speed', '#fd7e14']],
        ];
        for (const [index, metrics] of series.entries()) {
            charts.push(new Chart(canvases.value[index], chartOptions(visible.value, metrics)));
        }
        state.value = 'ready';
    } catch {
        if (alive && current === revision) {
            destroy();
            state.value = 'error';
        }
    }
}
watch(visible, draw);
onMounted(draw);
onBeforeUnmount(() => {
    alive = false;
    revision++;
    destroy();
});
</script>
<template>
    <section class="card mb-3" aria-labelledby="history-title">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 id="history-title" class="h6 mb-0">
                Solar Tracker History <small class="text-muted">Raw readings</small>
            </h2>
            <div class="btn-group btn-group-sm" role="group" aria-label="Graph time range">
                <button
                    v-for="range in [1, 12, 24, 0]"
                    :key="range"
                    type="button"
                    class="btn btn-outline-secondary"
                    :aria-pressed="hours === range"
                    @click="hours = range"
                >
                    {{ range ? `${range} hour${range === 1 ? '' : 's'}` : 'All' }}
                </button>
            </div>
        </div>
        <div class="card-body" :aria-busy="state === 'loading'">
            <p class="text-muted small" aria-live="polite">{{ rangeLabel(visible) }}</p>
            <p v-if="!visible.length">No telemetry was recorded for this time range.</p>
            <p v-else class="small">
                {{ visible.length }} raw readings. Missing measurements appear as gaps. Ranges end
                at the latest recorded reading.
            </p>
            <p v-if="state === 'loading'" role="status">Loading charts…</p>
            <div v-if="state === 'error'" role="alert">
                Charts could not load.
                <button type="button" class="btn btn-link" @click="draw">Try again</button>
            </div>
            <div v-show="visible.length" class="row g-3">
                <div
                    v-for="(title, index) in [
                        'Temperature (°C)',
                        'Panel sensors PS1 and PS2',
                        'Motor speed',
                    ]"
                    :key="title"
                    :class="index === 0 ? 'col-12' : 'col-md-6'"
                >
                    <h3 class="h6">{{ title }}</h3>
                    <div style="height: 260px">
                        <canvas
                            :ref="(element) => (canvases[index] = element)"
                            role="img"
                            :aria-label="`${title}, ${visible.length} raw readings. ${rangeLabel(visible)}`"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
