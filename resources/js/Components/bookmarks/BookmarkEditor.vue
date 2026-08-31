<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TagInput from './TagInput.vue';
import { flattenForSelect } from '@/lib/folderTree';

const props = defineProps({
    show: { type: Boolean, default: false },
    bookmark: { type: Object, default: null },
    folders: { type: Array, default: () => [] },
    allTags: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const page = usePage();

const isEdit = computed(() => !!props.bookmark);
const folderOptions = computed(() => flattenForSelect(props.folders));

const alreadySaved = ref(false);

const form = useForm({
    url: '',
    title: '',
    description: '',
    folder_id: null,
    tags: [],
});

watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }
        alreadySaved.value = false;
        form.clearErrors();
        form.defaults({
            url: props.bookmark?.url ?? '',
            title: props.bookmark?.title ?? '',
            description: props.bookmark?.description ?? '',
            folder_id: props.bookmark?.folder_id ?? null,
            tags: (props.bookmark?.tags ?? []).map((t) => t.name),
        });
        form.reset();
    },
);

const normalizedPreview = computed(() => {
    try {
        const u = new URL(form.url.trim());
        u.hash = '';
        const strip = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'utm_id', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'mc_cid',
            'mc_eid', 'ref', 'ref_src', 'igshid', 'si',
        ];
        strip.forEach((k) => u.searchParams.delete(k));
        u.protocol = u.protocol.toLowerCase();
        u.hostname = u.hostname.toLowerCase();
        let s = u.toString();
        if (s.endsWith('/') && u.pathname !== '/') {
            s = s.slice(0, -1);
        }

        return s;
    } catch {
        return '';
    }
});

function submit() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            const flash = page.props.flash;
            if (flash?.kind === 'already_saved') {
                alreadySaved.value = true;

                return;
            }
            emit('close');
        },
    };

    if (isEdit.value) {
        form.patch('/bookmarks/' + props.bookmark.id, opts);
    } else {
        form.post('/bookmarks', opts);
    }
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <form class="p-6" @submit.prevent="submit">
            <h2 class="text-lg font-medium text-gray-900">
                {{ isEdit ? 'Edit bookmark' : 'Add bookmark' }}
            </h2>

            <div
                v-if="alreadySaved"
                class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-800"
            >
                Already saved — editing the existing bookmark. Adjust the fields and save again.
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <InputLabel for="bm-url" value="URL" />
                    <TextInput
                        id="bm-url"
                        v-model="form.url"
                        type="url"
                        class="mt-1 block w-full"
                        required
                        :disabled="isEdit"
                    />
                    <p
                        v-if="normalizedPreview && normalizedPreview !== form.url.trim()"
                        class="mt-1 text-xs text-gray-500"
                    >
                        Saved as: <span class="font-mono">{{ normalizedPreview }}</span>
                    </p>
                    <InputError class="mt-1" :message="form.errors.url" />
                </div>

                <div>
                    <InputLabel for="bm-title" value="Title" />
                    <TextInput
                        id="bm-title"
                        v-model="form.title"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.title" />
                </div>

                <div>
                    <InputLabel for="bm-desc" value="Description" />
                    <textarea
                        id="bm-desc"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                    <InputError class="mt-1" :message="form.errors.description" />
                </div>

                <div>
                    <InputLabel for="bm-folder" value="Folder" />
                    <select
                        id="bm-folder"
                        v-model="form.folder_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option :value="null">Unfiled</option>
                        <option v-for="f in folderOptions" :key="f.id" :value="f.id">
                            {{ '  '.repeat(f.depth) + f.name }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.folder_id" />
                </div>

                <div>
                    <InputLabel value="Tags" />
                    <TagInput v-model="form.tags" :all-tags="allTags" class="mt-1" />
                    <InputError class="mt-1" :message="form.errors.tags" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" @click="emit('close')">Cancel</SecondaryButton>
                <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    {{ isEdit ? 'Save changes' : 'Save bookmark' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
