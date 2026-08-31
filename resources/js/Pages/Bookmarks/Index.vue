<script setup>
// Placeholder shell — the full bookmark index UI is built in Task 16.
defineProps({
    bookmarks: { type: Object, required: true },
    folders: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div>
        <h1>Bookmarks</h1>
        <p>{{ bookmarks.data.length }} shown</p>
    </div>
</template>
