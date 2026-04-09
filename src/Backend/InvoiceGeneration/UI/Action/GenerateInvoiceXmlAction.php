<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\InvoiceGeneration\UI\Action;

use Ksef\Backend\InvoiceGeneration\Application\GenerateInvoiceXml\GenerateInvoiceXmlCommand;
use Ksef\Backend\InvoiceGeneration\Application\GenerateInvoiceXml\GenerateInvoiceXmlHandler;
use Ksef\Backend\InvoiceGeneration\Domain\InvoiceLineItem;
use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class GenerateInvoiceXmlAction
{
    public function __construct(
        private readonly GenerateInvoiceXmlHandler $handler
    ) {}

    #[Route(path: '/api/invoices/generate', name: 'api_invoices_generate', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode((string) $request->getContent(), true);
            if (!is_array($data)) {
                return new JsonResponse(['ok' => false, 'message' => 'Nieprawidłowy JSON.'], Response::HTTP_BAD_REQUEST);
            }

            $lines = $this->parseLines($data['lines'] ?? []);
            if ([] === $lines) {
                return new JsonResponse(['ok' => false, 'message' => 'Faktura musi zawierać co najmniej jedną pozycję.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $xml = $this->handler->handle(new GenerateInvoiceXmlCommand(
                invoiceNumber: trim((string) ($data['invoiceNumber'] ?? '')),
                invoiceDate: trim((string) ($data['invoiceDate'] ?? '')),
                saleDate: trim((string) ($data['saleDate'] ?? '')),
                paymentDueDate: trim((string) ($data['paymentDueDate'] ?? '')),
                sellerNip: trim((string) ($data['sellerNip'] ?? '')),
                sellerName: trim((string) ($data['sellerName'] ?? '')),
                sellerAddress: trim((string) ($data['sellerAddress'] ?? '')),
                bankAccount: trim((string) ($data['bankAccount'] ?? '')),
                buyerNip: trim((string) ($data['buyerNip'] ?? '')),
                buyerName: trim((string) ($data['buyerName'] ?? '')),
                buyerAddress: trim((string) ($data['buyerAddress'] ?? '')),
                lines: $lines,
                notes: isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
            ));

            return new JsonResponse(['ok' => true, 'xml' => $xml]);
        } catch (DomainValidationException $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param mixed $raw
     * @return InvoiceLineItem[]
     */
    private function parseLines(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $lines = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $lines[] = new InvoiceLineItem(
                description: $description,
                quantity: (float) ($item['quantity'] ?? 1),
                unitPriceNet: (float) ($item['unitPriceNet'] ?? 0),
                vatRate: (int) ($item['vatRate'] ?? 23),
            );
        }

        return $lines;
    }
}
