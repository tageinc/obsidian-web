import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DOMWrapper, flushPromises, mount } from '@vue/test-utils';
import ReleaseActions from './ReleaseActions.vue';
import { requestJson, RequestError } from '../../shared/api/client.js';

vi.mock('../../shared/api/client.js', async (load) => ({
    ...(await load()),
    requestJson: vi.fn(),
}));
let wrapper;
const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');
function actions(kind, title, path) {
    wrapper = mount(ReleaseActions, {
        attachTo: document.body,
        props: {
            kind,
            title,
            csrfToken: 'test-csrf',
            row: {
                id: 42,
                version: '2',
                prefix: '<img src=x>',
                description: 'Existing release',
                updateUrl: `/developer-workspace/${path}/42`,
                deleteUrl: `/developer-workspace/${path}/42`,
            },
        },
    });
    return wrapper;
}
const view = () => new DOMWrapper(document.body);
const trigger = () => view().get('.release-action-trigger');
async function open(mode) {
    await trigger().trigger('click');
    await view()
        .get(`[role="menuitem"]${mode === 'edit' ? ':first-child' : ':last-child'}`)
        .trigger('click');
    await flushPromises();
}
beforeEach(() => {
    requestJson.mockReset();
    Object.defineProperties(HTMLDialogElement.prototype, {
        showModal: {
            configurable: true,
            value() {
                this.setAttribute('open', '');
            },
        },
        close: {
            configurable: true,
            value() {
                this.removeAttribute('open');
                this.dispatchEvent(new Event('close'));
            },
        },
    });
});
afterEach(() => {
    wrapper?.unmount();
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

describe.each([
    { kind: 'firmware', title: 'Firmware', path: 'firmware' },
    { kind: 'config', title: 'Configuration', path: 'config' },
])('$title row actions', ({ kind, title, path }) => {
    it('supports menu keyboard navigation, outside dismissal and Escape focus restoration', async () => {
        actions(kind, title, path);
        await trigger().trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const items = view().findAll('[role="menuitem"]');
        expect(document.activeElement).toBe(items[0].element);
        await items[0].trigger('keydown', { key: 'ArrowDown' });
        expect(document.activeElement).toBe(items[1].element);
        await items[1].trigger('keydown', { key: 'Escape' });
        expect(trigger().attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger().element);
        await trigger().trigger('click');
        document.body.click();
        await flushPromises();
        expect(trigger().attributes('aria-expanded')).toBe('false');
        expect(requestJson).not.toHaveBeenCalled();
    });

    it('edits only version and description, discards cancelled drafts and preserves the original row', async () => {
        actions(kind, title, path);
        await open('edit');
        expect(view().get('dialog').text()).toContain('<img src=x>');
        expect(view().find('dialog img').exists()).toBe(false);
        expect(view().find('dialog [name="prefix"]').exists()).toBe(false);
        await view().get('[name="version"]').setValue('27');
        await view().get('[name="description"]').setValue('Draft');
        await view().get('dialog').trigger('cancel');
        await flushPromises();
        expect(requestJson).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(trigger().element);
        await open('edit');
        expect(view().get('[name="version"]').element.value).toBe('2');
        expect(view().get('[name="description"]').element.value).toBe('Existing release');
        await view().get('[name="version"]').setValue('9007199254740993');
        await view().get('[name="description"]').setValue('Updated release');
        const result = { message: `${title} updated.`, id: 42, version: '9007199254740993' };
        requestJson.mockResolvedValueOnce(result);
        await view().get('dialog form').trigger('submit');
        await flushPromises();
        expect(requestJson).toHaveBeenCalledWith(
            `/developer-workspace/${path}/42`,
            expect.objectContaining({
                method: 'PATCH',
                csrfToken: 'test-csrf',
                data: { version: '9007199254740993', description: 'Updated release' },
            }),
        );
        expect(wrapper.props('row').version).toBe('2');
        expect(wrapper.emitted('changed')).toEqual([[result]]);
    });

    it('retains an invalid edit after server validation and permits a corrected retry', async () => {
        actions(kind, title, path);
        await open('edit');
        await view().get('[name="version"]').setValue('0');
        await view().get('dialog form').trigger('submit');
        expect(requestJson).not.toHaveBeenCalled();
        await view().get('[name="version"]').setValue('3');
        requestJson.mockRejectedValueOnce(
            new RequestError('Invalid.', 422, { version: ['This version is already used.'] }),
        );
        await view().get('dialog form').trigger('submit');
        await flushPromises();
        expect(view().get('[name="version"]').element.value).toBe('3');
        expect(view().get('[name="version"]').attributes('aria-invalid')).toBe('true');
        expect(view().get(`[href="#${kind}-actions-42-version"]`).text()).toBe(
            'This version is already used.',
        );
        expect(document.activeElement).toBe(view().get('[role="alert"]').element);
        await view().get('[name="version"]').setValue('4');
        requestJson.mockResolvedValueOnce({ message: `${title} updated.` });
        await view().get('dialog form').trigger('submit');
        await flushPromises();
        expect(requestJson).toHaveBeenCalledTimes(2);
        expect(wrapper.emitted('changed')).toHaveLength(1);
    });

    it('requires delete confirmation, guards in-flight submissions and recovers from failure', async () => {
        actions(kind, title, path);
        await open('delete');
        expect(view().get('dialog').text()).toContain(
            `Delete ${title.toLowerCase()} version 2 (<img src=x>)?`,
        );
        expect(requestJson).not.toHaveBeenCalled();
        await view().get('dialog form button[type="button"]').trigger('click');
        await flushPromises();
        expect(requestJson).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(trigger().element);
        await open('delete');
        let reject;
        requestJson.mockReturnValueOnce(
            new Promise((_, fail) => {
                reject = fail;
            }),
        );
        await view().get('dialog form').trigger('submit');
        await view().get('dialog form').trigger('submit');
        await view().get('dialog').trigger('cancel');
        expect(requestJson).toHaveBeenCalledTimes(1);
        expect(requestJson.mock.calls[0][1]).toMatchObject({
            method: 'DELETE',
            csrfToken: 'test-csrf',
        });
        expect(requestJson.mock.calls[0][1]).not.toHaveProperty('data');
        expect(view().get('dialog').attributes('open')).toBeDefined();
        expect(view().get('dialog button[type="submit"]').element.disabled).toBe(true);
        reject(new RequestError('The request could not be completed.', 500));
        await flushPromises();
        expect(view().get('[role="alert"]').text()).toBe('The request could not be completed.');
        expect(view().get('dialog button[type="submit"]').element.disabled).toBe(false);
        requestJson.mockResolvedValueOnce({ message: `${title} deleted.` });
        await view().get('dialog form').trigger('submit');
        await flushPromises();
        expect(wrapper.emitted('changed')).toEqual([[{ message: `${title} deleted.` }]]);
    });
});
