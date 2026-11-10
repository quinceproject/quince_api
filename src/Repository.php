<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Api;

use Db;
use OrderReturnState;

/**
 * Dostęp do natywnych zwrotów towaru (RMA) — wyłącznie odczyt.
 *
 * Zapytania własne, nie przez CQRS rdzenia: żadna gotowa kwerenda nie
 * oddaje listy z `updatedSince`/stronicowaniem ani pozycji z cenami —
 * `GetOrderReturnForEditing` obsługuje tylko pojedynczy formularz edycji
 * w panelu, bez pozycji i bez sklepu. Zapis (zmiana statusu) idzie inną
 * drogą — przez `UpdateOrderReturnStateCommand`, zob. `OrderReturnStateProcessor`.
 */
final class Repository
{
    public const PER_PAGE = 50;

    /** @return array<string,mixed>|null */
    public function get(int $id, int $idShop): ?array
    {
        $wiersz = Db::getInstance()->getRow(
            $this->bazoweZapytanie() . ' WHERE ord.`id_order_return` = ' . $id . ' AND o.`id_shop` = ' . $idShop
        );

        return is_array($wiersz) && $wiersz !== [] ? $wiersz : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(
        int $idShop,
        ?string $updatedSince,
        ?int $orderId,
        ?int $state,
        int $limit,
        int $offset
    ): array {
        $warunek = $this->warunek($idShop, $updatedSince, $orderId, $state);

        $wiersze = Db::getInstance()->executeS(
            $this->bazoweZapytanie() . ' WHERE ' . $warunek . '
             ORDER BY ord.`date_upd` ASC, ord.`id_order_return` ASC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        ) ?: [];

        return $wiersze;
    }

    public function count(int $idShop, ?string $updatedSince, ?int $orderId, ?int $state): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM `' . _DB_PREFIX_ . 'order_return` ord
             INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = ord.`id_order`
             WHERE ' . $this->warunek($idShop, $updatedSince, $orderId, $state)
        );
    }

    /**
     * Pozycje zwrotu, z ilością zamówioną i ceną pobraną z linii zamówienia —
     * `order_return_detail` sama w sobie nie niesie ani referencji produktu,
     * ani ceny.
     *
     * @return array<int,array<string,mixed>>
     */
    public function items(int $idOrderReturn): array
    {
        return Db::getInstance()->executeS(
            'SELECT ordd.`id_order_detail`, ordd.`id_customization`, ordd.`cancelled`,
                    ordd.`product_quantity` AS `quantity_returned`,
                    od.`product_id`, od.`product_attribute_id`, od.`product_reference`, od.`product_ean13`,
                    od.`product_name`, od.`product_quantity` AS `quantity_ordered`,
                    od.`unit_price_tax_excl`, od.`unit_price_tax_incl`
             FROM `' . _DB_PREFIX_ . 'order_return_detail` ordd
             INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.`id_order_detail` = ordd.`id_order_detail`
             WHERE ordd.`id_order_return` = ' . $idOrderReturn . '
             ORDER BY ordd.`id_order_detail` ASC'
        ) ?: [];
    }

    /**
     * Statusy zwrotu w języku sklepu — ta sama kwerenda, co natywny panel.
     *
     * @return array<int,array<string,mixed>>
     */
    public function states(int $idLang): array
    {
        return OrderReturnState::getOrderReturnStates($idLang) ?: [];
    }

    private function bazoweZapytanie(): string
    {
        return 'SELECT ord.`id_order_return`, ord.`id_order`, ord.`id_customer`, ord.`state`,
                       ord.`question`, ord.`date_add`, ord.`date_upd`,
                       o.`reference` AS `order_reference`, o.`id_shop`, o.`id_lang`,
                       c.`email` AS `customer_email`
                FROM `' . _DB_PREFIX_ . 'order_return` ord
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = ord.`id_order`
                LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = ord.`id_customer`';
    }

    private function warunek(int $idShop, ?string $updatedSince, ?int $orderId, ?int $state): string
    {
        $czesci = ['o.`id_shop` = ' . $idShop];

        if ($updatedSince !== null && $updatedSince !== '') {
            $czesci[] = 'ord.`date_upd` >= "' . pSQL($updatedSince) . '"';
        }

        if ($orderId !== null) {
            $czesci[] = 'ord.`id_order` = ' . $orderId;
        }

        if ($state !== null) {
            $czesci[] = 'ord.`state` = ' . $state;
        }

        return implode(' AND ', $czesci);
    }
}
