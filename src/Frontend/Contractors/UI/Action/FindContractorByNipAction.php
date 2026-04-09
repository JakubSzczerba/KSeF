<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\UI\Action;

use Ksef\Frontend\Contractors\Application\FindByNip\FindByNipHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FindContractorByNipAction
{
    public function __construct(
        private readonly FindByNipHandler $handler
    ) {}

    #[Route(path: '/api/contractors/search', name: 'api_contractors_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $nip = trim((string) $request->query->get('nip', ''));
        if ($nip === '') {
            return new JsonResponse(['ok' => false, 'message' => 'Parametr nip jest wymagany.'], Response::HTTP_BAD_REQUEST);
        }

        $contractor = $this->handler->handle($nip);
        if (null === $contractor) {
            return new JsonResponse(['ok' => false, 'message' => 'Nie znaleziono kontrahenta.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'ok' => true,
            'contractor' => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'nip' => $contractor->nip,
                'address' => $contractor->address,
                'email' => $contractor->email,
                'createdAt' => $contractor->createdAt,
            ],
        ]);
    }
}
