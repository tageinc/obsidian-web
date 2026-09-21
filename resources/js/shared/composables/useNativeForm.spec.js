import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { useNativeForm } from './useNativeForm.js';

afterEach(() => vi.restoreAllMocks());

describe('native form lifecycle', () => {
    it('does not lock a canceled submission and removes its browser history listener on unmount', () => {
        let state;
        const add = vi.spyOn(window, 'addEventListener');
        const remove = vi.spyOn(window, 'removeEventListener');
        const wrapper = mount(
            defineComponent({
                setup() {
                    state = useNativeForm();
                    return () => h('form');
                },
            }),
        );
        const canceled = new Event('submit', { cancelable: true });
        canceled.preventDefault();
        state.submit(canceled);
        expect(state.pending.value).toBe(false);
        const listener = add.mock.calls.find(([name]) => name === 'pageshow')[1];
        wrapper.unmount();
        expect(remove).toHaveBeenCalledWith('pageshow', listener);
    });
});
