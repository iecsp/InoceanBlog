<?php declare(strict_types=1);

namespace Inocean\Blog\Content;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{ApiAware, CascadeDelete, PrimaryKey, Required, SearchRanking};
use Shopware\Core\Framework\DataAbstractionLayer\Field\{BoolField, DateTimeField, FkField, IdField, ManyToManyAssociationField, ManyToOneAssociationField, StringField, TranslatedField, TranslationsAssociationField};
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Tag\TagDefinition;

final class PostDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ino_blog_post';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getDefaults(): array
    {
        return ['active' => false, 'pinned' => false, 'indexable' => true];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->addFlags(new ApiAware()),
            (new FkField('cover_id', 'coverId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new FkField('video_id', 'videoId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new StringField('author', 'author'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('pinned', 'pinned'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('indexable', 'indexable'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('published_at', 'publishedAt'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('cover', 'cover_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('video', 'video_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new TranslatedField('title'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('excerpt'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('content'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING)),
            (new ManyToManyAssociationField('tags', TagDefinition::class, PostTagDefinition::class, 'post_id', 'tag_id'))->addFlags(new ApiAware(), new CascadeDelete()),
            (new TranslatedField('metaTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('metaDescription'))->addFlags(new ApiAware()),
            (new TranslatedField('slug'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(PostTranslationDefinition::class, 'ino_blog_post_id'))->addFlags(new Required()),
        ]);
    }
}
