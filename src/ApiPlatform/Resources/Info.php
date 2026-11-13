<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Api\ApiPlatform\Resources;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Quince\Api\ApiPlatform\Provider\InfoProvider;

/**
 * Co ten moduł oddaje: wersja i lista zasobów ze scope'ami.
 *
 * Instalacje sklepu mogą mieć różne wersje tego modułu (albo nie mieć go
 * wcale), więc system zewnętrzny pyta tutaj, zanim założy, że dany zasób
 * istnieje.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/quince-api/info',
            provider: InfoProvider::class,
            extraProperties: ['scopes' => ['quince_api_order_returns_read']],
            openapiContext: ['summary' => 'Module version and the resources it exposes'],
        ),
    ],
)]
class Info
{
    public string $module = 'quince_api';

    public string $version = '';

    /** @var array<int,array{resource:string,scopes:array<int,string>}> */
    public array $resources = [];
}
