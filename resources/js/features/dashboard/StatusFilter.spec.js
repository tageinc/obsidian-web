import { expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusFilter from './StatusFilter.vue';

it('keeps multiselect changes as drafts until Apply, discards on reopen, and clears filters explicitly', async () => {
    const form = document.createElement('form');
    document.body.append(form);
    const submit = vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});
    const wrapper = mount(StatusFilter, {
        attachTo: form,
        props: {
            value: ['sleep'], options: ['offline', 'sleep'],
            stateValue: ['active'], stateOptions: ['active', 'inactive'],
        },
    });
    const select = wrapper.get('select').element;
    const control = select.tomselect;
    const stateControl = wrapper.findAll('select')[1].element.tomselect;
    try {
        await wrapper.get('.filter-trigger').trigger('click');
        expect(wrapper.findAll('.filter-panel label').map((label) => label.text())).toEqual([
            'Status',
            'State',
        ]);
        control.setValue(['offline', 'sleep']);
        stateControl.setValue(['inactive']);
        expect(submit).not.toHaveBeenCalled();
        expect(new FormData(form).getAll('status[]')).toEqual(['sleep']);
        expect(new FormData(form).getAll('state[]')).toEqual(['active']);
        await wrapper.get('.btn-primary').trigger('click');
        expect(submit).toHaveBeenCalledOnce();
        expect(new FormData(form).getAll('status[]')).toEqual(['offline', 'sleep']);
        expect(new FormData(form).getAll('state[]')).toEqual(['inactive']);
        await wrapper.get('.filter-trigger').trigger('click');
        control.setValue(['sleep']);
        await wrapper.get('.device-filter').trigger('keydown', { key: 'Escape' });
        await wrapper.get('.filter-trigger').trigger('click');
        expect(control.getValue()).toEqual(['offline', 'sleep']);
        await wrapper.get('.btn-outline-secondary').trigger('click');
        expect(submit).toHaveBeenCalledTimes(2);
        expect(new FormData(form).getAll('status[]')).toEqual([]);
        expect(new FormData(form).getAll('state[]')).toEqual([]);
        await wrapper.setProps({ value: ['online'], options: ['online', 'sleep'] });
        expect(control.getValue()).toEqual(['online']);
        expect(submit).toHaveBeenCalledTimes(2);
    } finally {
        wrapper.unmount();
        expect(select.tomselect).toBeUndefined();
        form.remove();
        vi.restoreAllMocks();
    }
});
