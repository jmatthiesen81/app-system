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
            return;
        }

        $connection = $this->container->get(Connection::class);
        $connection->executeQuery('DROP TABLE IF EXISTS `app_action_button_translation`, `app_action_button`, `app_translation`, `app`');
    }
}