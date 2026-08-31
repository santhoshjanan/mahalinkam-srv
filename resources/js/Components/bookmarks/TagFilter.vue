<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    tags: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

function active(name) {
    return (props.filters.tags ?? []).some((t) => t === name);
}

function toggle(name) {
    const current = props.filters.tags ?? [];
    const next = active(name)
        ? current.filter((t) => t !== name)
        : [...current, name];

    router.get(
        '/',
        { ...props.filters, tags: next, page: undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <div>
        <h3 class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
            Tags
        </h3>

        <p v-if="!tags.length" class="px-2 text-sm text-gray-400">No tags yet.</p>

        <ul class="space-y-0.5">
            <li v-for="tag in tags" :key="tag.id">
                <label
                    class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-100"
                >
                    <input
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        :checked="active(tag.name)"
                        @change="toggle(tag.name)"
                    />
                    <span class="flex-1 truncate">{{ tag.name }}</span>
                    <span class="text-xs text-gray-400">{{ tag.bookmarks_count ?? 0 }}</span>
                </label>
            </li>
        </ul>
    </div>
</template>
