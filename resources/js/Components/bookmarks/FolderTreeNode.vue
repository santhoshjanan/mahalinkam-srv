<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { flattenForSelect } from '@/lib/folderTree';

const props = defineProps({
    node: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    allFolders: { type: Array, default: () => [] },
    depth: { type: Number, default: 0 },
});

const open = ref(true);
const showMove = ref(false);
const moveTarget = ref('');

const isActive = computed(
    () => String(props.filters.folder_id ?? '') === String(props.node.id),
);

// Folders that are not this node and not one of its descendants.
const moveOptions = computed(() => {
    const banned = new Set([props.node.id]);
    const walk = (n) => {
        for (const c of n.children ?? []) {
            banned.add(c.id);
            walk(c);
        }
    };
    walk(props.node);

    return flattenForSelect(props.allFolders).filter((f) => !banned.has(f.id));
});

function select() {
    router.get(
        '/',
        { ...props.filters, folder_id: props.node.id, page: undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function addChild() {
    const name = window.prompt('New folder name');
    if (!name || !name.trim()) {
        return;
    }
    router.post(
        '/folders',
        { name: name.trim(), parent_id: props.node.id },
        { preserveScroll: true },
    );
}

function rename() {
    const name = window.prompt('Rename folder', props.node.name);
    if (!name || !name.trim() || name.trim() === props.node.name) {
        return;
    }
    router.patch('/folders/' + props.node.id, { name: name.trim() }, { preserveScroll: true });
}

function destroy() {
    if (!window.confirm(`Delete "${props.node.name}"? Its bookmarks move to Unfiled.`)) {
        return;
    }
    router.delete('/folders/' + props.node.id, { preserveScroll: true });
}

function submitMove() {
    router.patch(
        '/folders/' + props.node.id + '/move',
        { parent_id: moveTarget.value === '' ? null : Number(moveTarget.value) },
        {
            preserveScroll: true,
            onSuccess: () => {
                showMove.value = false;
            },
        },
    );
}
</script>

<template>
    <li>
        <div
            class="group flex items-center gap-1 rounded px-2 py-1 text-sm hover:bg-gray-100"
            :class="{ 'bg-indigo-50 font-medium text-indigo-700': isActive }"
            :style="{ paddingLeft: depth * 12 + 8 + 'px' }"
        >
            <button
                v-if="node.children.length"
                type="button"
                class="text-gray-400 hover:text-gray-600"
                @click="open = !open"
            >
                <span v-if="open">▾</span><span v-else>▸</span>
            </button>
            <span v-else class="w-3"></span>

            <button type="button" class="flex-1 truncate text-left" @click="select">
                {{ node.name }}
            </button>

            <Dropdown align="right" width="48">
                <template #trigger>
                    <button
                        type="button"
                        class="px-1 text-gray-400 opacity-0 hover:text-gray-700 group-hover:opacity-100"
                    >
                        &#8942;
                    </button>
                </template>
                <template #content>
                    <button
                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                        @click="addChild"
                    >
                        New subfolder
                    </button>
                    <button
                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                        @click="rename"
                    >
                        Rename
                    </button>
                    <button
                        class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                        @click="showMove = true"
                    >
                        Move…
                    </button>
                    <button
                        class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-gray-100"
                        @click="destroy"
                    >
                        Delete
                    </button>
                </template>
            </Dropdown>
        </div>

        <ul v-if="open && node.children.length">
            <FolderTreeNode
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :filters="filters"
                :all-folders="allFolders"
                :depth="depth + 1"
            />
        </ul>

        <Modal :show="showMove" max-width="md" @close="showMove = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Move "{{ node.name }}"</h2>
                <p class="mt-1 text-sm text-gray-500">Choose a new parent folder.</p>

                <select
                    v-model="moveTarget"
                    class="mt-4 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">— Top level —</option>
                    <option v-for="f in moveOptions" :key="f.id" :value="String(f.id)">
                        {{ '  '.repeat(f.depth) + f.name }}
                    </option>
                </select>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showMove = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitMove">Move</PrimaryButton>
                </div>
            </div>
        </Modal>
    </li>
</template>
