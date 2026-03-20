<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Frontend\Dashboard\Application\GetInvoiceOverview\GetInvoiceOverviewHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class GetInvoiceRowsAction
{
    public function __construct(
        private readonly GetInvoiceOverviewHandler $getInvoiceOverviewHandler
    ) {}

    #[Route(path: '/invoices/rows', name: 'frontend_invoice_rows', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            return new JsonResponse([
                'ok' => true,
                'rows' => $this->getInvoiceOverviewHandler->provide(),
            ]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
