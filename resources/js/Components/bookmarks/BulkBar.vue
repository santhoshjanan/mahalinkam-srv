<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { flattenForSelect } from '@/lib/folderTree';

const props = defineProps({
    selected: { type: Array, default: () => [] },
    bookmarks: { type: Array, default: () => [] },
    folders: { type: Array, default: () => [] },
});

const emit = defineEmits(['clear']);

const moveTo = ref('');
const folderOptions = computed(() => flattenForSelect(props.folders));

function bulk(payload) {
    router.post(
        '/bookmarks/bulk',
        { ids: props.selected, ...payload },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => emit('clear'),
        },
    );
}

function move() {
    if (moveTo.value === '') {
        return;
    }
    bulk({
        action: 'move',
        folder_id: moveTo.value === 'unfiled' ? null : Number(moveTo.value),
    });
    moveTo.value = '';
}

function addTag() {
    const tag = window.prompt('Tag to add');
    if (!tag || !tag.trim()) {
        return;
    }
    bulk({ action: 'tag', tag: tag.trim() });
}

function removeTag() {
    const tag = window.prompt('Tag to remove');
    if (!tag || !tag.trim()) {
        return;
    }
    bulk({ action: 'untag', tag: tag.trim() });
}

function destroy() {
    if (!window.confirm(`Delete ${props.selected.length} bookmark(s)?`)) {
        return;
    }
    bulk({ action: 'delete' });
}

function openAll() {
    const urls = props.bookmarks
        .filter((b) => props.selected.includes(b.id))
        .map((b) => b.url);

    if (urls.length > 10 && !window.confirm(`Open ${urls.length} tabs?`)) {
        return;
    }
    urls.forEach((u) => window.open(u, '_blank', 'noopener'));
}
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm"
    >
        <span class="font-medium text-indigo-800">{{ selected.length }} selected</span>

        <select
            v-model="moveTo"
            class="rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            @change="move"
        >
            <option value="">Move to…</option>
            <option value="unfiled">Unfiled</option>
            <option v-for="f in folderOptions" :key="f.id" :value="String(f.id)">
                {{ '  '.repeat(f.depth) + f.name }}
            </option>
        </select>

        <button type="button" class="text-indigo-700 hover:underline" @click="addTag">
            Add tag
        </button>
        <button type="button" class="text-indigo-700 hover:underline" @click="removeTag">
            Remove tag
        </button>
        <button type="button" class="text-indigo-700 hover:underline" @click="openAll">
            Open all
        </button>
        <button type="button" class="text-red-600 hover:underline" @click="destroy">
            Delete
        </button>

        <button
            type="button"
            class="ml-auto text-gray-500 hover:text-gray-700"
            @click="emit('clear')"
        >
            Clear
        </button>
    </div>
</template>
