<script setup>
import { computed, ref } from 'vue';
import FormField from '../../shared/components/FormField.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import { useNativeForm } from '../../shared/composables/useNativeForm';

const props = defineProps({
    mode: { type: String, required: true },
    action: { type: String, required: true },
    csrfToken: { type: String, required: true },
    values: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
    message: { type: String, default: null },
    links: { type: Object, default: () => ({}) },
    showResend: { type: Boolean, default: false },
    resetToken: { type: String, default: '' },
});
const safeFields = [
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
const form = ref(Object.fromEntries(safeFields.map((field) => [field, props.values[field] ?? ''])));
const password = ref('');
const passwordConfirmation = ref('');
const remember = ref(Boolean(props.values.remember));
const resendEmail = ref(props.values.email ?? '');
const { pending, submit } = useNativeForm();
const activeForm = ref(null);
const page = computed(
    () =>
        ({
            login: { title: 'Login', submit: 'Login' },
            register: { title: 'Register', submit: 'Register' },
            verify: { title: 'Verify Your Email Address', submit: 'Resend verification email' },
            'password-email': { title: 'Reset Password', submit: 'Send Password Reset Link' },
            'password-reset': { title: 'Reset Password', submit: 'Reset Password' },
            'password-confirm': { title: 'Confirm Password', submit: 'Confirm Password' },
        })[props.mode],
);
const hasPassword = computed(() =>
    ['login', 'register', 'password-reset', 'password-confirm'].includes(props.mode),
);
const newPassword = computed(() => ['register', 'password-reset'].includes(props.mode));
const addressFields = [
    {
        name: 'address_1',
        label: 'Address line 1',
        autocomplete: 'address-line1',
        column: 'col-12',
        required: true,
    },
    {
        name: 'address_2',
        label: 'Address line 2 (optional)',
        autocomplete: 'address-line2',
        column: 'col-12',
        required: false,
    },
    {
        name: 'city',
        label: 'City',
        autocomplete: 'address-level2',
        column: 'col-md-6',
        required: true,
    },
    {
        name: 'state',
        label: 'State / Province',
        autocomplete: 'address-level1',
        column: 'col-md-6',
        required: true,
    },
    {
        name: 'zip_code',
        label: 'ZIP / Postal code',
        autocomplete: 'postal-code',
        column: 'col-md-6',
        required: true,
    },
    {
        name: 'country',
        label: 'Country',
        autocomplete: 'country-name',
        column: 'col-md-6',
        required: true,
    },
];

function submitForm(event, target) {
    if (!pending.value) activeForm.value = target;
    submit(event);
}
</script>

<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h1 class="h5 mb-0">{{ page.title }}</h1>
                    </div>
                    <div class="card-body">
                        <FormFeedback
                            :errors="errors"
                            :success="success"
                            :session-error="sessionError"
                            summary="Please correct the highlighted fields and try again."
                        />
                        <p v-if="message" class="alert alert-info" role="status">{{ message }}</p>
                        <p v-if="mode === 'verify'">
                            Before proceeding, please check your email for a verification link. If
                            you did not receive it, request another below.
                        </p>
                        <p v-if="mode === 'password-confirm'">
                            Please confirm your password before continuing.
                        </p>

                        <form
                            v-if="mode === 'login' && showResend"
                            method="POST"
                            :action="links.verificationResend"
                            class="mb-4"
                            data-auth-resend
                            :aria-busy="pending && activeForm === 'resend'"
                            @submit="submitForm($event, 'resend')"
                        >
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <label for="resend-email" class="form-label"
                                >Email address to verify</label
                            >
                            <input
                                id="resend-email"
                                v-model="resendEmail"
                                type="email"
                                name="email"
                                class="form-control mb-2"
                                required
                                autocomplete="email"
                                maxlength="255"
                            />
                            <button
                                type="submit"
                                class="btn btn-outline-primary"
                                :disabled="pending"
                            >
                                {{
                                    pending && activeForm === 'resend'
                                        ? 'Sending…'
                                        : 'Resend verification email'
                                }}
                            </button>
                        </form>

                        <form
                            method="POST"
                            :action="action"
                            data-auth-form
                            :aria-busy="pending && activeForm === 'primary'"
                            @submit="submitForm($event, 'primary')"
                        >
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <input
                                v-if="mode === 'password-reset'"
                                type="hidden"
                                name="token"
                                :value="resetToken"
                            />
                            <template v-if="mode === 'register'">
                                <FormField
                                    v-model="form.name"
                                    name="name"
                                    label="Name"
                                    autocomplete="name"
                                    maxlength="255"
                                    required
                                    :errors="errors.name"
                                />
                                <FormField
                                    v-model="form.phone_number"
                                    name="phone_number"
                                    label="Phone number"
                                    type="tel"
                                    autocomplete="tel"
                                    maxlength="20"
                                    required
                                    :errors="errors.phone_number"
                                />
                                <fieldset class="mb-3">
                                    <legend class="h6">Address</legend>
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
                                                :required="field.required"
                                                :errors="errors[field.name]"
                                            />
                                        </div>
                                    </div>
                                </fieldset>
                            </template>
                            <FormField
                                v-if="mode !== 'password-confirm'"
                                v-model="form.email"
                                name="email"
                                label="Email address"
                                type="email"
                                autocomplete="email"
                                maxlength="255"
                                required
                                :errors="errors.email"
                            />
                            <FormField
                                v-if="hasPassword"
                                v-model="password"
                                name="password"
                                label="Password"
                                type="password"
                                :autocomplete="newPassword ? 'new-password' : 'current-password'"
                                :minlength="newPassword ? 8 : undefined"
                                required
                                :errors="errors.password"
                            />
                            <FormField
                                v-if="newPassword"
                                v-model="passwordConfirmation"
                                name="password_confirmation"
                                label="Confirm password"
                                type="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                                :errors="errors.password_confirmation"
                            />
                            <div v-if="mode === 'login'" class="form-check mb-3">
                                <input
                                    id="remember"
                                    v-model="remember"
                                    type="checkbox"
                                    name="remember"
                                    class="form-check-input"
                                />
                                <label for="remember" class="form-check-label">Remember Me</label>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="submit" class="btn btn-primary" :disabled="pending">
                                    {{
                                        pending && activeForm === 'primary'
                                            ? 'Please wait…'
                                            : page.submit
                                    }}
                                </button>
                                <a
                                    v-if="
                                        ['login', 'password-confirm'].includes(mode) &&
                                        links.passwordRequest
                                    "
                                    :href="links.passwordRequest"
                                    class="btn btn-link"
                                    >Forgot Your Password?</a
                                >
                                <template v-if="mode === 'login'">
                                    <a
                                        v-if="links.contact"
                                        :href="links.contact"
                                        class="btn btn-link"
                                        >Contact Us</a
                                    >
                                    <a
                                        v-if="links.privacy"
                                        :href="links.privacy"
                                        class="btn btn-link"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >Privacy Policy</a
                                    >
                                </template>
                            </div>
                            <span class="visually-hidden" role="status">{{
                                pending ? 'Submitting. Please wait.' : ''
                            }}</span>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
