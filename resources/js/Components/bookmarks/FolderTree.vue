<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import FolderTreeNode from './FolderTreeNode.vue';
import { buildTree } from '@/lib/folderTree';

const props = defineProps({
    folders: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const tree = computed(() => buildTree(props.folders));

const current = computed(() => String(props.filters.folder_id ?? ''));

function go(folderId) {
    router.get(
        '/',
        { ...props.filters, folder_id: folderId, page: undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function addRoot() {
    const name = window.prompt('New folder name');
    if (!name || !name.trim()) {
        return;
    }
    router.post('/folders', { name: name.trim(), parent_id: null }, { preserveScroll: true });
}
</script>

<template>
    <div>
        <div class="mb-2 flex items-center justify-between px-2">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Folders
            </h3>
            <button
                type="button"
                class="text-xs text-indigo-600 hover:text-indigo-800"
                @click="addRoot"
            >
                + New
            </button>
        </div>

        <ul class="space-y-0.5">
            <li>
                <button
                    type="button"
                    class="w-full rounded px-2 py-1 text-left text-sm hover:bg-gray-100"
                    :class="{ 'bg-indigo-50 font-medium text-indigo-700': current === '' }"
                    @click="go(undefined)"
                >
                    All bookmarks
                </button>
            </li>
            <li>
                <button
                    type="button"
                    class="w-full rounded px-2 py-1 text-left text-sm hover:bg-gray-100"
                    :class="{ 'bg-indigo-50 font-medium text-indigo-700': current === 'unfiled' }"
                    @click="go('unfiled')"
                >
                    Unfiled
                </button>
            </li>

            <FolderTreeNode
                v-for="node in tree"
                :key="node.id"
                :node="node"
                :filters="filters"
                :all-folders="folders"
            />
        </ul>
    </div>
</template>
