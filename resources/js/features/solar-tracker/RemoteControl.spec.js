import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import RemoteControl from './RemoteControl.vue';
import { requestJson } from '../../shared/api/client';
vi.mock('../../shared/api/client', () => ({ requestJson: vi.fn() }));
const props = {
    mode: 0,
    motorSpeed: 0,
    serial: 'local-test',
    endpoint: '/update-solar-tracker',
    csrfToken: 'test-only',
};
beforeEach(() => vi.clearAllMocks());
it('sends no command on mount or unmount and blocks automatic-mode buttons', () => {
    const wrapper = mount(RemoteControl, { props });
    expect(wrapper.findAll('button').every((button) => button.element.disabled)).toBe(true);
    wrapper.unmount();
    expect(requestJson).not.toHaveBeenCalled();
});
it('persists integer mode and Stop, prevents duplicate requests, then enables manual controls', async () => {
    let complete;
    requestJson.mockImplementation(
        () =>
            new Promise((resolve) => {
                complete = resolve;
            }),
    );
    const wrapper = mount(RemoteControl, { props });
    await wrapper.get('input').setValue(true);
    expect(requestJson).toHaveBeenCalledWith(
        props.endpoint,
        expect.objectContaining({ data: { mode: 1, motor_speed: 0, serial_no: props.serial } }),
    );
    expect(wrapper.get('input').element.disabled).toBe(true);
    complete({ success: true, mode: 1, motor_speed: 0 });
    await flushPromises();
    expect(wrapper.get('button').element.disabled).toBe(false);
    requestJson.mockResolvedValue({ success: true, mode: 1, motor_speed: 20 });
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('Up command saved.');
});
it('restores confirmed state on failure and never automatically retries', async () => {
    requestJson.mockRejectedValue(new Error('You do not have access to this action.'));
    const wrapper = mount(RemoteControl, { props });
    await wrapper.get('input').setValue(true);
    await flushPromises();
    expect(wrapper.get('input').element.checked).toBe(false);
    expect(wrapper.get('[role="alert"]').text()).toContain('do not have access');
    expect(requestJson).toHaveBeenCalledTimes(1);
});
