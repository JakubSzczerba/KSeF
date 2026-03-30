<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Frontend\Dashboard\Application\ListInvoices\ListInvoicesHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class ListInvoicesAction
{
    public function __construct(
        private readonly ListInvoicesHandler $listInvoicesHandler
    ) {}

    #[Route(path: '/api/invoices', name: 'api_invoices_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $page = max(1, $request->query->getInt('page', 1));
            $limit = max(1, $request->query->getInt('limit', 25));
            $paymentStatus = $request->query->get('status');
            $paymentStatus = is_string($paymentStatus) && $paymentStatus !== '' ? $paymentStatus : null;

            $result = $this->listInvoicesHandler->provide($page, $limit, $paymentStatus);

            return new JsonResponse(['ok' => true, ...$result]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
