<?php declare(strict_types=1);

namespace Inocean\Blog\Content;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{ApiAware, PrimaryKey, Required, SearchRanking};
use Shopware\Core\Framework\DataAbstractionLayer\Field\{IdField, IntField, OneToManyAssociationField, TranslatedField, TranslationsAssociationField};
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class CategoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ino_blog_category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getDefaults(): array
    {
        return ['position' => 0];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new IntField('position', 'position'))->addFlags(new ApiAware(), new Required()),
            new OneToManyAssociationField('posts', PostDefinition::class, 'category_id'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(CategoryTranslationDefinition::class, 'ino_blog_category_id'))->addFlags(new Required()),
        ]);
    }
}
