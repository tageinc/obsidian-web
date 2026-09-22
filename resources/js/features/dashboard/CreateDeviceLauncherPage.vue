<script setup>
import { nextTick, ref } from 'vue';
import CreateDeviceModal from './CreateDeviceModal.vue';

const props = defineProps({ creation: { type: Object, required: true } });
const open = ref(props.creation.initiallyOpen);
const trigger = ref(null);
async function close() {
    open.value = false;
    await nextTick();
    trigger.value?.focus();
}
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="btn btn-primary text-nowrap"
        aria-haspopup="dialog"
        @click="open = true"
    >
        Create +
    </button>
    <CreateDeviceModal v-if="open" :creation="creation" @close="close" />
</template>
