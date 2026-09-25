import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { requestJson } from '../../shared/api/client';
import { formatTimestamp } from './timeSeries';

const interval = 60000;

export function useDeviceTelemetry({ url, deviceKey, initialStatus, initialPoints }) {
    const status = ref(initialStatus());
    const points = ref(initialPoints());
    const refreshedAt = ref(null);
    const phase = ref('waiting');
    const hidden = ref(document.hidden);
    let mounted = false;
    let timer;
    let controller;
    let nextAt = 0;
    let generation = 0;

    const message = computed(() => {
        if (!url()) return '';
        if (phase.value === 'stopped')
            return 'Automatic updates stopped. Refresh this page to restore access.';
        if (hidden.value) return 'Automatic updates pause while this tab is hidden.';
        if (phase.value === 'loading') return 'Checking for new readings…';
        if (phase.value === 'error')
            return 'Could not refresh readings. Showing the last available data; retrying in one minute.';
        return refreshedAt.value === null
            ? 'Readings update automatically every minute.'
            : `Readings checked ${formatTimestamp(refreshedAt.value)}. Updates every minute.`;
    });

    function cancel() {
        clearTimeout(timer);
        generation++;
        controller?.abort();
        controller = null;
    }

    function schedule() {
        clearTimeout(timer);
        if (!mounted || hidden.value || !url() || phase.value === 'stopped' || controller) return;
        timer = setTimeout(refresh, Math.max(0, nextAt - Date.now()));
    }

    async function refresh() {
        if (!mounted || hidden.value || controller || phase.value === 'stopped') return;
        const current = ++generation;
        controller = new AbortController();
        nextAt = Date.now() + interval;
        phase.value = 'loading';
        try {
            const snapshot = await requestJson(url(), { signal: controller.signal });
            if (!mounted || current !== generation) return;
            if (
                !snapshot?.status ||
                typeof snapshot.status !== 'object' ||
                Array.isArray(snapshot.status) ||
                !Array.isArray(snapshot.graph?.points)
            )
                throw new Error('Invalid telemetry response');
            status.value = snapshot.status;
            points.value = snapshot.graph.points;
            refreshedAt.value = Date.now();
            phase.value = 'waiting';
        } catch (error) {
            if (!mounted || current !== generation || error.name === 'AbortError') return;
            phase.value = [401, 403, 404, 410, 419].includes(error.status) ? 'stopped' : 'error';
        } finally {
            if (current === generation) {
                controller = null;
                schedule();
            }
        }
    }

    function visibilityChanged() {
        hidden.value = document.hidden;
        if (hidden.value) {
            cancel();
            if (phase.value === 'loading') phase.value = 'waiting';
        } else schedule();
    }

    watch([url, deviceKey, initialStatus, initialPoints], () => {
        cancel();
        status.value = initialStatus();
        points.value = initialPoints();
        refreshedAt.value = null;
        phase.value = 'waiting';
        nextAt = Date.now() + interval;
        schedule();
    });
    onMounted(() => {
        mounted = true;
        nextAt = Date.now() + interval;
        document.addEventListener('visibilitychange', visibilityChanged);
        schedule();
    });
    onBeforeUnmount(() => {
        mounted = false;
        cancel();
        document.removeEventListener('visibilitychange', visibilityChanged);
    });

    return { status, points, refreshedAt, message };
}
