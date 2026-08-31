<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { host as urlHost } from '@/lib/urlDisplay';

const props = defineProps({
    bookmark: { type: Object, required: true },
    checked: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'edit']);

const faviconFailed = ref(false);

const host = computed(() => urlHost(props.bookmark.url));
const displayTitle = computed(() => props.bookmark.title || host.value);

const addedAt = computed(() => relative(props.bookmark.created_at));

function relative(iso) {
    if (!iso) {
        return '';
    }
    const then = new Date(iso).getTime();
    const secs = Math.round((Date.now() - then) / 1000);
    const table = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, size] of table) {
        const n = Math.floor(secs / size);
        if (n >= 1) {
            return `${n} ${unit}${n > 1 ? 's' : ''} ago`;
        }
    }

    return 'just now';
}

function destroy() {
    if (!window.confirm('Delete this bookmark?')) {
        return;
    }
    router.delete('/bookmarks/' + props.bookmark.id, { preserveScroll: true });
}

function retry() {
    // NOTE: POST /bookmarks/{id}/refetch is added in Task 17 — inert for now.
    router.post('/bookmarks/' + props.bookmark.id + '/refetch', {}, { preserveScroll: true });
}
</script>

<template>
    <div class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50">
        <input
            type="checkbox"
            class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            :checked="checked"
            @change="emit('toggle', bookmark.id)"
        />

        <img
            v-if="bookmark.favicon_url && !faviconFailed"
            :src="bookmark.favicon_url"
            alt=""
            class="mt-0.5 h-4 w-4 shrink-0 rounded-sm"
            @error="faviconFailed = true"
        />
        <svg
            v-else
            class="mt-0.5 h-4 w-4 shrink-0 text-gray-300"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
        >
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18" />
        </svg>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <a
                    :href="bookmark.url"
                    target="_blank"
                    rel="noopener"
                    class="truncate font-medium text-gray-900 hover:text-indigo-600 hover:underline"
                >
                    {{ displayTitle }}
                </a>

                <span
                    v-if="bookmark.metadata_status === 'pending'"
                    class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700"
                >
                    fetching…
                </span>
                <span
                    v-else-if="bookmark.metadata_status === 'failed'"
                    class="inline-flex items-center gap-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700"
                >
                    <span aria-hidden="true">!</span>
                    <button type="button" class="underline hover:no-underline" @click="retry">
                        retry
                    </button>
                </span>
            </div>

            <div class="mt-0.5 truncate text-xs text-gray-500">{{ host }}</div>

            <p
                v-if="bookmark.description"
                class="mt-1 line-clamp-2 text-sm text-gray-600"
            >
                {{ bookmark.description }}
            </p>

            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <span
                    v-for="tag in bookmark.tags"
                    :key="tag.id"
                    class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600"
                >
                    {{ tag.name }}
                </span>
                <span class="text-xs text-gray-400">{{ addedAt }}</span>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2 text-xs">
            <a
                :href="bookmark.url"
                target="_blank"
                rel="noopener"
                class="text-gray-500 hover:text-indigo-600"
            >
                Open
            </a>
            <button
                type="button"
                class="text-gray-500 hover:text-indigo-600"
                @click="emit('edit', bookmark)"
            >
                Edit
            </button>
            <button
                type="button"
                class="text-gray-500 hover:text-red-600"
                @click="destroy"
            >
                Delete
            </button>
        </div>
    </div>
</template>
