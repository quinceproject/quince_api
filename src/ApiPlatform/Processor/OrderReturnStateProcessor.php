<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Api\ApiPlatform\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\UpdateOrderReturnStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use Quince\Api\ApiPlatform\OrderReturnMapper;
use Quince\Api\ApiPlatform\Resources\OrderReturn;
use Quince\Api\Repository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Zmiana statusu zwrotu — wyłącznie przez `UpdateOrderReturnStateCommand`
 * rdzenia, ten sam kod co przycisk w natywnym panelu „Merchandise Returns".
 *
 * Nigdy bezpośredni zapis do tabeli: komenda woła `ObjectModel::update()`,
 * które odpala haki `actionObjectOrderReturnUpdateBefore/After`, i wysyła
 * mail do klienta o zmianie statusu — TYLKO gdy status faktycznie się
 * zmienia (powtórzenie tego samego statusu jest no-opem po stronie rdzenia,
 * bez drugiego maila).
 *
 * **Zapisywalne jest wyłącznie `stateId`.** Zasób nie ma grup serializacji,
 * więc ciało żądania mogłoby teoretycznie nieść też `id`/`shopId` i nadpisać
 * nimi odczytany wcześniej zasób — stąd `id` bierzemy wyłącznie z adresu,
 * a `shopId` wyłącznie z zapytania, tak jak w każdym innym endpoincie tego
 * modułu. Cokolwiek przyjdzie w ciele poza `stateId` jest ignorowane.
 *
 * @implements ProcessorInterface<OrderReturn, OrderReturn>
 */
final class OrderReturnStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Repository $repository,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderReturn
    {
        if (!$data instanceof OrderReturn) {
            throw new \RuntimeException('Unexpected payload for the order return status change.');
        }

        $id = (int) ($uriVariables['id'] ?? 0);
        $idShop = $this->idShop();

        if ($id <= 0 || $idShop === null) {
            throw new OrderReturnNotFoundException('That order return does not exist.');
        }

        $this->commandBus->handle(new UpdateOrderReturnStateCommand($id, $data->stateId));

        $wiersz = $this->repository->get($id, $idShop);

        if ($wiersz === null) {
            throw new OrderReturnNotFoundException('That order return does not exist.');
        }

        return (new OrderReturnMapper($this->repository))->map($wiersz);
    }

    private function idShop(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        $wartosc = $request !== null ? $request->query->get('shopId') : null;

        return $wartosc !== null && $wartosc !== '' ? (int) $wartosc : null;
    }
}
