<template>
    <sw-page>
        <template #smart-bar-header>
            <h2>{{ $t('ino-blog.' + kind) }}</h2>
        </template>
        <template #language-switch>
            <sw-language-switch @on-change="load(1)" />
        </template>
        <template #smart-bar-actions>
            <mt-button
                v-if="acl.can('ino_blog.creator')" icon-front="regular-plus"
                variant="primary"
                @click="$router.push({ name: 'ino.blog.' + routePrefix + 'Create' })"
            >
                {{ $t(isPost ? 'ino-blog.newPost' : 'ino-blog.newCategory') }}
            </mt-button>
        </template>
        <template #content>
            <sw-card-view>
                <mt-card position-identifier="ino-blog-list">
                    <div class="ino-blog-list__actions">
                        <mt-button
                            icon-front="regular-file-text" :variant="isPost ? 'primary' : 'secondary'"
                            @click="$router.push({ name: 'ino.blog.posts' })"
                        >{{ $t('ino-blog.posts') }}</mt-button>
                        <mt-button
                            icon-front="regular-folder" :variant="isPost ? 'secondary' : 'primary'"
                            @click="$router.push({ name: 'ino.blog.categories' })"
                        >{{ $t('ino-blog.categories') }}</mt-button>
                    </div>
                    <form class="ino-blog-list__actions" @submit.prevent="load(1)">
                        <mt-text-field v-model="term" :label="$t('ino-blog.search')" />
                        <mt-button type="submit" icon-front="regular-search" variant="secondary">{{ $t('ino-blog.search') }}</mt-button>
                    </form>
                    <sw-data-grid
                        :key="kind"
                        :data-source="rows"
                        :columns="columns.map((column) => ({ ...column, label: $t(column.label) }))"
                        :is-loading="loading"
                        :show-selection="false"
                        :allow-inline-edit="false"
                        :show-actions="false"
                    >
                        <template #column-title="{ item }">
                            <router-link :to="detail(item.id)">{{ item.translated.title }}</router-link>
                        </template>
                        <template #column-name="{ item }">
                            <router-link :to="detail(item.id)">{{ item.translated.name }}</router-link>
                        </template>
                        <template #column-active="{ item }">{{ $t('ino-blog.' + status(item)) }}</template>
                        <template #column-publishedAt="{ item }">
                            {{ item.publishedAt ? formatDate(item.publishedAt) : '–' }}
                        </template>
                    </sw-data-grid>
                    <div class="ino-blog-list__actions ino-blog-list__pagination" v-if="total">
                        <mt-button variant="secondary" icon-front="regular-chevron-left-xs" :disabled="page === 1 || loading" @click="load(page - 1)">
                            {{ $t('ino-blog.previous') }}
                        </mt-button>
                        <span>{{ $t('ino-blog.page', { page, pages: Math.ceil(total / limit) }) }}</span>
                        <mt-button variant="secondary" icon-back="regular-chevron-right-xs" :disabled="page * limit >= total || loading" @click="load(page + 1)">
                            {{ $t('ino-blog.next') }}
                        </mt-button>
                    </div>
                </mt-card>
            </sw-card-view>
        </template>
    </sw-page>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { notify } from './notify';

const props = defineProps({ kind: { type: String, required: true } });
const { Criteria } = Shopware.Data;
const acl = Shopware.Service('acl');
const repositoryFactory = Shopware.Service('repositoryFactory');
const rows = ref([]);
const page = ref(1);
const limit = 25;
const total = ref(0);
const term = ref('');
const loading = ref(false);
let requestId = 0;

const isPost = computed(() => props.kind === 'posts');
const routePrefix = computed(() => isPost.value ? 'post' : 'category');
const columns = computed(() => {
    const fields = isPost.value ? ['title', 'author', 'active', 'publishedAt'] : ['name', 'position'];
    return fields.map((property) => ({ property, label: 'ino-blog.' + property, sortable: false, primary: property === fields[0] }));
});

function detail(id) {
    return { name: `ino.blog.${routePrefix.value}Detail`, params: { id } };
}

function formatDate(date) {
    return Shopware.Filter.getByName('date')(date);
}

function status(item) {
    const date = item.publishedAt;
    return !item.active || !date ? 'draft' : Date.parse(date) > Date.now() ? 'scheduled' : 'published';
}

async function load(nextPage) {
    const currentRequest = ++requestId;
    loading.value = true;
    const criteria = new Criteria(nextPage, limit);
    criteria.setTotalCountMode(1);
    if (term.value.trim()) criteria.setTerm(term.value.trim());
    criteria.addSorting(Criteria.sort(isPost.value ? 'createdAt' : 'position', isPost.value ? 'DESC' : 'ASC'));
    criteria.addSorting(Criteria.sort('id', 'ASC'));

    try {
        const items = await repositoryFactory.create(`ino_blog_${routePrefix.value}`).search(criteria);
        if (currentRequest !== requestId) return;
        rows.value = items;
        page.value = nextPage;
        total.value = items.total;
    } catch {
        if (currentRequest === requestId) notify('ino-blog.loadError');
    } finally {
        if (currentRequest === requestId) loading.value = false;
    }
}

watch(() => props.kind, () => {
    rows.value = [];
    term.value = '';
    load(1);
}, { immediate: true });

swDefinePublic({});
</script>

<style scoped>
.ino-blog-list__actions {
    display: flex;
    align-items: end;
    gap: 12px;
    margin-bottom: 16px;
}
.ino-blog-list__actions :deep(.mt-field) { margin-bottom: 0; }
.ino-blog-list__pagination { align-items: center; margin: 16px 0 0; }
</style>
