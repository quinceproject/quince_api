<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Api\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Module;
use Quince\Api\ApiPlatform\Resources\Info;

/**
 * @implements ProviderInterface<Info>
 */
final class InfoProvider implements ProviderInterface
{
    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Info
    {
        $modul = Module::getInstanceByName('quince_api');

        $info = new Info();
        $info->version = $modul instanceof Module ? (string) $modul->version : '0.1.0';
        $info->resources = [
            [
                'resource' => '/quince-api/order-returns',
                'scopes' => ['quince_api_order_returns_read', 'quince_api_order_returns_write'],
            ],
            [
                'resource' => '/quince-api/order-return-states',
                'scopes' => ['quince_api_order_returns_read'],
            ],
        ];

        return $info;
    }
}
