<?php declare(strict_types=1);

namespace Swag\SaasConnect\Core\Content\App\Lifecycle;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Swag\SaasConnect\Core\Content\App\Manifest\Manifest;

/**
 * @Decoratable
 */
interface AppLoaderInterface
{
    /**
     * @return array<Manifest>
     */
    public function load(): array;

    public function getIcon(Manifest $app): ?string;
}
