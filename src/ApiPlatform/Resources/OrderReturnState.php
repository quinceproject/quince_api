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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Quince\Api\ApiPlatform\Provider\OrderReturnStateProvider;

/**
 * Jeden status, na jaki może przejść zwrot towaru. Ten sam zestaw, co
 * natywny panel pokazuje przy zwrocie — rdzeń nie udostępnia tego przez
 * Webservice ani przez standardowe Admin API.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/quince-api/order-return-states',
            provider: OrderReturnStateProvider::class,
            extraProperties: ['scopes' => ['quince_api_order_returns_read']],
            openapiContext: [
                'summary' => 'Merchandise return statuses',
                'description' => 'Requires `shopId`, used only to resolve the shop\'s default language '
                    . 'for `name`. Statuses themselves are not per-shop.',
            ],
        ),
    ],
)]
class OrderReturnState
{
    #[ApiProperty(identifier: true)]
    public int $id = 0;

    public string $name = '';

    /** Kolor etykiety w panelu, np. `#32CD32`. */
    public string $color = '';

    public bool $isCancellingReturn = false;
}
