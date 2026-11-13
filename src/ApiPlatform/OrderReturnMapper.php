<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Api\ApiPlatform;

use Configuration;
use Quince\Api\ApiPlatform\Resources\OrderReturn;
use Quince\Api\Repository;

/**
 * Wiersz z {@see Repository} na zasób API. Jedno miejsce dla dostawcy
 * i procesora, żeby odczyt po zapisie zwracał dokładnie ten sam kształt
 * co lista i szczegół.
 */
final class OrderReturnMapper
{
    public function __construct(private readonly Repository $repository)
    {
    }

    /** @param array<string,mixed> $wiersz */
    public function map(array $wiersz): OrderReturn
    {
        $idShop = (int) $wiersz['id_shop'];
        $idLang = (int) $wiersz['id_lang'];

        $zasob = new OrderReturn();
        $zasob->id = (int) $wiersz['id_order_return'];
        $zasob->reference = $this->reference((int) $wiersz['id_order_return'], $idLang, $idShop);
        $zasob->orderId = (int) $wiersz['id_order'];
        $zasob->orderReference = (string) $wiersz['order_reference'];
        $zasob->customerId = (int) $wiersz['id_customer'];
        $zasob->customerEmail = (string) $wiersz['customer_email'];
        $zasob->shopId = $idShop;
        $zasob->stateId = (int) $wiersz['state'];
        $zasob->question = (string) $wiersz['question'];
        $zasob->dateAdd = self::iso((string) $wiersz['date_add']);
        $zasob->dateUpd = self::iso((string) $wiersz['date_upd']);

        $stan = $this->stan($zasob->stateId, $idLang);
        $zasob->stateName = $stan['name'];
        $zasob->isCancellingReturn = $stan['isCancellingReturn'];

        $zasob->items = array_map(
            static fn (array $pozycja): array => [
                'idOrderDetail' => (int) $pozycja['id_order_detail'],
                'productId' => (int) $pozycja['product_id'],
                'productAttributeId' => (int) $pozycja['product_attribute_id'],
                'reference' => (string) $pozycja['product_reference'],
                'ean' => (string) $pozycja['product_ean13'],
                'name' => (string) $pozycja['product_name'],
                'quantityOrdered' => (int) $pozycja['quantity_ordered'],
                'quantityReturned' => (int) $pozycja['quantity_returned'],
                'idCustomization' => (int) $pozycja['id_customization'],
                'cancelled' => (bool) $pozycja['cancelled'],
                'unitPriceNet' => (float) $pozycja['unit_price_tax_excl'],
                'unitPriceGross' => (float) $pozycja['unit_price_tax_incl'],
            ],
            $this->repository->items($zasob->id)
        );

        return $zasob;
    }

    /** `PS_RETURN_PREFIX` + numer na 6 cyfr, ten sam kształt co natywny PDF zwrotu. */
    private function reference(int $id, int $idLang, int $idShop): string
    {
        $prefiks = trim((string) Configuration::get('PS_RETURN_PREFIX', $idLang, null, $idShop));

        return $prefiks . sprintf('%06d', $id);
    }

    /** @return array{name:string,isCancellingReturn:bool} */
    private function stan(int $idState, int $idLang): array
    {
        foreach ($this->repository->states($idLang) as $wiersz) {
            if ((int) $wiersz['id_order_return_state'] === $idState) {
                return [
                    'name' => (string) ($wiersz['name'] ?? ''),
                    'isCancellingReturn' => (bool) $wiersz['is_cancelling_return'],
                ];
            }
        }

        return ['name' => '', 'isCancellingReturn' => false];
    }

    private static function iso(string $mysql): string
    {
        return (new \DateTime($mysql, new \DateTimeZone(date_default_timezone_get())))->format('c');
    }
}
