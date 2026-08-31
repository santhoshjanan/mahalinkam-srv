<script setup>
import BookmarkRow from './BookmarkRow.vue';

defineProps({
    bookmarks: { type: Array, default: () => [] },
    selected: { type: Array, default: () => [] },
});

const emit = defineEmits(['toggle', 'edit']);
</script>

<template>
    <div class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white shadow-sm">
        <BookmarkRow
            v-for="bookmark in bookmarks"
            :key="bookmark.id"
            :bookmark="bookmark"
            :checked="selected.includes(bookmark.id)"
            @toggle="emit('toggle', $event)"
            @edit="emit('edit', $event)"
        />
    </div>
</template>
