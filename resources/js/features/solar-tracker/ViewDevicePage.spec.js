import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import ViewDevicePage from './ViewDevicePage.vue';
import HistoryCharts from './HistoryCharts.vue';
import { requestJson } from '../../shared/api/client';
vi.mock('../../shared/api/client', () => ({ requestJson: vi.fn() }));
const props = {
    device: {
        alias: 'Test tracker',
        serial: 'TEST-1',
        details: { Hardware: 'Solar Tracker', SKU: 'TEST', Address: '1 Test Street' },
    },
    status: {
        State: 'online',
        Updated: 'Sep 20, 2026, 12:00 PM PDT',
        'Temperature (°C)': 24,
        'Motor Speed': 0,
        PS1: 10,
    },
    remote: { mode: 0, motor_speed: 0 },
    points: [],
    links: { dashboard: '/dashboard', edit: '/edit-device/1', remote: '/update-solar-tracker' },
    csrfToken: 'test-only',
};
beforeEach(() => vi.clearAllMocks());
afterEach(() => {
    document.body.innerHTML = '';
});
it('opens on the summary and exposes each section through accessible keyboard tabs without sending commands', async () => {
    const wrapper = mount(ViewDevicePage, { props, attachTo: document.body });
    expect(wrapper.get('#device-panel-overview').isVisible()).toBe(true);
    expect(wrapper.get('#device-panel-control').isVisible()).toBe(false);
    expect(wrapper.findComponent(HistoryCharts).props('active')).toBe(false);
    await wrapper.get('#device-tab-overview').trigger('keydown', { key: 'ArrowRight' });
    expect(wrapper.get('#device-panel-history').isVisible()).toBe(true);
    expect(wrapper.get('#device-tab-history').attributes('aria-selected')).toBe('true');
    await wrapper.get('#device-tab-history').trigger('keydown', { key: 'End' });
    await flushPromises();
    expect(wrapper.get('#device-tab-control').attributes('aria-selected')).toBe('true');
    expect(wrapper.get('#device-panel-control').isVisible()).toBe(true);
    expect(document.activeElement).toBe(wrapper.get('#device-tab-control').element);
    expect(requestJson).not.toHaveBeenCalled();
    wrapper.unmount();
});
it('retains confirmed control state across sections and updates the overview only after a successful save', async () => {
    requestJson.mockResolvedValue({ success: true, mode: 1, motor_speed: 0 });
    const wrapper = mount(ViewDevicePage, { props });
    const control = wrapper.get('#remote-mode').element;
    await wrapper.get('#device-tab-control').trigger('click');
    await wrapper.get('#remote-mode').setValue(true);
    await flushPromises();
    await wrapper.get('#device-tab-overview').trigger('click');
    expect(wrapper.get('.device-metrics').text()).toContain('Remote control');
    await wrapper.get('#device-tab-control').trigger('click');
    expect(wrapper.get('#remote-mode').element).toBe(control);
    expect(wrapper.get('#remote-mode').element.checked).toBe(true);
    expect(requestJson).toHaveBeenCalledTimes(1);
    wrapper.unmount();
});
it('moves focus into History when the overview shortcut hides its source button', async () => {
    const wrapper = mount(ViewDevicePage, { props, attachTo: document.body });
    const shortcut = wrapper.get('#device-panel-overview button');
    shortcut.element.focus();
    await shortcut.trigger('click');
    expect(document.activeElement).toBe(wrapper.get('#device-panel-history').element);
    expect(wrapper.get('#device-tab-history').attributes('aria-selected')).toBe('true');
    expect(requestJson).not.toHaveBeenCalled();
    wrapper.unmount();
});
it('shows missing telemetry explicitly instead of presenting placeholder zero readings', () => {
    const wrapper = mount(ViewDevicePage, {
        props: {
            ...props,
            status: { State: 0, Updated: 'N/A', 'Temperature (°C)': 0, 'Motor Speed': 0 },
        },
    });
    expect(wrapper.text()).toContain('No telemetry received yet');
    expect(wrapper.get('.device-metrics').text()).not.toContain('0');
    wrapper.unmount();
});

it('embeds device details without page navigation and requests the edit modal from its parent', async () => {
    const wrapper = mount(ViewDevicePage, { props: { ...props, embedded: true } });
    expect(wrapper.find('h1').exists()).toBe(false);
    expect(wrapper.find('.device-back').exists()).toBe(false);
    expect(wrapper.get('.device-heading').text()).toContain('Solar tracker');
    expect(wrapper.get('.device-heading').text()).toContain('TEST-1');
    expect(wrapper.get('#sensors-title').element.tagName).toBe('H3');
    expect(wrapper.find('a[href="/edit-device/1"]').exists()).toBe(false);
    await wrapper.get('.device-heading button').trigger('click');
    expect(wrapper.emitted('edit')).toEqual([[]]);
    expect(requestJson).not.toHaveBeenCalled();
    await wrapper.get('#device-tab-history').trigger('click');
    expect(wrapper.findComponent(HistoryCharts).props('active')).toBe(true);
    wrapper.unmount();
});

it('keeps standalone navigation and hides editing when the server supplies no edit link', () => {
    const wrapper = mount(ViewDevicePage, { props });
    expect(wrapper.get('h1').text()).toBe('Test tracker');
    expect(wrapper.get('.device-back').attributes('href')).toBe('/dashboard');
    expect(wrapper.get('.device-heading a').attributes('href')).toBe('/edit-device/1');
    wrapper.unmount();
    const readOnly = mount(ViewDevicePage, {
        props: { ...props, embedded: true, links: { ...props.links, edit: null } },
    });
    expect(readOnly.find('.device-heading button').exists()).toBe(false);
    readOnly.unmount();
});
