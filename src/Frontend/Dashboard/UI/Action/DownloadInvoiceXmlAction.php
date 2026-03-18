<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Backend\Authentication\Application\AccessTokenStore;
use Ksef\Backend\Authentication\Application\Contract\AuthenticateHandlerInterface;
use Ksef\Backend\Shared\Application\Contract\KsefApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DownloadInvoiceXmlAction
{
    public function __construct(
        private readonly KsefApi $ksefApi,
        private readonly AccessTokenStore $accessTokenStore,
        private readonly AuthenticateHandlerInterface $authenticateHandler
    ) {}

    #[Route(path: '/invoices/download/{ksefNumber}', name: 'frontend_invoice_download', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $ksefNumber = (string) $request->attributes->get('ksefNumber', '');

        try {
            $accessToken = $this->accessTokenStore->get() ?? $this->authenticateHandler->execute()->accessToken;
            $invoice = $this->ksefApi->downloadInvoiceByKsefNumber($accessToken->value, $ksefNumber);
            $fileName = $this->sanitizeFileName($ksefNumber) . '.xml';

            $response = new Response($invoice['content']);
            $response->headers->set('Content-Type', 'application/xml; charset=utf-8');
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileName));
            if (is_string($invoice['hash']) && $invoice['hash'] !== '') {
                $response->headers->set('x-ms-meta-hash', $invoice['hash']);
            }

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
