<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    tokens: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const plainTextToken = computed(() => page.props.flash?.token ?? null);

const createForm = useForm({
    name: '',
});

const createToken = () => {
    createForm.post('/settings/tokens', {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name'),
    });
};

const revoke = (id) => {
    if (!window.confirm('Revoke this token? Any client using it will stop working.')) {
        return;
    }

    useForm({}).delete('/settings/tokens/' + id, {
        preserveScroll: true,
    });
};

const copied = ref(false);

const copyToken = async () => {
    if (!plainTextToken.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(plainTextToken.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        copied.value = false;
    }
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};
</script>

<template>
    <Head title="API Tokens" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                API Tokens
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="plainTextToken"
                    class="rounded-lg border border-green-300 bg-green-50 p-4 shadow sm:p-8"
                >
                    <h3 class="text-lg font-medium text-green-900">
                        Your new API token
                    </h3>
                    <p class="mt-1 text-sm text-green-800">
                        Copy it now. For your security, it won't be shown again.
                    </p>

                    <div class="mt-4 flex items-center gap-3">
                        <code
                            class="block flex-1 overflow-x-auto rounded bg-white px-3 py-2 font-mono text-sm text-gray-800 ring-1 ring-inset ring-gray-200"
                        >{{ plainTextToken }}</code>
                        <SecondaryButton type="button" @click="copyToken">
                            {{ copied ? 'Copied' : 'Copy' }}
                        </SecondaryButton>
                    </div>
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <section class="max-w-xl">
                        <header>
                            <h3 class="text-lg font-medium text-gray-900">
                                Create token
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Personal access tokens let the browser extension
                                authenticate to the API.
                            </p>
                        </header>

                        <form
                            @submit.prevent="createToken"
                            class="mt-6 space-y-6"
                        >
                            <div>
                                <InputLabel for="name" value="Token name" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="createForm.name"
                                    required
                                    autofocus
                                    placeholder="e.g. Laptop"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="createForm.errors.name"
                                />
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="createForm.processing">
                                    Create
                                </PrimaryButton>
                            </div>
                        </form>
                    </section>
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <h3 class="text-lg font-medium text-gray-900">
                        Existing tokens
                    </h3>

                    <p
                        v-if="tokens.length === 0"
                        class="mt-4 text-sm text-gray-600"
                    >
                        You don't have any tokens yet.
                    </p>

                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-2 pr-4 font-medium">Name</th>
                                    <th class="py-2 pr-4 font-medium">
                                        Last used
                                    </th>
                                    <th class="py-2 pr-4 font-medium">Created</th>
                                    <th class="py-2 font-medium">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="token in tokens" :key="token.id">
                                    <td class="py-3 pr-4 text-gray-900">
                                        {{ token.name }}
                                    </td>
                                    <td class="py-3 pr-4 text-gray-600">
                                        {{ formatDate(token.last_used_at) }}
                                    </td>
                                    <td class="py-3 pr-4 text-gray-600">
                                        {{ formatDate(token.created_at) }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <DangerButton
                                            type="button"
                                            @click="revoke(token.id)"
                                        >
                                            Revoke
                                        </DangerButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
