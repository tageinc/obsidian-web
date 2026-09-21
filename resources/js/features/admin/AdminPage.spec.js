import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AdminPage from './AdminPage.vue';

const mounted = [];
function page(overrides = {}) {
    const pagination = {
        perPage: 2,
        lastPage: 2,
        sizes: [2, 10, 20],
        links: [
            {
                url: '/developer-workspace?firmware_show=2&config_show=10&section=firmware&page=2',
                label: '2',
                active: false,
            },
        ],
    };
    const wrapper = mount(AdminPage, {
        attachTo: document.body,
        props: {
            csrfToken: 'test-csrf',
            links: {
                dashboard: '/dashboard',
                admin: '/developer-workspace',
                uploadFirmware: '/upload-firmware',
                uploadConfig: '/upload-config',
            },
            firmware: {
                rows: [
                    {
                        version: '2',
                        prefix: 'SP1',
                        description: '<img src=x>',
                        createdAt: '2026-09-21 10:30:00',
                    },
                ],
                pagination,
            },
            config: { rows: [], pagination: { ...pagination, perPage: 10 } },
            ...overrides,
        },
    });
    mounted.push(wrapper);
    return wrapper;
}
afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    vi.restoreAllMocks();
});

describe('developer workspace upload workflow', () => {
    it('starts with one history and reveals uploads on demand without losing drafts across sections', async () => {
        const wrapper = page();
        expect(wrapper.get('h1').text()).toBe('Developer Workspace');
        expect(wrapper.get('#developer-firmware-panel').isVisible()).toBe(true);
        expect(wrapper.get('#developer-config-panel').isVisible()).toBe(false);
        expect(wrapper.get('#firmware-upload').isVisible()).toBe(false);
        await wrapper.get('[aria-controls="firmware-upload-section"]').trigger('click');
        expect(wrapper.get('#firmware-upload').isVisible()).toBe(true);
        expect(document.activeElement).toBe(wrapper.get('#firmware').element);
        await wrapper.get('#firmware-description').setValue('A draft release');
        await wrapper.get('#developer-config-tab').trigger('click');
        expect(wrapper.get('#developer-firmware-panel').isVisible()).toBe(false);
        expect(wrapper.get('#developer-config-panel').isVisible()).toBe(true);
        expect(wrapper.get('#config-upload').isVisible()).toBe(false);
        await wrapper.get('#developer-firmware-tab').trigger('click');
        expect(wrapper.get('#firmware-description').element.value).toBe('A draft release');
        await wrapper.get('[aria-controls="firmware-upload-section"]').trigger('click');
        expect(wrapper.get('#firmware-upload').isVisible()).toBe(false);
        expect(wrapper.get('#firmware-description').element.value).toBe('A draft release');
    });

    it('supports section links from the server and keyboard tab navigation with one tab stop', async () => {
        const wrapper = page({ activeSection: 'config' });
        const firmware = wrapper.get('#developer-firmware-tab');
        const config = wrapper.get('#developer-config-tab');
        expect(config.attributes('aria-selected')).toBe('true');
        expect(config.attributes('tabindex')).toBe('0');
        expect(firmware.attributes('tabindex')).toBe('-1');
        await config.trigger('keydown', { key: 'ArrowRight' });
        expect(firmware.attributes('aria-selected')).toBe('true');
        expect(document.activeElement).toBe(firmware.element);
        await firmware.trigger('keydown', { key: 'End' });
        expect(document.activeElement).toBe(config.element);
        await config.trigger('keydown', { key: 'Home' });
        expect(document.activeElement).toBe(firmware.element);
        expect(wrapper.findAll('[role="tab"][tabindex="0"]')).toHaveLength(1);
        for (const tab of [firmware, config]) {
            const panel = wrapper.get(`#${tab.attributes('aria-controls')}`);
            expect(panel.attributes('aria-labelledby')).toBe(tab.attributes('id'));
        }
    });

    it('preserves both native multipart endpoints and never restores file contents or unsafe table markup', () => {
        const wrapper = page({
            values: {
                description: 'A release',
                prefix: 'SP1',
                file_path: 'must-not-render',
                firmware: 'must-not-render',
            },
        });
        const forms = wrapper.findAll('form[enctype="multipart/form-data"]');
        expect(forms).toHaveLength(2);
        expect(forms[0].attributes()).toMatchObject({ method: 'POST', action: '/upload-firmware' });
        expect(forms[1].attributes()).toMatchObject({ method: 'POST', action: '/upload-config' });
        for (const [index, kind] of ['firmware', 'config'].entries()) {
            expect(forms[index].get('[name="_token"]').element.value).toBe('test-csrf');
            expect(forms[index].get('[name="_upload_kind"]').element.value).toBe(kind);
            expect(forms[index].get(`[name="${kind}"]`).element.value).toBe('');
            expect(forms[index].findAll('button[type="submit"]')).toHaveLength(1);
        }
        expect(wrapper.html()).not.toContain('must-not-render');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x>');
        expect(wrapper.text()).toContain('No configuration updates found.');
        const ids = wrapper.findAll('[id]').map((element) => element.attributes('id'));
        expect(new Set(ids).size).toBe(ids.length);
    });

    it('retains failed upload text and exposes server errors only on the originating form with correct field links', () => {
        const wrapper = page({
            activeUpload: 'config',
            activeSection: 'firmware',
            values: { description: 'Retained', prefix: 'CFG' },
            errors: {
                config: ['Choose JSON.'],
                description: ['Description is invalid.'],
                prefix: ['Prefix is invalid.'],
            },
        });
        expect(wrapper.get('#developer-config-panel').isVisible()).toBe(true);
        expect(wrapper.get('#config-upload').isVisible()).toBe(true);
        expect(wrapper.get('#developer-firmware-panel').isVisible()).toBe(false);
        expect(wrapper.get('#config-description').element.value).toBe('Retained');
        expect(wrapper.get('#firmware-description').element.value).toBe('');
        expect(wrapper.get('#config-prefix').element.value).toBe('CFG');
        expect(wrapper.get('#config').attributes('aria-describedby')).toBe(
            'config-help config-errors',
        );
        expect(wrapper.get('#config-prefix').attributes('aria-describedby')).toBe(
            'config-prefix-errors',
        );
        expect(wrapper.findAll('[role="alert"]')).toHaveLength(1);
        expect(wrapper.get('[href="#config-description"]').text()).toBe('Description is invalid.');
        expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element);
    });

    it('validates file size without sending or retaining the selected file', async () => {
        const wrapper = page();
        const input = wrapper.get('#config');
        Object.defineProperty(input.element, 'files', {
            configurable: true,
            value: [{ size: 1024 * 1024 + 1 }],
        });
        await input.trigger('change');
        expect(input.element.validationMessage).toBe('The file must be no larger than 1 MB.');
        Object.defineProperty(input.element, 'files', {
            configurable: true,
            value: [{ size: 1024 }],
        });
        await input.trigger('change');
        expect(input.element.validity.customError).toBe(false);
        expect(wrapper.findAll('button:disabled')).toHaveLength(0);
    });

    it('prevents duplicate or competing uploads while preserving the first native submission', async () => {
        const wrapper = page();
        const first = new Event('submit', { cancelable: true });
        wrapper.get('#config-upload').element.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(false);
        const competing = new Event('submit', { cancelable: true });
        wrapper.get('#firmware-upload').element.dispatchEvent(competing);
        expect(competing.defaultPrevented).toBe(true);
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('button[type="submit"]:disabled')).toHaveLength(2);
        expect(wrapper.findAll('[role="tab"]:disabled')).toHaveLength(2);
        expect(wrapper.get('#config-upload').attributes('aria-busy')).toBe('true');
        window.dispatchEvent(new Event('pageshow'));
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('button:disabled')).toHaveLength(0);
    });

    it('keeps both page-size parameters in native GET controls and preserves server pagination URLs', async () => {
        const wrapper = page();
        const select = wrapper.get('#firmware-per-page');
        const form = select.element.form;
        const submit = vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});
        await select.setValue('20');
        expect(submit).toHaveBeenCalledOnce();
        expect(form.method).toBe('get');
        expect(Object.fromEntries(new FormData(form))).toEqual({
            section: 'firmware',
            config_show: '10',
            firmware_show: '20',
        });
        expect(wrapper.get('nav a').attributes('href')).toBe(
            '/developer-workspace?firmware_show=2&config_show=10&section=firmware&page=2',
        );
    });
});
