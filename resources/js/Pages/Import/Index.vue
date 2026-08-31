<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import ImportProgress from '@/Components/import/ImportProgress.vue';
import ImportErrorsTable from '@/Components/import/ImportErrorsTable.vue';

const props = defineProps({
    activeImport: { type: Object, default: null },
    formats: { type: Array, default: () => [] },
});

const form = useForm({
    file: null,
    format: '',
});

const isRunning = computed(
    () =>
        props.activeImport?.status === 'pending' ||
        props.activeImport?.status === 'processing',
);

function onFileChange(event) {
    form.file = event.target.files[0] ?? null;
}

function submit() {
    form
        .transform((data) => {
            const payload = { file: data.file };
            if (data.format) {
                payload.format = data.format;
            }
            return payload;
        })
        .post('/import', {
            forceFormData: true,
            onSuccess: () => {
                form.reset('file');
                const input = document.getElementById('import-file');
                if (input) {
                    input.value = '';
                }
            },
        });
}

let timer = null;

function startPolling() {
    if (timer !== null) {
        return;
    }
    timer = setInterval(() => {
        router.reload({ only: ['activeImport'], preserveScroll: true });
    }, 2000);
}

function stopPolling() {
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }
}

onMounted(() => {
    if (isRunning.value) {
        startPolling();
    }
});

watch(
    () => props.activeImport?.status,
    (status) => {
        if (status === 'pending' || status === 'processing') {
            startPolling();
        } else if (status === 'completed' || status === 'failed') {
            stopPolling();
        }
    },
);

onUnmounted(stopPolling);
</script>

<template>
    <Head title="Import" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Import</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <label
                                for="import-file"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Bookmarks file
                            </label>
                            <input
                                id="import-file"
                                type="file"
                                class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                @change="onFileChange"
                            />
                            <InputError class="mt-1" :message="form.errors.file" />
                        </div>

                        <div>
                            <label
                                for="import-format"
                                class="block text-sm font-medium text-gray-700"
                            >
                                Format
                            </label>
                            <select
                                id="import-format"
                                v-model="form.format"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Auto-detect</option>
                                <option v-for="f in formats" :key="f" :value="f">
                                    {{ f.toUpperCase() }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.format" />
                        </div>

                        <div class="flex items-center gap-3">
                            <PrimaryButton
                                :class="{ 'opacity-50': form.processing || isRunning }"
                                :disabled="form.processing || isRunning || !form.file"
                            >
                                Start import
                            </PrimaryButton>
                            <span v-if="isRunning" class="text-xs text-gray-500">
                                An import is already in progress.
                            </span>
                        </div>
                    </form>
                </div>

                <div
                    v-if="activeImport"
                    class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <ImportProgress :row="activeImport" />
                    <div class="mt-6">
                        <ImportErrorsTable
                            :errors="activeImport.errors ?? []"
                            :error-count="activeImport.error_count ?? 0"
                        />
                    </div>
                </div>

                <p v-else class="px-1 text-sm text-gray-500">
                    Upload a bookmarks file (Netscape HTML, CSV, JSON, or a plain list of
                    URLs).
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
