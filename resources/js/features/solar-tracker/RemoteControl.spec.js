import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import RemoteControl from './RemoteControl.vue';
import { requestJson } from '../../shared/api/client';
vi.mock('../../shared/api/client', () => ({ requestJson: vi.fn() }));
const props = {
    mode: 0,
    serial: 'local-test',
    endpoint: '/update-solar-tracker',
    csrfToken: 'test-only',
};
beforeEach(() => vi.resetAllMocks());
it('defaults to Stop without commands on mount or unmount and disables automatic control', async () => {
    const wrapper = mount(RemoteControl, { props });
    const slider = wrapper.get('#remote-motor-speed');
    expect(slider.element.disabled).toBe(true);
    expect(slider.element.value).toBe('0');
    expect(slider.attributes()).toMatchObject({
        type: 'range',
        min: '-100',
        max: '100',
        step: '10',
    });
    expect(wrapper.findAll('button')).toHaveLength(0);
    await slider.trigger('change');
    wrapper.unmount();
    expect(requestJson).not.toHaveBeenCalled();
});
it('loads saved speed and preserves off-step legacy commands without rewriting them', () => {
    const wrapper = mount(RemoteControl, { props: { ...props, mode: 1, motorSpeed: 25 } });
    expect(wrapper.get('#remote-motor-speed').element.value).toBe('30');
    expect(wrapper.get('#remote-saved-speed').text()).toBe('Saved motor speed: Up (25)');
    expect(requestJson).not.toHaveBeenCalled();
});
it('previews dragging without saving, commits once, and skips unchanged commands', async () => {
    requestJson.mockResolvedValue({ success: true, mode: 1, motor_speed: -30 });
    const wrapper = mount(RemoteControl, { props: { ...props, mode: 1, motorSpeed: 40 } });
    const slider = wrapper.get('#remote-motor-speed');
    expect(slider.element.value).toBe('40');
    slider.element.value = '-30';
    await slider.trigger('input');
    expect(wrapper.get('#remote-speed-value').text()).toBe('Down (-30)');
    expect(slider.attributes('aria-valuetext')).toBe('Down (-30)');
    expect(wrapper.get('#remote-saved-speed').text()).toContain('Up (40)');
    expect(requestJson).not.toHaveBeenCalled();
    await slider.trigger('change');
    await flushPromises();
    expect(requestJson).toHaveBeenCalledWith(props.endpoint, {
        method: 'POST',
        csrfToken: props.csrfToken,
        data: { mode: 1, motor_speed: -30, serial_no: props.serial },
    });
    expect(wrapper.get('#remote-saved-speed').text()).toContain('Down (-30)');
    expect(wrapper.get('[role="status"]').text()).toBe('Motor speed saved: Down (-30).');
    expect(wrapper.emitted('change')).toEqual([[{ mode: 1, motor_speed: -30 }]]);
    await slider.trigger('change');
    expect(requestJson).toHaveBeenCalledTimes(1);
});
it.each([-100, -10, 0, 10, 100])(
    'saves a committed speed of %i as a numeric command',
    async (speed) => {
        requestJson.mockResolvedValue({ success: true, mode: 1, motor_speed: speed });
        const wrapper = mount(RemoteControl, { props: { ...props, mode: 1, motorSpeed: 20 } });
        await wrapper.get('#remote-motor-speed').setValue(String(speed));
        await flushPromises();
        expect(requestJson).toHaveBeenCalledWith(
            props.endpoint,
            expect.objectContaining({
                data: { mode: 1, motor_speed: speed, serial_no: props.serial },
            }),
        );
        expect(wrapper.get('#remote-motor-speed').element.value).toBe(String(speed));
    },
);
it('resets speed to Stop on mode changes and blocks concurrent commands', async () => {
    let complete;
    requestJson.mockImplementation(
        () =>
            new Promise((resolve) => {
                complete = resolve;
            }),
    );
    const wrapper = mount(RemoteControl, { props });
    const toggle = wrapper.get('#remote-mode');
    const slider = wrapper.get('#remote-motor-speed');
    await toggle.setValue(true);
    expect(requestJson).toHaveBeenCalledWith(
        props.endpoint,
        expect.objectContaining({ data: { mode: 1, motor_speed: 0, serial_no: props.serial } }),
    );
    expect(toggle.element.disabled).toBe(true);
    expect(slider.element.disabled).toBe(true);
    await slider.trigger('change');
    await toggle.trigger('change');
    expect(requestJson).toHaveBeenCalledTimes(1);
    complete({ success: true, mode: 1, motor_speed: 0 });
    await flushPromises();
    expect(slider.element.disabled).toBe(false);
    requestJson.mockResolvedValueOnce({ success: true, mode: 1, motor_speed: 70 });
    await slider.setValue('70');
    await flushPromises();
    requestJson.mockResolvedValueOnce({ success: true, mode: 0, motor_speed: 0 });
    await toggle.setValue(false);
    await flushPromises();
    expect(requestJson).toHaveBeenLastCalledWith(
        props.endpoint,
        expect.objectContaining({ data: { mode: 0, motor_speed: 0, serial_no: props.serial } }),
    );
    expect(slider.element.value).toBe('0');
    expect(slider.element.disabled).toBe(true);
});
it('restores confirmed state on failure and never automatically retries', async () => {
    requestJson.mockRejectedValue(new Error('You do not have access to this action.'));
    const wrapper = mount(RemoteControl, { props });
    await wrapper.get('#remote-mode').setValue(true);
    await flushPromises();
    expect(wrapper.get('#remote-mode').element.checked).toBe(false);
    expect(wrapper.get('#remote-motor-speed').element.disabled).toBe(true);
    expect(wrapper.get('[role="alert"]').text()).toContain('do not have access');
    expect(requestJson).toHaveBeenCalledTimes(1);
});
it('restores the saved speed after failure and permits an explicit retry', async () => {
    requestJson.mockRejectedValueOnce(new Error('Could not save motor speed.'));
    const wrapper = mount(RemoteControl, { props: { ...props, mode: 1, motorSpeed: -40 } });
    const slider = wrapper.get('#remote-motor-speed');
    await slider.setValue('100');
    await flushPromises();
    expect(slider.element.value).toBe('-40');
    expect(wrapper.get('#remote-speed-value').text()).toBe('Down (-40)');
    expect(wrapper.get('#remote-saved-speed').text()).toContain('Down (-40)');
    expect(wrapper.get('[role="alert"]').text()).toContain('Could not save');
    expect(wrapper.emitted('change')).toBeUndefined();
    expect(requestJson).toHaveBeenCalledTimes(1);
    requestJson.mockResolvedValueOnce({ success: true, mode: 1, motor_speed: 100 });
    await slider.setValue('100');
    await flushPromises();
    expect(wrapper.get('#remote-saved-speed').text()).toContain('Up (100)');
});
