<?php declare(strict_types=1);

namespace Inocean\Blog\Content;

use Shopware\Core\Framework\DataAbstractionLayer\Field\{FkField, ManyToOneAssociationField};
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{ApiAware, PrimaryKey, Required};
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\Tag\TagDefinition;

final class PostTagDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'ino_blog_post_tag';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('post_id', 'postId', PostDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('tag_id', 'tagId', TagDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            new ManyToOneAssociationField('post', 'post_id', PostDefinition::class, 'id', false),
            new ManyToOneAssociationField('tag', 'tag_id', TagDefinition::class, 'id', false),
        ]);
    }
}
