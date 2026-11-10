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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturnState\Exception\OrderReturnStateNotFoundException;
use Quince\Api\ApiPlatform\Processor\OrderReturnStateProcessor;
use Quince\Api\ApiPlatform\Provider\OrderReturnProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Natywny zwrot towaru (RMA), z pozycjami.
 *
 * Odczyt sięga bezpośrednio do tabel rdzenia (żadna gotowa kwerenda CQRS
 * nie oddaje listy z pozycjami i cenami — zob. {@see \Quince\Api\Repository}).
 * Zapis statusu idzie wyłącznie przez natywną komendę rdzenia
 * `UpdateOrderReturnStateCommand`, nie bezpośredni zapis — dzięki temu klient
 * dostaje ten sam mail co z panelu i odpalają się te same haki.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/quince-api/order-returns',
            provider: OrderReturnProvider::class,
            extraProperties: ['scopes' => ['quince_api_order_returns_read']],
            openapiContext: [
                'summary' => 'Merchandise returns, oldest update first',
                'description' => 'Requires `shopId`. Filter with `updatedSince` (matches `dateUpd`, '
                    . 'inclusive), `orderId` and `state`. Page with `limit` + `page`.',
            ],
        ),
        new Get(
            uriTemplate: '/quince-api/order-returns/{id}',
            provider: OrderReturnProvider::class,
            extraProperties: ['scopes' => ['quince_api_order_returns_read']],
            openapiContext: ['summary' => 'One merchandise return by id, with its line items', 'description' => 'Requires `shopId`.'],
        ),
        new Patch(
            uriTemplate: '/quince-api/order-returns/{id}',
            provider: OrderReturnProvider::class,
            processor: OrderReturnStateProcessor::class,
            extraProperties: ['scopes' => ['quince_api_order_returns_write']],
            exceptionToStatus: [
                OrderReturnNotFoundException::class => Response::HTTP_NOT_FOUND,
                OrderReturnStateNotFoundException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
                OrderReturnConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            openapiContext: [
                'summary' => 'Change the return\'s status',
                'description' => 'Body: `{"stateId": N}`. Goes through the core command, same as the '
                    . 'native panel — the customer gets the standard status-change e-mail. Setting the '
                    . 'same status again is a no-op: no second e-mail.',
            ],
        ),
    ],
)]
class OrderReturn
{
    #[ApiProperty(identifier: true)]
    public int $id = 0;

    /** `PS_RETURN_PREFIX` + numer na 6 cyfr, np. `RMA000012`. */
    public string $reference = '';

    public int $orderId = 0;

    public string $orderReference = '';

    public int $customerId = 0;

    public string $customerEmail = '';

    public int $shopId = 0;

    public int $stateId = 0;

    /** Nazwa statusu w języku sklepu. */
    public string $stateName = '';

    public bool $isCancellingReturn = false;

    /** Treść od klienta. */
    public string $question = '';

    public string $dateAdd = '';

    public string $dateUpd = '';

    /**
     * Pozycje zwrotu.
     *
     * @var array<int,array{
     *     idOrderDetail:int, productId:int, productAttributeId:int, reference:string, ean:string,
     *     name:string, quantityOrdered:int, quantityReturned:int, idCustomization:int, cancelled:bool,
     *     unitPriceNet:float, unitPriceGross:float
     * }>
     */
    public array $items = [];
}
