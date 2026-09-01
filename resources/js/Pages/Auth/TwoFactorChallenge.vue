<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

defineProps({ status: String });

const form = useForm({
    code: '',
});

const submit = () => {
    form.post(route('two-factor.challenge.store'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Two-Factor Authentication" />

        <div class="mb-4 text-sm text-gray-600">
            Your account is protected with an authenticator app. Enter the
            6-digit code from your app to finish signing in.
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="code" value="Authentication code" />
                <TextInput
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    class="mt-1 block w-full tracking-[0.4em] font-mono"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div class="mt-4 flex justify-end">
                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Verify code
                </PrimaryButton>
            </div>
        </form>

        <form class="mt-6 text-center">
            <a
                href="#"
                class="text-sm text-gray-600 underline hover:text-gray-900"
                @click.prevent="router.post(route('logout'))"
            >
                Cancel and sign out
            </a>
        </form>
    </GuestLayout>
</template>
