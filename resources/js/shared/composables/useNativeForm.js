import { onBeforeUnmount, onMounted, ref } from 'vue';

// Keep Laravel's native redirects and validation, without sending a second request.
export function useNativeForm() {
    const pending = ref(false);
    function submit(event) {
        if (event.defaultPrevented) return;
        if (pending.value) event.preventDefault();
        else pending.value = true;
    }
    function resume() {
        pending.value = false;
    }
    onMounted(() => window.addEventListener('pageshow', resume));
    onBeforeUnmount(() => window.removeEventListener('pageshow', resume));
    return { pending, submit };
}
