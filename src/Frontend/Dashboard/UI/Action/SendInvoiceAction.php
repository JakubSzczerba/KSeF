<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use DateTimeImmutable;
use DateTimeInterface;
use Ksef\Backend\Invoice\Application\SendInvoiceCommand;
use Ksef\Backend\Invoice\Application\SendInvoiceHandler;
use Ksef\Backend\Parser\Application\Fa3StructuredInvoiceParser;
use Ksef\Frontend\Dashboard\Application\GetInvoiceOverview\GetInvoiceOverviewHandler;
use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;
use Ksef\Frontend\Dashboard\Infrastructure\SubmittedInvoiceRepository;
use Ksef\Frontend\Shared\Exception\FrontendRequestException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class SendInvoiceAction
{
    public function __construct(
        private readonly SendInvoiceHandler $sendInvoiceHandler,
        private readonly Fa3StructuredInvoiceParser $fa3StructuredInvoiceParser,
        private readonly SubmittedInvoiceRepository $submittedInvoiceRepository,
        private readonly GetInvoiceOverviewHandler $getInvoiceOverviewHandler
    ) {}

    #[Route(path: '/send', name: 'frontend_invoice_send', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $xml = $this->resolveXmlPayload($request);
            $fa3Invoice = $this->fa3StructuredInvoiceParser->parse($xml);

            $command = new SendInvoiceCommand(
                $fa3Invoice->xml,
                $this->option($request, 'system_code', 'FA (3)'),
                $this->option($request, 'schema_version', '1-0E'),
                $this->option($request, 'form_value', 'FA'),
                $request->request->getBoolean('offline_mode')
            );

            $result = $this->sendInvoiceHandler->execute($command);

            $this->submittedInvoiceRepository->add(
                new SubmittedInvoice(
                    $result->sessionReferenceNumber->value,
                    $result->invoiceReferenceNumber->value,
                    (new DateTimeImmutable())->format(DateTimeInterface::ATOM)
                )
            );

            $this->getInvoiceOverviewHandler->invalidate();

            return new JsonResponse([
                'ok' => true,
                'message' => sprintf(
                    'Wysłano fakturę. SessionRef: %s, InvoiceRef: %s',
                    $result->sessionReferenceNumber->value,
                    $result->invoiceReferenceNumber->value
                ),
            ]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function resolveXmlPayload(Request $request): string
    {
        $file = $request->files->get('xml_file');
        if ($file instanceof UploadedFile) {
            $contents = file_get_contents($file->getPathname());
            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        }

        $xml = trim((string) $request->request->get('xml_text', ''));
        if ($xml !== '') {
            return $xml;
        }

        throw new FrontendRequestException('Podaj XML przez plik lub pole tekstowe.');
    }

    private function option(Request $request, string $key, string $default): string
    {
        $value = trim((string) $request->request->get($key, $default));

        return $value !== '' ? $value : $default;
    }
}
