import { afterEach, expect, it } from 'vitest';
import { DOMWrapper, flushPromises, mount } from '@vue/test-utils';
import ExternalApiKeyActions from './ExternalApiKeyActions.vue';

let wrapper;
function actions(status = 'Active') {
    wrapper = mount(ExternalApiKeyActions, {
        attachTo: document.body,
        props: { apiKey: { id: 42, name: '<img src=x>', status } },
    });
    return wrapper;
}
const menu = () => new DOMWrapper(document.body).get('[role="menu"]');
afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

it('reveals only Revoke with keyboard focus and returns to the trigger on Escape', async () => {
    actions();
    const trigger = wrapper.get('button');
    expect(menu().isVisible()).toBe(false);
    await trigger.trigger('keydown', { key: 'ArrowDown' });
    await flushPromises();
    expect(trigger.attributes('aria-expanded')).toBe('true');
    expect(menu().findAll('[role="menuitem"]')).toHaveLength(1);
    const revoke = menu().get('[role="menuitem"]');
    expect(revoke.text()).toBe('Revoke');
    expect(revoke.attributes('aria-label')).toBe('Revoke <img src=x>');
    expect(menu().find('img').exists()).toBe(false);
    expect(document.activeElement).toBe(revoke.element);
    await revoke.trigger('keydown', { key: 'ArrowUp' });
    expect(document.activeElement).toBe(revoke.element);
    await revoke.trigger('keydown', { key: 'Escape' });
    expect(menu().isVisible()).toBe(false);
    expect(document.activeElement).toBe(trigger.element);
    expect(wrapper.emitted('revoke')).toBeUndefined();
});

it('dismisses on outside click and emits the trigger only after choosing Revoke', async () => {
    actions('Expired');
    await wrapper.setProps({ opensDialog: false });
    expect(menu().get('button').attributes('aria-haspopup')).toBeUndefined();
    const trigger = wrapper.get('button');
    await trigger.trigger('click');
    await flushPromises();
    document.body.click();
    await flushPromises();
    expect(menu().isVisible()).toBe(false);
    expect(wrapper.emitted('revoke')).toBeUndefined();
    await trigger.trigger('click');
    await flushPromises();
    await menu().get('button').trigger('click');
    expect(menu().isVisible()).toBe(false);
    expect(wrapper.emitted('revoke')).toEqual([[trigger.element]]);
});

it('blocks revoked and pending actions and closes an open menu when eligibility changes', async () => {
    actions('Revoked');
    const trigger = wrapper.get('button');
    expect(trigger.element.disabled).toBe(true);
    await trigger.trigger('keydown', { key: 'ArrowDown' });
    expect(menu().isVisible()).toBe(false);
    await wrapper.setProps({ apiKey: { id: 42, name: 'Integration', status: 'Active' } });
    await trigger.trigger('click');
    await flushPromises();
    expect(menu().isVisible()).toBe(true);
    await wrapper.setProps({ disabled: true });
    expect(menu().isVisible()).toBe(false);
    expect(trigger.element.disabled).toBe(true);
    await menu().get('button').trigger('click');
    expect(wrapper.emitted('revoke')).toBeUndefined();
});
