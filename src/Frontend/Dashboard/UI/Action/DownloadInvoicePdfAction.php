<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Backend\Authentication\Application\TokenRefreshingExecutor;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use Ksef\Frontend\Dashboard\Application\RenderInvoicePdf\RenderInvoicePdfHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DownloadInvoicePdfAction
{
    public function __construct(
        private readonly KsefApi $ksefApi,
        private readonly TokenRefreshingExecutor $tokenRefreshingExecutor,
        private readonly RenderInvoicePdfHandler $renderInvoicePdfHandler
    ) {}

    #[Route(path: '/invoices/download/{ksefNumber}/pdf', name: 'frontend_invoice_download_pdf', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $ksefNumber = (string) $request->attributes->get('ksefNumber', '');

        try {
            $accessToken = $this->tokenRefreshingExecutor->getValidToken();
            $invoice = $this->ksefApi->downloadInvoiceByKsefNumber($accessToken->value, $ksefNumber);
            $fileName = $this->sanitizeFileName($ksefNumber) . '.pdf';
            $pdf = $this->renderInvoicePdfHandler->render($invoice['content'], $ksefNumber);

            $response = new Response($pdf);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileName));

            return $response;
        } catch (Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function sanitizeFileName(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_.-]/', '_', $value);

        return is_string($sanitized) && $sanitized !== '' ? $sanitized : 'invoice';
    }
}
