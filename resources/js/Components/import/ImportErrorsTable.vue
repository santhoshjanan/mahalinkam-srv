<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    errors: { type: Array, default: () => [] },
    errorCount: { type: Number, default: 0 },
});

const perPage = 25;
const page = ref(1);

const pageCount = computed(() => Math.max(1, Math.ceil(props.errors.length / perPage)));

watch(
    () => props.errors.length,
    () => {
        if (page.value > pageCount.value) {
            page.value = pageCount.value;
        }
    },
);

const pageRows = computed(() => {
    const start = (page.value - 1) * perPage;
    return props.errors.slice(start, start + perPage);
});

const truncated = computed(() => props.errorCount > props.errors.length);
</script>

<template>
    <div v-if="errors.length" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-800">Errors</h3>
            <p v-if="truncated" class="text-xs text-gray-500">
                Showing the first {{ errors.length }} errors
            </p>
        </div>

        <div class="overflow-x-auto rounded-md border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Row</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">URL</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Message</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="(err, i) in pageRows" :key="i">
                        <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ err.row }}</td>
                        <td class="max-w-xs truncate px-3 py-2 text-gray-700" :title="err.url">
                            {{ err.url }}
                        </td>
                        <td class="px-3 py-2 text-red-700">{{ err.message }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="pageCount > 1" class="flex items-center justify-between text-xs text-gray-500">
            <span>Page {{ page }} of {{ pageCount }}</span>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                    :disabled="page <= 1"
                    @click="page--"
                >
                    Prev
                </button>
                <button
                    type="button"
                    class="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                    :disabled="page >= pageCount"
                    @click="page++"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
