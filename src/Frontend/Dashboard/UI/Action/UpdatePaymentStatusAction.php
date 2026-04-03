<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Frontend\Dashboard\Application\UpdatePaymentStatus\UpdatePaymentStatusHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class UpdatePaymentStatusAction
{
    public function __construct(
        private readonly UpdatePaymentStatusHandler $updatePaymentStatusHandler
    ) {}

    #[Route(path: '/api/invoices/{invoiceRef}/payment-status', name: 'api_invoices_update_payment_status', methods: ['PATCH'])]
    public function __invoke(Request $request, string $invoiceRef): JsonResponse
    {
        try {
            $body = json_decode((string) $request->getContent(), true);
            $status = isset($body['status']) && is_string($body['status']) ? $body['status'] : '';

            if ('' === $status) {
                return new JsonResponse(['ok' => false, 'message' => 'Missing status field.'], Response::HTTP_BAD_REQUEST);
            }

            $found = $this->updatePaymentStatusHandler->handle($invoiceRef, $status);

            if (!$found) {
                return new JsonResponse(['ok' => false, 'message' => 'Invoice not found or invalid status.'], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse(['ok' => true]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
