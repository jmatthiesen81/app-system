<?php declare(strict_types=1);

namespace Swag\SaasConnect\Core\Framework\Webhook;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Swag\SaasConnect\Core\Content\App\AppDefinition;

class WebhookDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'saas_webhook';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return WebhookEntity::class;
    }

    public function getCollectionClass(): string
    {
        return WebhookCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('event_name', 'eventName', 500))->addFlags(new Required()),
            (new StringField('url', 'url', 500))->addFlags(new Required()),

            new FkField('app_id', 'appId', AppDefinition::class),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
