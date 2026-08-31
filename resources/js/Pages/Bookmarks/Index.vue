<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FolderTree from '@/Components/bookmarks/FolderTree.vue';
import TagFilter from '@/Components/bookmarks/TagFilter.vue';
import BookmarkList from '@/Components/bookmarks/BookmarkList.vue';
import BulkBar from '@/Components/bookmarks/BulkBar.vue';
import BookmarkEditor from '@/Components/bookmarks/BookmarkEditor.vue';

const props = defineProps({
    bookmarks: { type: Object, required: true },
    folders: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.q ?? '');
const sort = ref(props.filters.sort ?? 'created_desc');
const selected = ref([]);

const showEditor = ref(false);
const editingBookmark = ref(null);

const allTagNames = computed(() => props.tags.map((t) => t.name));

const rows = computed(() => props.bookmarks.data ?? []);

const hasActiveFilter = computed(
    () =>
        !!props.filters.q ||
        props.filters.folder_id !== undefined ||
        (props.filters.tags ?? []).length > 0,
);

const emptyKind = computed(() => {
    if (rows.value.length > 0) {
        return null;
    }
    if (props.filters.q) {
        return 'no-results';
    }
    if (props.filters.folder_id !== undefined) {
        return 'empty-folder';
    }
    if ((props.filters.tags ?? []).length > 0) {
        return 'no-results';
    }

    return 'nothing';
});

function navigate(overrides) {
    const params = {
        q: search.value || undefined,
        folder_id: props.filters.folder_id,
        tags: props.filters.tags,
        sort: sort.value !== 'created_desc' ? sort.value : undefined,
        page: undefined,
        ...overrides,
    };

    router.get('/', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let debounceTimer = null;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => navigate({}), 300);
});

watch(sort, () => navigate({}));

// Drop selections that are no longer on the page after a reload.
watch(rows, (list) => {
    const ids = new Set(list.map((b) => b.id));
    selected.value = selected.value.filter((id) => ids.has(id));
});

function toggle(id) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((x) => x !== id)
        : [...selected.value, id];
}

function openCreate() {
    editingBookmark.value = null;
    showEditor.value = true;
}

function openEdit(bookmark) {
    editingBookmark.value = bookmark;
    showEditor.value = true;
}
</script>

<template>
    <Head title="Bookmarks" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Bookmarks</h2>

                <div class="ms-auto flex flex-wrap items-center gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search…"
                        class="w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />

                    <select
                        v-model="sort"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="created_desc">Newest</option>
                        <option value="created_asc">Oldest</option>
                        <option value="title_asc">Title A–Z</option>
                    </select>

                    <Link
                        href="/import"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Import
                    </Link>

                    <span
                        class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-gray-500"
                    >
                        <span>Export</span>
                        <a href="/export?format=html" class="hover:text-gray-900">HTML</a>
                        <a href="/export?format=csv" class="hover:text-gray-900">CSV</a>
                        <a href="/export?format=json" class="hover:text-gray-900">JSON</a>
                    </span>

                    <PrimaryButton @click="openCreate">Add bookmark</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 md:grid-cols-[260px_1fr]">
                    <aside class="space-y-6">
                        <FolderTree :folders="folders" :filters="filters" />
                        <TagFilter :tags="tags" :filters="filters" />
                    </aside>

                    <div class="space-y-4">
                        <BulkBar
                            v-if="selected.length"
                            :selected="selected"
                            :bookmarks="rows"
                            :folders="folders"
                            @clear="selected = []"
                        />

                        <div
                            v-if="emptyKind === 'nothing'"
                            class="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center"
                        >
                            <p class="text-sm text-gray-600">No bookmarks yet.</p>
                            <div class="mt-4 flex justify-center gap-3">
                                <PrimaryButton @click="openCreate">Add your first bookmark</PrimaryButton>
                                <Link
                                    href="/import"
                                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50"
                                >
                                    Import a list
                                </Link>
                            </div>
                        </div>

                        <div
                            v-else-if="emptyKind === 'empty-folder'"
                            class="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center text-sm text-gray-600"
                        >
                            This folder is empty.
                        </div>

                        <div
                            v-else-if="emptyKind === 'no-results'"
                            class="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center text-sm text-gray-600"
                        >
                            No bookmarks match your filters.
                        </div>

                        <template v-else>
                            <BookmarkList
                                :bookmarks="rows"
                                :selected="selected"
                                @toggle="toggle"
                                @edit="openEdit"
                            />

                            <nav
                                v-if="(bookmarks.links ?? []).length > 3"
                                class="flex flex-wrap gap-1"
                            >
                                <component
                                    :is="link.url ? Link : 'span'"
                                    v-for="(link, i) in bookmarks.links"
                                    :key="i"
                                    :href="link.url || undefined"
                                    preserve-scroll
                                    class="rounded px-3 py-1 text-sm"
                                    :class="[
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-white text-gray-600 hover:bg-gray-100',
                                        !link.url && 'cursor-default text-gray-300',
                                    ]"
                                    v-html="link.label"
                                />
                            </nav>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <BookmarkEditor
            :show="showEditor"
            :bookmark="editingBookmark"
            :folders="folders"
            :all-tags="allTagNames"
            @close="showEditor = false"
        />
    </AuthenticatedLayout>
</template>
