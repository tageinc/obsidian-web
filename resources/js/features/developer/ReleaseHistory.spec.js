import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ReleaseHistory from './ReleaseHistory.vue';

let wrapper;
let submit;
function history() {
    wrapper = mount(ReleaseHistory, {
        attachTo: document.body,
        props: {
            kind: 'firmware',
            title: 'Firmware',
            developerUrl: '/developer-workspace',
            otherSize: 10,
            pagination: {
                perPage: 10,
                sizes: [2, 10, 20],
                total: 1,
                from: 1,
                to: 1,
                lastPage: 1,
                links: [],
            },
            rows: [
                {
                    version: '2',
                    prefix: 'SP1',
                    description: 'A release',
                    createdAt: '2026-09-01 12:00:00',
                },
            ],
            filters: {
                search: 'release',
                prefixes: ['SP1'],
                versions: [],
                from: '',
                to: '',
                sort: 'newest',
            },
            filterOptions: { prefixes: ['SP1', 'SP2', 'SP3'], versions: ['1', '2', '3'] },
            preservedQuery: [
                { name: 'config_search', value: 'settings' },
                { name: 'config_page', value: '3' },
                { name: 'config_prefixes[]', value: 'CFG' },
            ],
        },
    });
    submit = vi.spyOn(wrapper.element, 'requestSubmit').mockImplementation(() => {});
    return wrapper;
}
const data = () => new FormData(wrapper.element);
const prefixes = () => wrapper.findAll('select[multiple]')[0].element.tomselect;
const versions = () => wrapper.findAll('select[multiple]')[1].element.tomselect;
afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('release history filtering', () => {
    it('discards unapplied selections on dismissal, then commits a combined filter without losing the other section', async () => {
        history();
        const trigger = wrapper.get('.release-filter-trigger');
        await trigger.trigger('click');
        prefixes().setValue(['SP2']);
        versions().setValue(['2']);
        await wrapper.get('#firmware-filter-from').setValue('2026-09-01');
        expect(data().getAll('firmware_prefixes[]')).toEqual(['SP1']);
        expect(data().getAll('firmware_versions[]')).toEqual([]);
        expect(data().get('firmware_from')).toBeNull();
        expect(submit).not.toHaveBeenCalled();
        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        await trigger.trigger('click');
        expect(prefixes().items).toEqual(['SP1']);
        expect(versions().items).toEqual([]);
        expect(wrapper.get('#firmware-filter-from').element.value).toBe('');
        prefixes().setValue(['SP1', 'SP2']);
        versions().setValue(['1', '2']);
        await wrapper.get('#firmware-filter-from').setValue('2026-09-01');
        await wrapper.get('.filter-panel-actions .btn-primary').trigger('click');
        await flushPromises();
        expect(submit).toHaveBeenCalledOnce();
        expect(data().getAll('firmware_prefixes[]')).toEqual(['SP1', 'SP2']);
        expect(data().getAll('firmware_versions[]')).toEqual(['1', '2']);
        expect(data().get('firmware_from')).toBe('2026-09-01');
        expect(data().get('firmware_search')).toBe('release');
        expect(data().get('firmware_page')).toBeNull();
        expect(data().get('config_search')).toBe('settings');
        expect(data().get('config_page')).toBe('3');
        expect(data().getAll('config_prefixes[]')).toEqual(['CFG']);
        await wrapper.get('.release-clear').trigger('click');
        await flushPromises();
        expect(submit).toHaveBeenCalledTimes(2);
        expect(data().getAll('firmware_prefixes[]')).toEqual([]);
        expect(data().get('firmware_from')).toBeNull();
        expect(data().get('firmware_search')).toBe('');
        expect(data().get('config_search')).toBe('settings');
    });

    it('validates the draft date interval and applies it with Enter instead of submitting stale hidden filters', async () => {
        history();
        await wrapper.get('.release-filter-trigger').trigger('click');
        prefixes().setValue(['SP2']);
        prefixes().close();
        await wrapper
            .get('#firmware-filter-prefixes')
            .trigger('keydown', { key: 'Enter', keyCode: 13 });
        expect(submit).not.toHaveBeenCalled();
        await wrapper.get('#firmware-filter-from').setValue('2026-09-10');
        const to = wrapper.get('#firmware-filter-to');
        await to.setValue('2026-09-05');
        await wrapper.get('.filter-panel-actions .btn-primary').trigger('click');
        expect(submit).not.toHaveBeenCalled();
        expect(to.element.validity.rangeUnderflow).toBe(true);
        await to.setValue('2026-09-11');
        await to.trigger('keydown', { key: 'Enter' });
        await flushPromises();
        expect(submit).toHaveBeenCalledOnce();
        expect(data().getAll('firmware_prefixes[]')).toEqual(['SP2']);
        expect(data().get('firmware_from')).toBe('2026-09-10');
        expect(data().get('firmware_to')).toBe('2026-09-11');
    });

    it('closes the suggestion list before dismissing drafts, restores focus, and destroys Tom Select on unmount', async () => {
        history();
        const trigger = wrapper.get('.release-filter-trigger');
        await trigger.trigger('click');
        const select = wrapper.findAll('select[multiple]')[0].element;
        prefixes().setValue(['SP2']);
        prefixes().open();
        await wrapper.get('#firmware-filter-prefixes').trigger('keydown', { key: 'Escape' });
        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(prefixes().isOpen).toBe(false);
        expect(prefixes().items).toEqual(['SP2']);
        await wrapper.get('#firmware-filter-prefixes').trigger('keydown', { key: 'Escape' });
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger.element);
        expect(prefixes().items).toEqual(['SP1']);
        expect(submit).not.toHaveBeenCalled();
        wrapper.unmount();
        wrapper = null;
        expect(select.tomselect).toBeUndefined();
    });
});
