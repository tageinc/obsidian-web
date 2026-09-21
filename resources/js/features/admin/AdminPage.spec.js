import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import AdminPage from './AdminPage.vue';

const mounted = [];
const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
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
async function openUpload(wrapper, kind) {
    await wrapper.get('#developer-' + kind + '-tab').trigger('click');
    const trigger = wrapper.get('[aria-controls="' + kind + '-upload-dialog"]');
    trigger.element.focus();
    await trigger.trigger('click');
    await flushPromises();
    return trigger;
}
async function cancelUpload(wrapper, kind) {
    await wrapper.get('#' + kind + '-upload button[type="button"]').trigger('click');
    await flushPromises();
}

beforeEach(() => {
    Object.defineProperties(HTMLDialogElement.prototype, {
        showModal: {
            configurable: true,
            value: function () {
                this.setAttribute('open', '');
            },
        },
        close: {
            configurable: true,
            value: function () {
                this.removeAttribute('open');
                this.dispatchEvent(new Event('close'));
            },
        },
    });
});
afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.innerHTML = '';
    for (const [method, original] of [
        ['showModal', originalShowModal],
        ['close', originalClose],
    ]) {
        if (original) Object.defineProperty(HTMLDialogElement.prototype, method, original);
        else delete HTMLDialogElement.prototype[method];
    }
    vi.restoreAllMocks();
});

describe('developer workspace upload workflow', () => {
    it('opens a modal on demand and keeps text drafts while resetting the file on close', async () => {
        const wrapper = page();
        expect(wrapper.get('h1').text()).toBe('Developer Workspace');
        expect(wrapper.get('#developer-firmware-panel').isVisible()).toBe(true);
        expect(wrapper.get('#developer-config-panel').isVisible()).toBe(false);
        expect(wrapper.find('dialog').exists()).toBe(false);
        const trigger = await openUpload(wrapper, 'firmware');
        expect(trigger.attributes('aria-haspopup')).toBe('dialog');
        expect(wrapper.get('dialog').attributes('open')).toBeDefined();
        expect(wrapper.get('dialog h2').text()).toBe('Upload firmware');
        expect(document.activeElement).toBe(wrapper.get('dialog h2').element);
        expect(document.body.style.overflow).toBe('hidden');
        await wrapper.get('#firmware-description').setValue('A draft release');
        await wrapper.get('#firmware-prefix').setValue('SP1');
        const originalFile = wrapper.get('#firmware').element;
        await cancelUpload(wrapper, 'firmware');
        expect(wrapper.find('dialog').exists()).toBe(false);
        expect(wrapper.find('#firmware-upload').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
        expect(document.body.style.overflow).toBe('');
        await wrapper.get('#developer-config-tab').trigger('click');
        expect(wrapper.get('#developer-config-panel').isVisible()).toBe(true);
        expect(wrapper.find('dialog').exists()).toBe(false);
        await openUpload(wrapper, 'firmware');
        expect(wrapper.get('#firmware-description').element.value).toBe('A draft release');
        expect(wrapper.get('#firmware-prefix').element.value).toBe('SP1');
        expect(wrapper.get('#firmware').element).not.toBe(originalFile);
        expect(wrapper.get('#firmware').element.value).toBe('');
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
            const panel = wrapper.get('#' + tab.attributes('aria-controls'));
            expect(panel.attributes('aria-labelledby')).toBe(tab.attributes('id'));
        }
    });

    it('preserves native multipart endpoints and never restores file contents or unsafe table markup', async () => {
        const wrapper = page({
            values: {
                description: 'A release',
                prefix: 'SP1',
                file_path: 'must-not-render',
                firmware: 'must-not-render',
            },
        });
        expect(wrapper.find('dialog').exists()).toBe(false);
        for (const kind of ['firmware', 'config']) {
            await openUpload(wrapper, kind);
            const form = wrapper.get('form[enctype="multipart/form-data"]');
            expect(wrapper.findAll('form[enctype="multipart/form-data"]')).toHaveLength(1);
            expect(form.attributes()).toMatchObject({
                method: 'POST',
                action: '/upload-' + kind,
            });
            expect(form.get('[name="_token"]').element.value).toBe('test-csrf');
            expect(form.get('[name="_upload_kind"]').element.value).toBe(kind);
            expect(form.get('[name="' + kind + '"]').element.value).toBe('');
            expect(form.findAll('button[type="submit"]')).toHaveLength(1);
            expect(wrapper.html()).not.toContain('must-not-render');
            const ids = wrapper.findAll('[id]').map((element) => element.attributes('id'));
            expect(new Set(ids).size).toBe(ids.length);
            await cancelUpload(wrapper, kind);
        }
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x>');
        expect(wrapper.text()).toContain('No configuration updates found.');
    });

    it('reopens the originating modal after validation with retained text and correct error links', async () => {
        const wrapper = page({
            activeUpload: 'config',
            activeSection: 'firmware',
            initiallyOpenUpload: 'config',
            values: { description: 'Retained', prefix: 'CFG' },
            errors: {
                config: ['Choose JSON.'],
                description: ['Description is invalid.'],
                prefix: ['Prefix is invalid.'],
            },
        });
        await flushPromises();
        expect(wrapper.get('#developer-config-panel').isVisible()).toBe(true);
        expect(wrapper.get('dialog').attributes('id')).toBe('config-upload-dialog');
        expect(wrapper.get('#developer-firmware-panel').isVisible()).toBe(false);
        expect(wrapper.get('#config-description').element.value).toBe('Retained');
        expect(wrapper.find('#firmware-description').exists()).toBe(false);
        expect(wrapper.get('#config-prefix').element.value).toBe('CFG');
        expect(wrapper.get('#config').element.value).toBe('');
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

    it('shows a handled storage failure inside the correct modal and leaves success closed', async () => {
        const failed = page({
            activeUpload: 'config',
            activeSection: 'firmware',
            initiallyOpenUpload: 'config',
            values: { description: 'Retained', prefix: 'CFG' },
            sessionError: 'The file could not be stored.',
        });
        await flushPromises();
        expect(failed.get('#developer-config-tab').attributes('aria-selected')).toBe('true');
        expect(failed.get('dialog [role="alert"]').text()).toBe('The file could not be stored.');
        expect(failed.findAll('[role="alert"]')).toHaveLength(1);
        expect(failed.get('#config-description').element.value).toBe('Retained');
        expect(document.activeElement).toBe(failed.get('[role="alert"]').element);
        failed.unmount();
        mounted.pop();
        const success = page({
            activeUpload: 'config',
            activeSection: 'config',
            success: 'Configuration uploaded.',
            values: { description: 'A previous release', prefix: 'CFG' },
        });
        expect(success.find('dialog').exists()).toBe(false);
        expect(success.get('[role="status"]').text()).toBe('Configuration uploaded.');
    });

    it('keeps unrelated workspace errors outside the modal without changing the selected history', () => {
        const wrapper = page({
            activeSection: 'config',
            sessionError: 'A workspace operation could not be completed.',
        });
        expect(wrapper.find('dialog').exists()).toBe(false);
        expect(wrapper.get('[role="alert"]').text()).toBe(
            'A workspace operation could not be completed.',
        );
        expect(wrapper.get('#developer-config-tab').attributes('aria-selected')).toBe('true');
        expect(wrapper.get('#developer-config-panel').isVisible()).toBe(true);
    });

    it.each([
        ['firmware', 10],
        ['config', 1],
    ])('validates the %s file size without uploading or retaining a file', async (kind, limit) => {
        const wrapper = page();
        await openUpload(wrapper, kind);
        const input = wrapper.get('#' + kind);
        Object.defineProperty(input.element, 'files', {
            configurable: true,
            value: [{ size: limit * 1024 * 1024 + 1 }],
        });
        await input.trigger('change');
        expect(input.element.validationMessage).toBe(
            'The file must be no larger than ' + limit + ' MB.',
        );
        Object.defineProperty(input.element, 'files', {
            configurable: true,
            value: [{ size: limit * 1024 * 1024 }],
        });
        await input.trigger('change');
        expect(input.element.validity.customError).toBe(false);
        expect(wrapper.findAll('button:disabled')).toHaveLength(0);
    });

    it('guards native upload submission and modal dismissal until pageshow resets pending', async () => {
        const wrapper = page();
        const trigger = await openUpload(wrapper, 'config');
        const form = wrapper.get('#config-upload');
        const first = new Event('submit', { cancelable: true });
        form.element.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(false);
        const duplicate = new Event('submit', { cancelable: true });
        form.element.dispatchEvent(duplicate);
        expect(duplicate.defaultPrevented).toBe(true);
        await wrapper.vm.$nextTick();
        expect(form.get('button[type="submit"]').element.disabled).toBe(true);
        expect(form.get('button[type="button"]').element.disabled).toBe(true);
        expect(wrapper.get('[aria-label="Close configuration upload"]').element.disabled).toBe(
            true,
        );
        expect(wrapper.findAll('[role="tab"]:disabled')).toHaveLength(2);
        expect(wrapper.findAll('[aria-haspopup="dialog"]:disabled')).toHaveLength(2);
        expect(form.attributes('aria-busy')).toBe('true');
        const cancel = new Event('cancel', { cancelable: true });
        wrapper.get('dialog').element.dispatchEvent(cancel);
        expect(cancel.defaultPrevented).toBe(true);
        expect(wrapper.get('dialog').attributes('open')).toBeDefined();
        window.dispatchEvent(new Event('pageshow'));
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('button:disabled')).toHaveLength(0);
        await wrapper.get('dialog').trigger('cancel');
        await flushPromises();
        expect(wrapper.find('dialog').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
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
