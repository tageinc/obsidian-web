<script setup>
import { ref } from 'vue';
import FormField from '../../shared/components/FormField.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import { useNativeForm } from '../../shared/composables/useNativeForm.js';

const props = defineProps({
    csrfToken: { type: String, required: true },
    action: { type: String, required: true },
    values: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
    links: { type: Object, required: true },
});
const fields = [
    'name',
    'email',
    'phone_number',
    'address_1',
    'address_2',
    'city',
    'state',
    'zip_code',
    'country',
];
const form = ref(Object.fromEntries(fields.map((field) => [field, props.values[field] ?? ''])));
const password = ref('');
const passwordConfirmation = ref('');
const { pending, submit } = useNativeForm();
const addressFields = [
    { name: 'address_1', label: 'Address line 1', autocomplete: 'address-line1', column: 'col-12' },
    { name: 'address_2', label: 'Address line 2', autocomplete: 'address-line2', column: 'col-12' },
    { name: 'city', label: 'City', autocomplete: 'address-level2', column: 'col-md-6' },
    {
        name: 'state',
        label: 'State / Province',
        autocomplete: 'address-level1',
        column: 'col-md-6',
    },
    {
        name: 'zip_code',
        label: 'ZIP / Postal code',
        autocomplete: 'postal-code',
        column: 'col-md-6',
    },
    { name: 'country', label: 'Country', autocomplete: 'country-name', column: 'col-md-6' },
];
</script>

<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <nav aria-label="Breadcrumb" class="mb-2">
                    <ol class="list-unstyled d-flex flex-wrap small text-muted mb-0">
                        <li>
                            <a :href="links.dashboard" class="text-muted text-nowrap">Dashboard</a
                            ><span class="mx-2" aria-hidden="true">›</span>
                        </li>
                        <li aria-current="page">Profile</li>
                    </ol>
                </nav>
                <h1 class="h3 mb-3">Profile</h1>
                <FormFeedback
                    :errors="errors"
                    :success="success"
                    :session-error="sessionError"
                    summary="Please correct the highlighted fields and save your profile again."
                />
                <div class="card">
                    <div class="card-body">
                        <form method="POST" :action="action" :aria-busy="pending" @submit="submit">
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input type="hidden" name="_method" value="PUT" />
                            <fieldset class="mb-4">
                                <legend class="h5">Contact information</legend>
                                <FormField
                                    v-model="form.name"
                                    name="name"
                                    label="Name"
                                    autocomplete="name"
                                    maxlength="255"
                                    required
                                    :errors="errors.name"
                                />
                                <div class="row">
                                    <div class="col-md-6">
                                        <FormField
                                            v-model="form.email"
                                            name="email"
                                            label="Email address"
                                            type="email"
                                            autocomplete="email"
                                            maxlength="255"
                                            required
                                            :errors="errors.email"
                                        />
                                    </div>
                                    <div class="col-md-6">
                                        <FormField
                                            v-model="form.phone_number"
                                            name="phone_number"
                                            label="Phone number"
                                            type="tel"
                                            autocomplete="tel"
                                            maxlength="20"
                                            :errors="errors.phone_number"
                                            help="You may keep your current number. For a new number, use 10–15 digits, including the country code if needed."
                                        />
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset class="mb-4">
                                <legend class="h5">Address</legend>
                                <div class="row">
                                    <div
                                        v-for="field in addressFields"
                                        :key="field.name"
                                        :class="field.column"
                                    >
                                        <FormField
                                            v-model="form[field.name]"
                                            :name="field.name"
                                            :label="field.label"
                                            :autocomplete="field.autocomplete"
                                            maxlength="255"
                                            :errors="errors[field.name]"
                                        />
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset class="mb-4">
                                <legend class="h5">Change password</legend>
                                <div class="row">
                                    <div class="col-md-6">
                                        <FormField
                                            v-model="password"
                                            name="password"
                                            label="New password"
                                            type="password"
                                            autocomplete="new-password"
                                            minlength="8"
                                            :errors="errors.password"
                                            help="Leave both fields blank to keep your current password. A new password must contain at least 8 characters."
                                        />
                                    </div>
                                    <div class="col-md-6">
                                        <FormField
                                            v-model="passwordConfirmation"
                                            name="password_confirmation"
                                            label="Confirm new password"
                                            type="password"
                                            autocomplete="new-password"
                                            minlength="8"
                                            :required="password.length > 0"
                                            :errors="errors.password_confirmation"
                                        />
                                    </div>
                                </div>
                            </fieldset>
                            <button type="submit" class="btn btn-primary" :disabled="pending">
                                {{ pending ? 'Saving profile…' : 'Save profile' }}
                            </button>
                            <span class="visually-hidden" role="status">{{
                                pending ? 'Saving profile. Please wait.' : ''
                            }}</span>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
