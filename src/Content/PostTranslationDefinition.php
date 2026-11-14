<?php declare(strict_types=1);

namespace Inocean\Blog\Content;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{AllowHtml, ApiAware, Required};
use Shopware\Core\Framework\DataAbstractionLayer\Field\{LongTextField, StringField};
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class PostTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'ino_blog_post_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function getParentDefinitionClass(): string
    {
        return PostDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('title', 'title'))->addFlags(new ApiAware(), new Required()),
            (new LongTextField('excerpt', 'excerpt'))->addFlags(new ApiAware()),
            (new LongTextField('content', 'content'))->addFlags(new ApiAware(), new AllowHtml()),
            (new StringField('meta_title', 'metaTitle'))->addFlags(new ApiAware()),
            (new StringField('meta_description', 'metaDescription'))->addFlags(new ApiAware()),
            (new StringField('slug', 'slug', 100))->addFlags(new ApiAware()),
        ]);
    }
}
