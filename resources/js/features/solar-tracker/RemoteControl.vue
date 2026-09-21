<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { requestJson } from '../../shared/api/client';
const props = defineProps({
    mode: { type: Number, default: 0 },
    motorSpeed: { type: Number, default: 0 },
    serial: { type: String, required: true },
    endpoint: { type: String, required: true },
    csrfToken: { type: String, required: true },
});
const emit = defineEmits(['change']);
const confirmed = ref({ mode: props.mode === 1 ? 1 : 0, motor_speed: props.motorSpeed || 0 });
const pending = ref(null);
const feedback = ref('');
const failed = ref(false);
let alive = true;
onBeforeUnmount(() => {
    alive = false;
});
async function save(mode, speed, toggle = false) {
    if (pending.value || (!toggle && confirmed.value.mode !== 1)) return;
    pending.value = { mode, motor_speed: speed };
    feedback.value = 'Saving…';
    failed.value = false;
    try {
        const result = await requestJson(props.endpoint, {
            method: 'POST',
            csrfToken: props.csrfToken,
            data: { mode, motor_speed: speed, serial_no: props.serial },
        });
        if (
            result?.success !== true ||
            ![0, 1].includes(result.mode) ||
            !Number.isFinite(result.motor_speed)
        )
            throw new Error(
                'Could not confirm the remote control state. Refresh before trying again.',
            );
        if (!alive) return;
        confirmed.value = { mode: result.mode, motor_speed: result.motor_speed };
        emit('change', { ...confirmed.value });
        feedback.value = toggle
            ? `${result.mode ? 'Remote Control' : 'Automatic'} mode saved.`
            : `${result.motor_speed === 0 ? 'Stop' : result.motor_speed > 0 ? 'Up' : 'Down'} command saved.`;
    } catch (error) {
        if (alive) {
            failed.value = true;
            feedback.value = error.message || 'Could not save remote control. Try again.';
        }
    } finally {
        if (alive) pending.value = null;
    }
}
</script>
<template>
    <section class="card mb-3" aria-labelledby="remote-title" :aria-busy="Boolean(pending)">
        <h2 id="remote-title" class="card-header h6">Remote Control</h2>
        <div class="card-body">
            <div class="form-check form-switch mb-2">
                <input
                    id="remote-mode"
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    :checked="(pending || confirmed).mode === 1"
                    :disabled="Boolean(pending)"
                    aria-describedby="remote-help"
                    @change="save($event.target.checked ? 1 : 0, 0, true)"
                /><label class="form-check-label" for="remote-mode">Remote control mode</label>
            </div>
            <p>
                Mode: <strong>{{ confirmed.mode === 1 ? 'Remote Control' : 'Automatic' }}</strong>
            </p>
            <p id="remote-help" class="text-muted small">
                Enable Remote Control to use Up, Stop, and Down. Switching modes resets the manual
                motor command to Stop.
            </p>
            <div role="group" aria-label="Motor controls" class="d-flex gap-2">
                <button
                    v-for="[speed, label] in [
                        [20, 'Up'],
                        [0, 'Stop'],
                        [-20, 'Down'],
                    ]"
                    :key="speed"
                    type="button"
                    class="btn"
                    :class="speed === 0 ? 'btn-secondary' : 'btn-primary'"
                    :disabled="Boolean(pending) || confirmed.mode !== 1"
                    :aria-pressed="confirmed.mode === 1 && confirmed.motor_speed === speed"
                    @click="save(1, speed)"
                >
                    {{ label }}
                </button>
            </div>
            <p class="small mt-2 mb-0" :role="failed ? 'alert' : 'status'" aria-live="polite">
                {{ feedback }}
            </p>
        </div>
    </section>
</template>
