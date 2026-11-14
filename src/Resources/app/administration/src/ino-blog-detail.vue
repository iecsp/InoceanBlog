<template>
    <sw-page>
        <template #smart-bar-header>
            <h2>{{ entity?.translated?.[headingField] || $t(isPost ? 'ino-blog.newPost' : 'ino-blog.newCategory') }}</h2>
        </template>
        <template #language-switch>
            <sw-language-switch
                v-if="id"
                :disabled="loading || saving"
                :save-changes-function="saveForLanguageSwitch"
                :save-permission="canEdit"
                :abort-change-function="hasChanges"
                @on-change="load"
            />
        </template>
        <template #smart-bar-actions>
            <mt-button variant="secondary" icon-front="regular-long-arrow-left" :disabled="saving" @click="back">{{ $t('ino-blog.back') }}</mt-button>
            <mt-button v-if="isPost" variant="secondary" icon-front="regular-eye" :disabled="!entity || loading || saving" @click="openPreview">
                {{ $t('ino-blog.preview') }}
            </mt-button>
            <mt-button icon-front="regular-trash" v-if="id && acl.can('ino_blog.deleter')" variant="secondary" :disabled="loading || saving" @click="confirmDelete = true">
                {{ $t('ino-blog.delete') }}
            </mt-button>
            <mt-button icon-front="regular-save" v-if="canEdit" variant="primary" :disabled="loading || saving || !entity" @click="save">
                {{ $t('ino-blog.save') }}
            </mt-button>
        </template>
        <template #content>
            <sw-card-view>
                <mt-loader v-if="loading" />
                <template v-else-if="entity">
                    <mt-card :title="$t('ino-blog.details')" position-identifier="ino-blog-details">
                        <fieldset class="ino-blog-grid" :disabled="!canEdit || saving">
                            <mt-text-field
                                :class="{ 'ino-blog-wide': isPost }"
                                v-model="entity[headingField]"
                                :label="$t('ino-blog.' + headingField)"
                                :placeholder="entity.translated?.[headingField] || ''"
                                :required="true"
                                :maxlength="255"
                            />
                            <template v-if="isPost">
                                <mt-text-field v-model="entity.author" :label="$t('ino-blog.author')" required :maxlength="255" />
                                <sw-entity-single-select
                                    v-model:value="entity.salesChannelId"
                                    entity="sales_channel"
                                    :criteria="channelCriteria"
                                    :label="$t('ino-blog.channel')"
                                    :disabled="!canEdit || saving"
                                    required
                                />
                                <sw-entity-single-select
                                    v-model:value="entity.categoryId"
                                    entity="ino_blog_category"
                                    :label="$t('ino-blog.category')"
                                    :disabled="!canEdit || saving"
                                />
                                <mt-datepicker v-model="entity.publishedAt" :label="$t('ino-blog.publishedAt')" />
                                <sw-entity-multi-select
                                    v-model:entity-collection="entity.tags"
                                    entity-name="tag"
                                    :label="$t('ino-blog.tags')"
                                    :disabled="!canEdit || saving"
                                />
                                <mt-switch v-model="entity.active" :label="$t('ino-blog.active')" />
                                <mt-switch v-model="entity.pinned" :label="$t('ino-blog.pinned')" />
                                <mt-switch v-model="entity.indexable" :label="$t('ino-blog.indexable')" />
                            </template>
                            <template v-else>
                                <mt-number-field v-model="entity.position" :label="$t('ino-blog.position')" number-type="int" :min="0" />
                                <mt-textarea class="ino-blog-wide" v-model="entity.description" :label="$t('ino-blog.description')" />
                            </template>
                        </fieldset>
                    </mt-card>
                    <template v-if="isPost">
                        <mt-card :title="$t('ino-blog.content')" position-identifier="ino-blog-content">
                            <fieldset class="ino-blog-grid" :disabled="!canEdit || saving">
                                <mt-textarea class="ino-blog-wide"
                                    v-model="entity.excerpt"
                                    :label="$t('ino-blog.excerpt')"
                                    :placeholder="entity.translated?.excerpt || ''"
                                />
                                <mt-text-editor class="ino-blog-wide"
                                    :key="currentLanguage"
                                    :model-value="entity.content || entity.translated?.content || '<p></p>'"
                                    :label="$t('ino-blog.content')"
                                    :custom-buttons="editorButtons"
                                    :disabled="!canEdit || saving"
                                    @update:model-value="entity.content = $event"
                                />
                                <sw-media-field v-model:value="entity.coverId" :label="$t('ino-blog.cover')" file-accept="image/*" :disabled="!canEdit || saving" />
                                <sw-media-field v-model:value="entity.videoId" :label="$t('ino-blog.video')" file-accept="video/*" :disabled="!canEdit || saving" />
                            </fieldset>
                        </mt-card>
                        <mt-card :title="$t('ino-blog.seo')" position-identifier="ino-blog-seo">
                            <fieldset class="ino-blog-grid" :disabled="!canEdit || saving">
                                <mt-text-field v-model="entity.slug" :label="$t('ino-blog.slug')" :maxlength="100" />
                                <mt-text-field v-model="entity.metaTitle" :label="$t('ino-blog.metaTitle')" :maxlength="255" />
                                <mt-textarea class="ino-blog-wide" v-model="entity.metaDescription" :label="$t('ino-blog.metaDescription')" :maxlength="255" />
                            </fieldset>
                        </mt-card>
                    </template>
                </template>
            </sw-card-view>
            <sw-media-modal-v2
                v-if="imagePicker"
                file-accept="image/*"
                :allow-multi-select="false"
                @media-modal-selection-change="insertImage"
                @modal-close="closeImagePicker"
            />
            <sw-modal v-if="previewOpen" :title="$t('ino-blog.preview')" variant="large" @modal-close="previewOpen = false">
                <mt-loader v-if="previewLoading" />
                <article v-else-if="preview" class="ino-blog-preview">
                    <img v-if="preview.cover" class="ino-blog-preview__cover" :src="preview.cover.url" :alt="preview.cover.translated?.alt || ''" />
                    <h2>{{ preview.title }}</h2>
                    <p class="ino-blog-preview__byline">{{ [preview.author, preview.date].filter(Boolean).join(' · ') }}</p>
                    <p v-if="preview.excerpt" class="ino-blog-preview__excerpt">{{ preview.excerpt }}</p>
                    <video v-if="preview.video" controls preload="metadata" :src="preview.video.url" />
                    <div class="ino-blog-preview__body" v-html="preview.html"></div>
                </article>
                <p v-else>{{ $t('ino-blog.previewError') }}</p>
                <template #modal-footer>
                    <mt-button variant="secondary" icon-front="regular-times" @click="previewOpen = false">{{ $t('ino-blog.close') }}</mt-button>
                </template>
            </sw-modal>
            <sw-modal v-if="confirmDelete" :title="$t('ino-blog.delete')" @modal-close="confirmDelete = false">
                <p>{{ $t('ino-blog.deletePrompt') }}</p>
                <template #modal-footer>
                    <mt-button variant="secondary" icon-front="regular-times" @click="confirmDelete = false">{{ $t('ino-blog.cancel') }}</mt-button>
                    <mt-button variant="critical" icon-front="regular-trash" :disabled="saving" @click="remove">{{ $t('ino-blog.delete') }}</mt-button>
                </template>
            </sw-modal>
        </template>
    </sw-page>
</template>

<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { notify } from './notify';

const props = defineProps({
    kind: { type: String, required: true },
    id: { type: String, default: null },
});

const { Criteria } = Shopware.Data;
const acl = Shopware.Service('acl');
const repositoryFactory = Shopware.Service('repositoryFactory');
const vm = getCurrentInstance().proxy;
const entity = ref(null);
const loading = ref(false);
const saving = ref(false);
const currentLanguage = ref(null);
const confirmDelete = ref(false);
const imagePicker = ref(false);
const previewOpen = ref(false);
const previewLoading = ref(false);
const preview = ref(null);
let previewRequest = 0;
let editor = null;
let requestId = 0;

const isPost = computed(() => props.kind === 'posts');
const headingField = computed(() => isPost.value ? 'title' : 'name');
const repository = computed(() => repositoryFactory.create(isPost.value ? 'ino_blog_post' : 'ino_blog_category'));
const channelCriteria = new Criteria(1, 25).addFilter(Criteria.equals('typeId', Shopware.Defaults.apiSalesChannelTypeId));
const canEdit = computed(() => acl.can(props.id ? 'ino_blog.editor' : 'ino_blog.creator'));
const editorButtons = computed(() => [{
    name: 'ino-blog-image',
    label: vm.$t('ino-blog.insertImage'),
    icon: 'regular-image',
    action: (currentEditor) => {
        editor = currentEditor;
        imagePicker.value = true;
    },
}]);

function hasChanges() {
    return entity.value ? repository.value.hasChanges(entity.value) : false;
}

async function load() {
    const currentRequest = ++requestId;
    loading.value = true;
    entity.value = null;
    previewOpen.value = false;
    const context = {
        ...Shopware.Context.api,
        languageId: props.id ? Shopware.Context.api.languageId : Shopware.Defaults.systemLanguageId,
    };

    try {
        const criteria = new Criteria();
        if (isPost.value) criteria.addAssociation('tags');
        const entry = props.id ? await repository.value.get(props.id, context, criteria) : repository.value.create(context);
        if (currentRequest !== requestId) return;
        if (!entry) throw new Error('Missing entity');
        if (!props.id && isPost.value) {
            const user = Shopware.Store.get('session').currentUser;
            entry.author = [user?.firstName, user?.lastName].filter(Boolean).join(' ');
            entry.active = false;
            entry.pinned = false;
            entry.indexable = true;
        } else if (!props.id) {
            entry.position = 0;
        }
        currentLanguage.value = context.languageId;
        entity.value = entry;
    } catch {
        if (currentRequest === requestId) notify('ino-blog.loadError');
    } finally {
        if (currentRequest === requestId) loading.value = false;
    }
}

function back() {
    vm.$router.push({ name: `ino.blog.${props.kind}` });
}

async function save() {
    if (!canEdit.value || saving.value || !entity.value) return false;
    const entry = entity.value;
    if (!(entry[headingField.value] || entry.translated?.[headingField.value])?.trim()
        || (isPost.value && (!entry.author?.trim() || !entry.salesChannelId || (entry.active && !entry.publishedAt)))) {
        notify('ino-blog.required');
        return false;
    }

    saving.value = true;
    try {
        await repository.value.save(entry, { ...Shopware.Context.api, languageId: currentLanguage.value });
        notify('ino-blog.saved', 'success');
        if (!props.id) {
            Shopware.Store.get('context').setApiLanguageId(currentLanguage.value);
            await vm.$router.replace({ name: isPost.value ? 'ino.blog.postDetail' : 'ino.blog.categoryDetail', params: { id: entry.id } });
        } else {
            await load();
        }
        return true;
    } catch (error) {
        notify('ino-blog.saveError', 'error', error.response?.data?.errors?.[0]?.detail);
        return false;
    } finally {
        saving.value = false;
    }
}

async function saveForLanguageSwitch() {
    if (!(await save())) throw new Error('Blog entry could not be saved');
}

async function remove() {
    saving.value = true;
    try {
        await repository.value.delete(props.id);
        back();
    } catch (error) {
        notify('ino-blog.deleteError', 'error', error.response?.data?.errors?.[0]?.detail);
    } finally {
        saving.value = false;
        confirmDelete.value = false;
    }
}

async function openPreview() {
    const entry = entity.value;
    const request = ++previewRequest;
    preview.value = null;
    previewOpen.value = true;
    previewLoading.value = true;
    const fields = {
        title: entry.title ?? entry.translated?.title ?? '',
        excerpt: entry.excerpt ?? entry.translated?.excerpt ?? '',
        author: entry.author,
        date: entry.publishedAt ? Shopware.Filter.getByName('date')(entry.publishedAt) : '',
    };
    const mediaRepository = repositoryFactory.create('media');
    const loadMedia = async (id, mimePrefix) => {
        if (!id) return null;
        const media = await mediaRepository.get(id);
        return media && !media.private && media.mimeType?.startsWith(mimePrefix) ? media : null;
    };

    try {
        const http = Shopware.Application.getContainer('init').httpClient;
        const [{ data }, cover, video] = await Promise.all([
            http.post('_action/ino-blog/preview', { content: entry.content ?? entry.translated?.content ?? '' }, {
                headers: { Authorization: `Bearer ${Shopware.Service('loginService').getToken()}` },
            }),
            loadMedia(entry.coverId, 'image/'),
            loadMedia(entry.videoId, 'video/'),
        ]);
        if (request === previewRequest) preview.value = { ...fields, html: data.html, cover, video };
    } catch {
        if (request === previewRequest && previewOpen.value) notify('ino-blog.previewError');
    } finally {
        if (request === previewRequest) previewLoading.value = false;
    }
}

function closeImagePicker() {
    editor = null;
    imagePicker.value = false;
}

function insertImage(selection) {
    const image = selection.find((item) => !item.private && item.mimeType?.startsWith('image/') && item.url);
    if (image) {
        editor?.chain().focus().setImage({ src: image.url, alt: image.translated?.alt || image.alt || '' }).run();
    }
    closeImagePicker();
}

watch(() => [props.kind, props.id], load, { immediate: true });

swDefinePublic({});
</script>

<style scoped>
fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
.ino-blog-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 20px;
    align-items: start;
}
.ino-blog-wide { grid-column: 1 / -1; }
.ino-blog-preview { max-width: 780px; margin: auto; line-height: 1.65; overflow-wrap: anywhere; }
.ino-blog-preview h2 { font-size: 2rem; line-height: 1.2; margin: 16px 0 8px; }
.ino-blog-preview__byline { color: var(--color-text-secondary-default); margin-bottom: 16px; }
.ino-blog-preview__excerpt { font-size: 1.125rem; margin-bottom: 20px; }
.ino-blog-preview__cover, .ino-blog-preview video { width: 100%; border-radius: 8px; margin-bottom: 16px; }
.ino-blog-preview__cover { max-height: 380px; object-fit: cover; }
.ino-blog-preview__body :deep(img) { max-width: 100%; height: auto; }
.ino-blog-preview__body :deep(p) { margin-bottom: 1em; }
.ino-blog-preview__body :deep(ul), .ino-blog-preview__body :deep(ol) { padding-inline-start: 1.5em; }
.ino-blog-preview__body :deep(pre) { overflow: auto; }
@media (max-width: 700px) {
    .ino-blog-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
