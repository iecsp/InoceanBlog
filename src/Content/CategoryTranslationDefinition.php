<?php declare(strict_types=1);

namespace Inocean\Blog\Content;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{ApiAware, Required};
use Shopware\Core\Framework\DataAbstractionLayer\Field\{LongTextField, StringField};
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class CategoryTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'ino_blog_category_translation';
    }

    protected function getParentDefinitionClass(): string
    {
        return CategoryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware()),
        ]);
    }
}
