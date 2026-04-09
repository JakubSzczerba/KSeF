<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\UI\Action;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Contractors\Application\UpdateContractor\UpdateContractorCommand;
use Ksef\Frontend\Contractors\Application\UpdateContractor\UpdateContractorHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class UpdateContractorAction
{
    public function __construct(
        private readonly UpdateContractorHandler $handler
    ) {}

    #[Route(path: '/api/contractors/{id}', name: 'api_contractors_update', methods: ['PATCH'])]
    public function __invoke(Request $request, int $id): JsonResponse
    {
        try {
            $data = json_decode((string) $request->getContent(), true);
            if (!is_array($data)) {
                return new JsonResponse(['ok' => false, 'message' => 'Nieprawidłowy JSON.'], Response::HTTP_BAD_REQUEST);
            }

            $name = trim((string) ($data['name'] ?? ''));
            $address = isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null;
            $email = isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null;

            $updated = $this->handler->handle(new UpdateContractorCommand($id, $name, $address, $email));
            if (!$updated) {
                return new JsonResponse(['ok' => false, 'message' => 'Nie znaleziono kontrahenta.'], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse(['ok' => true]);
        } catch (DomainValidationException $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
