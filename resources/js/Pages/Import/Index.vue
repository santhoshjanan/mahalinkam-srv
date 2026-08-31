<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Placeholder stub. Task 20 replaces this with the real import UI
// (upload form, format picker, progress polling).
defineProps({
    activeImport: { type: Object, default: null },
    formats: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Import" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Import</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <p>Import UI coming soon.</p>
                    <pre v-if="activeImport">{{ activeImport }}</pre>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
