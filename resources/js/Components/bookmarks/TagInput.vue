<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    allTags: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');

const suggestions = computed(() => {
    const q = draft.value.trim().toLowerCase();
    if (!q) {
        return [];
    }

    return props.allTags
        .filter(
            (name) =>
                name.toLowerCase().includes(q) &&
                !props.modelValue.some((t) => t.toLowerCase() === name.toLowerCase()),
        )
        .slice(0, 8);
});

function commit(raw) {
    const name = (raw ?? draft.value).trim().replace(/,$/, '').trim();
    draft.value = '';
    if (!name) {
        return;
    }
    if (props.modelValue.some((t) => t.toLowerCase() === name.toLowerCase())) {
        return;
    }
    emit('update:modelValue', [...props.modelValue, name]);
}

function removeAt(i) {
    const next = props.modelValue.slice();
    next.splice(i, 1);
    emit('update:modelValue', next);
}

function onKeydown(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        commit();
    } else if (e.key === 'Backspace' && draft.value === '' && props.modelValue.length) {
        removeAt(props.modelValue.length - 1);
    }
}
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-1 rounded-md border border-gray-300 p-1.5 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500"
    >
        <span
            v-for="(tag, i) in modelValue"
            :key="tag"
            class="inline-flex items-center gap-1 rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800"
        >
            {{ tag }}
            <button
                type="button"
                class="text-indigo-500 hover:text-indigo-900"
                @click="removeAt(i)"
            >
                &times;
            </button>
        </span>

        <div class="relative min-w-[8rem] flex-1">
            <input
                v-model="draft"
                type="text"
                class="w-full border-0 p-0.5 text-sm focus:ring-0"
                placeholder="Add a tag…"
                @keydown="onKeydown"
                @blur="commit()"
            />
            <ul
                v-if="suggestions.length"
                class="absolute left-0 top-full z-20 mt-1 w-48 overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg"
            >
                <li
                    v-for="s in suggestions"
                    :key="s"
                    class="cursor-pointer px-3 py-1.5 text-sm hover:bg-indigo-50"
                    @mousedown.prevent="commit(s)"
                >
                    {{ s }}
                </li>
            </ul>
        </div>
    </div>
</template>
