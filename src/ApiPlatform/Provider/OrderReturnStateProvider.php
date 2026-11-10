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
use Configuration;
use Quince\Api\ApiPlatform\Resources\OrderReturnState;
use Quince\Api\Repository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lista statusów zwrotu — nie są przypisane do sklepu, tylko `shopId`
 * rozwiązuje język nazwy (ten sam wzorzec co reszta tego modułu).
 *
 * @implements ProviderInterface<OrderReturnState>
 */
final class OrderReturnStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly Repository $repository,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     *
     * @return array<int,OrderReturnState>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $wartosc = $request !== null ? $request->query->get('shopId') : null;
        $idShop = $wartosc !== null && $wartosc !== '' ? (int) $wartosc : null;

        if ($idShop === null) {
            return [];
        }

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT', null, null, $idShop);

        return array_map(static function (array $wiersz): OrderReturnState {
            $stan = new OrderReturnState();
            $stan->id = (int) $wiersz['id_order_return_state'];
            $stan->name = (string) ($wiersz['name'] ?? '');
            $stan->color = (string) $wiersz['color'];
            $stan->isCancellingReturn = (bool) $wiersz['is_cancelling_return'];

            return $stan;
        }, $this->repository->states($idLang));
    }
}
