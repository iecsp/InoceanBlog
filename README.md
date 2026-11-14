# InoceanBlog

Shopware 6.7.16.0 的无头博客插件。Composer 包为 `inocean/blog`，数据库表为 `ino_blog_*`。后台入口为 **Content → Blog**，界面提供英文、简体中文及繁体中文（`zh-TW`、`zh-HK`）。

想了解 Shopware 6 在餐饮场景中的应用？欢迎访问 [IECSP 官网 · iecsp.com](https://iecsp.com/)，了解基于 Shopware 6 的餐饮运营平台 inocean ROP。

## 安装与编辑

在 Shopware 项目根目录运行：

```bash
bin/console plugin:refresh
bin/console plugin:install --activate InoceanBlog
bin/console assets:install
bin/console cache:clear
```

插件自带基于 Shopware 6.7.16.0 构建的后台资源。修改 Vue 源码后使用项目的 `bin/build-administration.sh` 重新构建。后台用户可通过 **Settings → Users & permissions** 分配 Blog 权限。上传或管理媒体需要相应的 Shopware 媒体权限。

先创建分类和系统语言文章，再用详情页语言切换器补充译文。标题、摘要、正文、分类名称和描述、URL 别名及 SEO 元数据按语言保存，未提供的字段沿用 Shopware 语言回退。作者默认为当前后台用户姓名，可改为对外署名。每篇文章绑定一个 Headless 销售渠道，可选择一个分类。正文编辑器可从媒体库插图。头图和视频为独立媒体字段，前端可用 `cover.url` 和 `video.url` 渲染。后台发布时间按编辑者的 Shopware 时区显示，API 返回带时区的时间。启用发布且到达发布时间后，文章才会出现在公开读取接口。

## Nuxt 接入

所有接口使用 `GET`，请求携带 Shopware 的 `sw-access-key`，并按需携带 `sw-language-id`。

| 路径 | 响应 |
| --- | --- |
| `/store-api/ino-blog/posts` | 分页摘要，置顶优先，其次按发布时间排序。支持 `page`、`limit`、`q` 和 `categoryId` |
| `/store-api/ino-blog/posts/{postId}` | 文章详情，包括清理后的正文 HTML、纯文本、媒体、作者、发布时间、SEO 元数据 |
| `/store-api/ino-blog/categories` | 该渠道下含已发布文章的分类。支持 `page`、`limit` |
| `/store-api/ino-blog/index` | 提供给站内搜索或 AI 索引任务的分页全文，按发布时间排序，支持与文章列表相同的查询参数 |
| `/store-api/ino-blog/feed.xml` | RSS 2.0，按发布时间排序，包含带 canonical URL 的可索引文章，支持与索引相同的查询参数 |

分页对象返回 `elements`、`total`、`page`、`limit`，默认每页 20 条，最多 100 条。未发布、尚未到发布时间及其他销售渠道的文章不可读取。关闭索引的文章仍可正常浏览，详情返回 `X-Robots-Tag: noindex`，并从全文索引、RSS 和 sitemap 中排除。Nuxt 渲染时也应根据 `indexable` 输出相应的 robots 指令。

### SEO 与爬虫

1. 为每种语言在 Shopware **Headless 销售渠道**配置 Nuxt 域名，并标记 **External storefront**。此标记是 Shopware 生成该渠道 SEO URL 和 sitemap 的前提。配置变更后运行 `bin/console dal:refresh:index --only=ino_blog_post.seo-url` 及 `bin/console sitemap:generate --salesChannelId=<ID>`。
2. 用 Shopware 原生 `/store-api/seo-url` 按 `seoPathInfo` 查询访问路径。匹配 `routeName === 'store-api.ino-blog.post.detail'` 时，以 `foreignKey` 请求文章详情。详情返回 `canonicalUrl` 和 `seoPath`。旧地址与当前 canonical URL 不同时，Nuxt 可发出 301。默认 URL 使用译文别名或标题，并附加文章 ID，以避免同名文章冲突。若渠道尚无对应语言的外部域名，应准备处理空 canonical URL。
3. Nuxt 应服务端渲染可索引文章并输出 canonical、SEO 标题和描述。可用详情数据生成 Schema.org `BlogPosting`。在 Nuxt 域名下公开 Shopware 生成的 sitemap，代理 RSS 接口并在 HTML 中声明 feed 链接。由 Nuxt 服务端注入渠道与语言参数，便于爬虫和订阅工具访问。要构建 AI 检索索引，可遍历 `/index`，定期移除列表中已不存在的条目。

Shopware 参考：[无头 SEO URL 解析](https://developer.shopware.com/frontends/frontends-recipes/context/url-resolving.html)、[原生无头 sitemap](https://developer.shopware.com/release-notes/6.7/6.7.15.0.html)。

## 兼容性

后台使用 Shopware 自带的 Meteor 5.8.0 和 Vue SFC，使用 6.7.16.0 的 Vite 扩展构建。服务与路由由 PHP 配置。Composer 包允许 6.7.16+ 和 6.8.x。已在 6.7.16.0 验证，6.8 是预适配范围，正式版本需要重新运行安装、构建与集成测试。

## 1.1 文章预览、标签与查询

详情页的 **Preview / 预览** 使用当前表单的内容，包括尚未保存的正文、摘要、署名和媒体。预览正文与 Store API 使用同一套 HTML 清理规则。Nuxt 可以独立定义页面样式与排版。

在文章表单中可选用 Shopware 的共享标签。文章列表、详情和全文索引返回 `tags: [{ id, name }]`。文章列表、全文索引和 RSS 都支持以下参数：

| 参数 | 用途 |
| --- | --- |
| `page`, `limit` | 页码与每页条数 |
| `q` | 标题、摘要和正文关键词 |
| `categoryId`, `tagId` | 按分类或标签 UUID 筛选，可组合 |
| `publishedFrom`, `publishedTo` | 含首尾的 UTC 日期范围，格式为 `YYYY-MM-DD` |
| `sort` | `pinned` 置顶优先、`newest` 最新优先、`oldest` 最早优先。文章列表默认 `pinned`，索引和 RSS 默认 `newest` |

数据库包含销售渠道、发布日期、置顶、分类及标签关联索引。全文索引用分页读取，关键词查询使用 Shopware DAL。

## 原生 Admin MCP

连接 `/api/_mcp?toolsets=inocean-blog`，并在 Shopware 集成的 MCP allowlist 中授权 `inocean-blog-post-write` 和 `inocean-blog-category-write`。为集成角色分配 Blog 所需的创建或更新权限。两工具默认 `dryRun: true`，通过后可传 `false` 保存。每次提交一个 JSON 对象，省略 `id` 创建，提供现有 `id` 更新。使用 `sw-language-id` 编辑特定语言。

可用 Shopware 原生 `shopware-entity-search`、`shopware-entity-read` 检索文章和分类，使用实体工具管理 `ino_blog_post_tag` 标签关系。此类工具使用 Shopware 原生集成权限和 allowlist。 [Shopware MCP 文档](https://developer.shopware.com/docs/products/tools/mcp-server/intro.html)

## 卸载

卸载并取消 **Keep user data** 会清理全部五张 `ino_blog_*` 表、文章、翻译、分类、标签关联、博客 SEO URL 和模板。标签关联表先于文章表删除。Shopware 共享标签和媒体库文件继续由 Shopware 管理。使用 `--keep-user-data` 可保留博客数据用于重装。
