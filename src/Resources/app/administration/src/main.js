import { h } from 'vue';
import en from './snippet/en-GB.json';
import zhCN from './snippet/zh-CN.json';
import zhTW from './snippet/zh-TW.json';

for (const [locale, snippets] of Object.entries({ 'en-GB': en, 'zh-CN': zhCN, 'zh-TW': zhTW, 'zh-HK': zhTW })) {
    Shopware.Locale.extend(locale, snippets);
}

const entities = ['ino_blog_post', 'ino_blog_category', 'ino_blog_post_translation', 'ino_blog_category_translation', 'ino_blog_post_tag'];
const permissions = (action) => entities.map((entity) => `${entity}:${action}`);

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'ino_blog',
    roles: {
        viewer: { privileges: [...permissions('read'), 'sales_channel:read', 'media:read', 'tag:read'], dependencies: [] },
        editor: { privileges: [...permissions('update'), 'ino_blog_post_tag:create', 'ino_blog_post_tag:delete'], dependencies: ['ino_blog.viewer'] },
        creator: { privileges: permissions('create'), dependencies: ['ino_blog.editor'] },
        deleter: { privileges: permissions('delete'), dependencies: ['ino_blog.viewer'] },
    },
});

function registerPage(name, loadPage) {
    Shopware.Component.register(name, async () => {
        const { default: Page } = await loadPage();

        return {
            props: { kind: String, id: String },
            render() {
                return h(Page, { kind: this.kind, id: this.id });
            },
        };
    });
}

registerPage('ino-blog-list', () => import('./ino-blog-list.vue'));
registerPage('ino-blog-detail', () => import('./ino-blog-detail.vue'));

const route = (component, path, kind, privilege, detail = false) => ({
    components: { default: component },
    path,
    props: { default: (current) => ({ kind, ...(detail ? { id: current.params.id } : {}) }) },
    meta: {
        privilege: `ino_blog.${privilege}`,
        ...(detail ? { parentPath: `ino.blog.${kind}` } : {}),
    },
});

Shopware.Application.addServiceProviderDecorator('searchTypeService', (searchTypeService) => {
    for (const [entityName, section, snippet] of [
        ['ino_blog_post', 'posts', 'searchPosts'],
        ['ino_blog_category', 'categories', 'searchCategories'],
    ]) {
        searchTypeService.upsertType(entityName, {
            entityName,
            placeholderSnippet: 'ino-blog.' + snippet,
            listingRoute: 'ino.blog.' + section,
            hideOnGlobalSearchBar: true,
        });
    }

    return searchTypeService;
});

Shopware.Module.register('ino-blog', {
    type: 'plugin',
    name: 'InoceanBlog',
    title: 'ino-blog.blog',
    description: 'ino-blog.posts',
    version: '1.1.0',
    targetVersion: '1.1.0',
    icon: 'regular-file-text',
    color: '#785af0',
    routes: {
        posts: route('ino-blog-list', 'posts', 'posts', 'viewer'),
        categories: route('ino-blog-list', 'categories', 'categories', 'viewer'),
        postCreate: route('ino-blog-detail', 'posts/create', 'posts', 'creator', true),
        postDetail: route('ino-blog-detail', 'posts/detail/:id', 'posts', 'viewer', true),
        categoryCreate: route('ino-blog-detail', 'categories/create', 'categories', 'creator', true),
        categoryDetail: route('ino-blog-detail', 'categories/detail/:id', 'categories', 'viewer', true),
    },
    navigation: [{
        id: 'ino-blog',
        label: 'ino-blog.blog',
        path: 'ino.blog.posts',
        parent: 'sw-content',
        position: 45,
        privilege: 'ino_blog.viewer',
    }],
});
