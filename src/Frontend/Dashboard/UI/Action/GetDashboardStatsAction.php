<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Frontend\Dashboard\Application\GetDashboardStats\GetDashboardStatsHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class GetDashboardStatsAction
{
    public function __construct(
        private readonly GetDashboardStatsHandler $getDashboardStatsHandler
    ) {}

    #[Route(path: '/api/dashboard/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $stats = $this->getDashboardStatsHandler->provide();

            return new JsonResponse(['ok' => true, ...$stats]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
