<template>
    <sw-page>
        <template #search-bar>
            <sw-search-bar
                :key="kind"
                :initial-search-type="'ino_blog_' + routePrefix"
                :initial-search="term"
                :type-search-always-in-container="false"
                @search="search"
            />
        </template>
        <template #smart-bar-header>
            <h2>
                {{ $t('ino-blog.' + kind) }}
                <span v-if="!loading && !failed" class="sw-page__smart-bar-amount">({{ total }})</span>
            </h2>
        </template>
        <template #language-switch>
            <sw-language-switch @on-change="load(1)" />
        </template>
        <template #smart-bar-actions>
            <mt-button
                :icon-front="isPost ? 'regular-folder' : 'regular-file-text'"
                variant="secondary"
                @click="$router.push({ name: isPost ? 'ino.blog.categories' : 'ino.blog.posts' })"
            >{{ $t(isPost ? 'ino-blog.categories' : 'ino-blog.posts') }}</mt-button>
            <mt-button
                v-if="acl.can('ino_blog.creator')"
                icon-front="regular-plus"
                variant="primary"
                @click="$router.push({ name: 'ino.blog.' + routePrefix + 'Create' })"
            >{{ $t(isPost ? 'ino-blog.newPost' : 'ino-blog.newCategory') }}</mt-button>
        </template>
        <template #content>
            <mt-banner v-if="failed" variant="critical">{{ $t('ino-blog.loadError') }}</mt-banner>
            <mt-empty-state
                v-else-if="!loading && !total"
                centered
                :icon="isPost ? 'regular-file-text' : 'regular-folder'"
                :headline="$t('ino-blog.noResults')"
            />
            <sw-data-grid
                v-else
                :key="kind"
                full-page
                :data-source="rows"
                :columns="columns.map((column) => ({ ...column, label: $t(column.label) }))"
                :is-loading="loading"
                :show-selection="false"
                :allow-inline-edit="false"
                :show-settings="canConfigureGrid"
                :identifier="canConfigureGrid ? 'ino-blog-list-' + kind : undefined"
                :sort-by="sortBy"
                :sort-direction="sortDirection"
                @column-sort="sort"
            >
                <template #column-title="{ item }">
                    <router-link :to="detail(item.id)">{{ item.translated.title }}</router-link>
                </template>
                <template #column-name="{ item }">
                    <router-link :to="detail(item.id)">{{ item.translated.name }}</router-link>
                </template>
                <template #column-category="{ item }">{{ item.category?.translated?.name || '—' }}</template>
                <template #column-active="{ item }">{{ $t('ino-blog.' + status(item)) }}</template>
                <template #column-publishedAt="{ item }">{{ formatDate(item.publishedAt) }}</template>
                <template #column-createdAt="{ item }">{{ formatDate(item.createdAt) }}</template>
                <template #actions="{ item }">
                    <sw-context-menu-item :router-link="detail(item.id)">
                        {{ $t(acl.can('ino_blog.editor') ? 'ino-blog.edit' : 'ino-blog.view') }}
                    </sw-context-menu-item>
                </template>
                <template #pagination>
                    <sw-pagination :page="page" :limit="limit" :total="total" :total-visible="7" @page-change="changePage" />
                </template>
            </sw-data-grid>
        </template>
        <template #sidebar>
            <sw-sidebar :key="kind">
                <sw-sidebar-item icon="regular-undo" :title="$t('ino-blog.refresh')" @click="load(page)" />
                <sw-sidebar-item v-if="isPost" icon="regular-filter" :title="$t('ino-blog.filters')" :badge="appliedStatus ? 1 : 0">
                    <form @submit.prevent="applyFilter">
                        <mt-select
                            v-model="statusFilter"
                            :label="$t('ino-blog.status')"
                            :options="['draft', 'scheduled', 'published'].map((value) => ({ value, label: $t('ino-blog.' + value) }))"
                        />
                        <mt-button v-if="appliedStatus" variant="secondary" :disabled="loading" @click="resetFilter">
                            {{ $t('ino-blog.clearFilter') }}
                        </mt-button>
                        <mt-button type="submit" variant="primary" :disabled="loading">{{ $t('ino-blog.applyFilter') }}</mt-button>
                    </form>
                </sw-sidebar-item>
            </sw-sidebar>
        </template>
    </sw-page>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({ kind: { type: String, required: true } });
const { Criteria } = Shopware.Data;
const acl = Shopware.Service('acl');
const repositoryFactory = Shopware.Service('repositoryFactory');
const rows = ref([]);
const page = ref(1);
const limit = ref(25);
const total = ref(0);
const term = ref('');
const loading = ref(false);
const failed = ref(false);
const sortBy = ref('createdAt');
const sortDirection = ref('DESC');
const statusFilter = ref(null);
const appliedStatus = ref(null);
let requestId = 0;

const isPost = computed(() => props.kind === 'posts');
const routePrefix = computed(() => isPost.value ? 'post' : 'category');
const columns = computed(() => {
    const fields = isPost.value ? ['title', 'category', 'active', 'author', 'publishedAt', 'createdAt'] : ['name', 'position', 'createdAt'];
    return fields.map((property) => ({
        property,
        label: 'ino-blog.' + (property === 'active' ? 'status' : property),
        sortable: !['category', 'active'].includes(property),
        allowResize: true,
        primary: property === fields[0],
    }));
});

const canConfigureGrid = computed(() =>
    ['read', 'create', 'update'].every((action) => acl.can('user_config:' + action)),
);

function detail(id) {
    return { name: `ino.blog.${routePrefix.value}Detail`, params: { id } };
}

function formatDate(date) {
    return date ? new Date(date).toLocaleString(Shopware.Store.get('session').currentLocale) : '—';
}

function status(item) {
    const date = item.publishedAt;
    return !item.active || !date ? 'draft' : Date.parse(date) > Date.now() ? 'scheduled' : 'published';
}

function search(value) {
    const query = value?.trim() ?? '';
    if (query !== term.value) {
        term.value = query;
        load(1);
    }
}

function changePage({ page: nextPage, limit: nextLimit }) {
    limit.value = nextLimit;
    load(nextPage);
}

function sort({ property }) {
    if (!columns.value.some((column) => column.property === property && column.sortable)) return;
    sortDirection.value = sortBy.value === property && sortDirection.value === 'ASC' ? 'DESC' : 'ASC';
    sortBy.value = property;
    load(1);
}

function applyFilter() {
    appliedStatus.value = statusFilter.value;
    load(1);
}

function resetFilter() {
    statusFilter.value = null;
    applyFilter();
}

function addStatusFilter(criteria) {
    const filter = appliedStatus.value;
    if (!filter) return;
    if (filter === 'draft') {
        criteria.addFilter(Criteria.multi('OR', [Criteria.equals('active', false), Criteria.equals('publishedAt', null)]));
        return;
    }

    const comparison = filter === 'scheduled' ? 'gt' : 'lte';
    criteria.addFilter(Criteria.equals('active', true));
    criteria.addFilter(Criteria.range('publishedAt', { [comparison]: new Date().toISOString() }));
}

async function load(nextPage) {
    const currentRequest = ++requestId;
    loading.value = true;
    failed.value = false;
    const criteria = new Criteria(nextPage, limit.value);
    criteria.setTotalCountMode(1);
    if (term.value.trim()) criteria.setTerm(term.value.trim());
    criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value));
    if (isPost.value) {
        criteria.addAssociation('category');
        addStatusFilter(criteria);
    }
    criteria.addSorting(Criteria.sort('id', 'ASC'));

    try {
        const items = await repositoryFactory.create(`ino_blog_${routePrefix.value}`).search(criteria);
        if (currentRequest !== requestId) return;
        rows.value = items;
        page.value = nextPage;
        total.value = items.total;
    } catch {
        if (currentRequest === requestId) failed.value = true;
    } finally {
        if (currentRequest === requestId) loading.value = false;
    }
}

watch(() => props.kind, () => {
    rows.value = [];
    term.value = '';
    total.value = 0;
    sortBy.value = isPost.value ? 'createdAt' : 'position';
    sortDirection.value = isPost.value ? 'DESC' : 'ASC';
    resetFilter();
}, { immediate: true });

swDefinePublic({});
</script>
