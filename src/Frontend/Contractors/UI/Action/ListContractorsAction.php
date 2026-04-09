<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\UI\Action;

use Ksef\Frontend\Contractors\Application\ListContractors\ListContractorsHandler;
use Ksef\Frontend\Contractors\Domain\Contractor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ListContractorsAction
{
    public function __construct(
        private readonly ListContractorsHandler $handler
    ) {}

    #[Route(path: '/api/contractors', name: 'api_contractors_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 25)));
        $search = $request->query->get('search');
        $search = is_string($search) && $search !== '' ? trim($search) : null;

        $result = $this->handler->handle($page, $limit, $search);

        return new JsonResponse([
            'ok' => true,
            'items' => array_map(static fn (Contractor $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'nip' => $c->nip,
                'address' => $c->address,
                'email' => $c->email,
                'createdAt' => $c->createdAt,
            ], $result['items']),
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / $limit),
            'limit' => $limit,
        ]);
    }
}
