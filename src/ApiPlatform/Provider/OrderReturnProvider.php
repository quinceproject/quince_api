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
use Quince\Api\ApiPlatform\OrderReturnMapper;
use Quince\Api\ApiPlatform\Resources\OrderReturn;
use Quince\Api\Repository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Zwroty towaru czytane przez Admin API — ta sama logika co natywny panel
 * (`PS_RETURN_PREFIX`, nazwa statusu w języku sklepu), żeby system zewnętrzny
 * nie liczył referencji po swojemu z ryzykiem rozjazdu.
 *
 * @implements ProviderInterface<OrderReturn>
 */
final class OrderReturnProvider implements ProviderInterface
{
    private readonly OrderReturnMapper $mapper;

    public function __construct(
        private readonly Repository $repository,
        private readonly RequestStack $requestStack
    ) {
        $this->mapper = new OrderReturnMapper($repository);
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     *
     * @return array<int,OrderReturn>|OrderReturn|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|OrderReturn|null
    {
        $idShop = $this->idShop();

        if (isset($uriVariables['id'])) {
            if ($idShop === null) {
                return null;
            }

            $wiersz = $this->repository->get((int) $uriVariables['id'], $idShop);

            return $wiersz !== null ? $this->mapper->map($wiersz) : null;
        }

        if ($idShop === null) {
            return [];
        }

        $request = $this->requestStack->getCurrentRequest();
        $query = $request !== null ? $request->query->all() : [];

        $updatedSince = isset($query['updatedSince']) && $query['updatedSince'] !== ''
            ? date('Y-m-d H:i:s', strtotime((string) $query['updatedSince']) ?: 0)
            : null;
        $orderId = isset($query['orderId']) && $query['orderId'] !== '' ? (int) $query['orderId'] : null;
        $state = isset($query['state']) && $query['state'] !== '' ? (int) $query['state'] : null;
        $limit = min(200, max(1, (int) ($query['limit'] ?? Repository::PER_PAGE)));
        $page = max(1, (int) ($query['page'] ?? 1));

        $wiersze = $this->repository->list($idShop, $updatedSince, $orderId, $state, $limit, ($page - 1) * $limit);

        return array_map(fn (array $wiersz): OrderReturn => $this->mapper->map($wiersz), $wiersze);
    }

    private function idShop(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        $wartosc = $request !== null ? $request->query->get('shopId') : null;

        return $wartosc !== null && $wartosc !== '' ? (int) $wartosc : null;
    }
}
