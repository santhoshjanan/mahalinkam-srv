<script setup>
import { computed } from 'vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const total = computed(() => {
    const t = Number(props.row.total_rows);
    return Number.isFinite(t) && t > 0 ? t : null;
});

const processed = computed(() => {
    const p = Number(props.row.processed_rows);
    return Number.isFinite(p) && p > 0 ? p : 0;
});

const indeterminate = computed(() => total.value === null);

const percent = computed(() => {
    if (total.value === null) {
        return 0;
    }
    return Math.min(100, Math.round((processed.value / total.value) * 100));
});

const isActive = computed(
    () => props.row.status === 'pending' || props.row.status === 'processing',
);

const statusLabel = computed(() => {
    switch (props.row.status) {
        case 'pending':
            return 'Pending';
        case 'processing':
            return 'Processing…';
        case 'completed':
            return 'Completed';
        case 'failed':
            return 'Failed';
        default:
            return props.row.status;
    }
});

const statusClass = computed(() => {
    switch (props.row.status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'failed':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-indigo-100 text-indigo-800';
    }
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm text-gray-600">
                <span v-if="row.original_filename" class="font-medium text-gray-800">{{
                    row.original_filename
                }}</span>
                <span v-if="row.format" class="ms-2 uppercase tracking-wide text-gray-400">{{
                    row.format
                }}</span>
            </div>
            <span
                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :class="statusClass"
            >
                {{ statusLabel }}
            </span>
        </div>

        <div>
            <div class="mb-1 flex justify-between text-xs text-gray-500">
                <span v-if="indeterminate">Working…</span>
                <span v-else>{{ processed }} / {{ total }} rows</span>
                <span v-if="!indeterminate">{{ percent }}%</span>
            </div>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    v-if="indeterminate && isActive"
                    class="h-full w-1/3 animate-pulse rounded-full bg-indigo-500"
                />
                <div
                    v-else
                    class="h-full rounded-full bg-indigo-500 transition-all duration-500"
                    :class="{ 'bg-green-500': row.status === 'completed', 'bg-red-500': row.status === 'failed' }"
                    :style="{ width: (indeterminate ? 100 : percent) + '%' }"
                />
            </div>
        </div>

        <dl class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-md bg-gray-50 p-3">
                <dt class="text-xs uppercase tracking-wide text-gray-500">Created</dt>
                <dd class="mt-1 text-lg font-semibold text-green-700">
                    {{ row.created_count ?? 0 }}
                </dd>
            </div>
            <div class="rounded-md bg-gray-50 p-3">
                <dt class="text-xs uppercase tracking-wide text-gray-500">Duplicates</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-700">
                    {{ row.duplicate_count ?? 0 }}
                </dd>
            </div>
            <div class="rounded-md bg-gray-50 p-3">
                <dt class="text-xs uppercase tracking-wide text-gray-500">Errors</dt>
                <dd class="mt-1 text-lg font-semibold text-red-700">
                    {{ row.error_count ?? 0 }}
                </dd>
            </div>
        </dl>
    </div>
</template>
