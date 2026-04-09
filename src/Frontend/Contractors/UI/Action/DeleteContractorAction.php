<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\UI\Action;

use Ksef\Frontend\Contractors\Application\DeleteContractor\DeleteContractorHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteContractorAction
{
    public function __construct(
        private readonly DeleteContractorHandler $handler
    ) {}

    #[Route(path: '/api/contractors/{id}', name: 'api_contractors_delete', methods: ['DELETE'])]
    public function __invoke(int $id): JsonResponse
    {
        $deleted = $this->handler->handle($id);
        if (!$deleted) {
            return new JsonResponse(['ok' => false, 'message' => 'Nie znaleziono kontrahenta.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['ok' => true]);
    }
}
