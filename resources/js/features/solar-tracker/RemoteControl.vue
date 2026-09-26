<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { requestJson } from '../../shared/api/client';
const props = defineProps({
    mode: { type: Number, default: 0 },
    motorSpeed: { type: Number, default: 0 },
    serial: { type: String, required: true },
    endpoint: { type: String, required: true },
    csrfToken: { type: String, required: true },
});
const emit = defineEmits(['change']);
const confirmed = ref({
    mode: props.mode === 1 ? 1 : 0,
    motor_speed: Number.isFinite(props.motorSpeed) ? props.motorSpeed : 0,
});
const draftSpeed = ref(sliderSpeed(confirmed.value.motor_speed));
const speedInput = ref(null);
const pending = ref(null);
const feedback = ref('');
const failed = ref(false);
const speedTicks = Array.from({ length: 21 }, (_, index) => -100 + index * 10);
let alive = true;
onBeforeUnmount(() => {
    alive = false;
});
function sliderSpeed(speed) {
    const value = Number(speed);
    return Number.isFinite(value) ? Math.max(-100, Math.min(100, Math.round(value / 10) * 10)) : 0;
}
function speedLabel(speed) {
    return speed === 0 ? 'Stop (0)' : String(speed);
}
function previewSpeed(event) {
    if (pending.value || confirmed.value.mode !== 1) return;
    draftSpeed.value = sliderSpeed(event.target.value);
}
function commitSpeed(event) {
    const speed = sliderSpeed(event.target.value);
    if (pending.value || confirmed.value.mode !== 1) return;
    draftSpeed.value = speed;
    if (speed !== confirmed.value.motor_speed) return save(1, speed);
}
async function save(mode, speed, toggle = false) {
    if (pending.value || (!toggle && confirmed.value.mode !== 1)) return;
    const slider = speedInput.value;
    const restoreFocus = slider === slider?.ownerDocument.activeElement;
    pending.value = { mode, motor_speed: speed };
    draftSpeed.value = sliderSpeed(speed);
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
            !Number.isFinite(result.motor_speed) ||
            result.motor_speed < -100 ||
            result.motor_speed > 100
        )
            throw new Error(
                'Could not confirm the remote control state. Refresh before trying again.',
            );
        if (!alive) return;
        confirmed.value = { mode: result.mode, motor_speed: result.motor_speed };
        emit('change', { ...confirmed.value });
        feedback.value = toggle
            ? `${result.mode ? 'Remote Control' : 'Automatic'} mode saved.`
            : `Motor speed saved: ${speedLabel(result.motor_speed)}.`;
    } catch (error) {
        if (alive) {
            failed.value = true;
            feedback.value = error.message || 'Could not save remote control. Try again.';
        }
    } finally {
        if (alive) {
            draftSpeed.value = sliderSpeed(confirmed.value.motor_speed);
            pending.value = null;
            await nextTick();
            if (
                alive &&
                restoreFocus &&
                slider.isConnected &&
                !slider.disabled &&
                slider.ownerDocument.activeElement === slider.ownerDocument.body
            ) {
                slider.focus();
            }
        }
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
                Enable Remote Control to set the motor speed. Switching modes resets the manual
                motor command to Stop (0).
            </p>
            <div class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
                <label class="form-label mb-0" for="remote-motor-speed">Motor speed</label>
                <output id="remote-speed-value" for="remote-motor-speed" class="fw-semibold">
                    {{ speedLabel(draftSpeed) }}
                </output>
            </div>
            <input
                id="remote-motor-speed"
                ref="speedInput"
                class="form-range"
                type="range"
                min="-100"
                max="100"
                step="10"
                :value="draftSpeed"
                :disabled="Boolean(pending) || confirmed.mode !== 1"
                :aria-valuetext="speedLabel(draftSpeed)"
                aria-describedby="remote-speed-help remote-saved-speed"
                @input="previewSpeed"
                @change="commitSpeed"
            />
            <div class="motor-speed-scale" aria-hidden="true">
                <span
                    v-for="tick in speedTicks"
                    :key="tick"
                    class="motor-speed-tick"
                    :class="{ 'motor-speed-tick-major': tick % 20 === 0 }"
                    :style="{ left: `${(tick + 100) / 2}%` }"
                >
                    <span v-if="tick % 20 === 0" class="motor-speed-tick-label">{{ tick }}</span>
                </span>
            </div>
            <p id="remote-speed-help" class="small text-muted mt-3 mb-2">
                Drag and release to save, or use the arrow keys. Set a value from -100 to 100 in
                steps of 10; 0 stops the motor.
            </p>
            <p id="remote-saved-speed" class="small mb-0">
                Saved motor speed: {{ speedLabel(confirmed.motor_speed) }}
            </p>
            <p class="small mt-2 mb-0" :role="failed ? 'alert' : 'status'" aria-live="polite">
                {{ feedback }}
            </p>
        </div>
    </section>
</template>

<style scoped>
.motor-speed-scale {
    position: relative;
    height: 2.1rem;
    margin: -0.25rem 0.5rem 0;
}
.motor-speed-tick {
    position: absolute;
    top: 0;
    width: 0;
    height: 0.35rem;
    border-left: 1px solid #adb5bd;
}
.motor-speed-tick-major {
    height: 0.5rem;
    border-left-color: #6c757d;
}
.motor-speed-tick-label {
    position: absolute;
    top: 0.65rem;
    left: 0;
    color: #6c757d;
    font-size: 0.7rem;
    line-height: 1;
    transform: translateX(-50%);
    white-space: nowrap;
}
.motor-speed-tick:first-child .motor-speed-tick-label {
    transform: none;
}
.motor-speed-tick:last-child .motor-speed-tick-label {
    transform: translateX(-100%);
}
</style>
