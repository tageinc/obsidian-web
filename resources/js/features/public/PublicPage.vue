<script setup>
defineProps({
    mode: { type: String, required: true },
    contact: { type: Object, default: () => ({}) },
    links: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="container py-5 text-center">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <template v-if="mode === 'contact'">
                    <h1>Contact Us</h1>
                    <address class="mb-0">
                        <p>
                            Phone #: <a :href="contact.phoneHref">{{ contact.phoneLabel }}</a>
                        </p>
                        <p>
                            Email: <a :href="`mailto:${contact.email}`">{{ contact.email }}</a>
                        </p>
                    </address>
                </template>
                <template v-else-if="mode === 'thank-you'">
                    <h1>Thank You for Creating Your Device!</h1>
                    <p>Your device has been successfully registered.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a :href="links.createDevice" class="btn btn-primary"
                            >Create Another Device</a
                        >
                        <a :href="links.deviceManager" class="btn btn-secondary">View My Devices</a>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
