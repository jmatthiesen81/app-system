<?php declare(strict_types=1);

namespace Swag\SaasConnect;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class SaasConnect extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData() === true) {
            $uninstallContext->enableKeepMigrations();

            return;
        }

        $connection = $this->container->get(Connection::class);
        $connection->executeUpdate('
            ALTER TABLE `custom_field_set`
            DROP FOREIGN KEY `fk.custom_field_set.app_id`,
            DROP COLUMN `app_id`;
        ');
        $connection->executeUpdate('
            DROP TABLE IF EXISTS
                `saas_webhook`,
                `saas_template`,
                `saas_app_action_button_translation`,
                `saas_app_action_button`,
                `saas_app_translation`,
                `saas_app`;
        ');
    }
}
