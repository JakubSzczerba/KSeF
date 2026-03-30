<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Backend\Invoice\Application\Dto\SendInvoiceJobStatus;
use Ksef\Backend\Invoice\Application\SendInvoiceCommand;
use Ksef\Backend\Parser\Application\Fa3StructuredInvoiceParser;
use Ksef\Frontend\Shared\Exception\FrontendRequestException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Throwable;

final class SendInvoiceAction
{
    private const JOB_CACHE_TTL = 300;
    private const JOB_KEY_PREFIX = 'send_invoice_job_';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Fa3StructuredInvoiceParser $fa3StructuredInvoiceParser,
        private readonly CacheItemPoolInterface $cache
    ) {}

    #[Route(path: '/send', name: 'frontend_invoice_send', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $xml = $this->resolveXmlPayload($request);
            $fa3Invoice = $this->fa3StructuredInvoiceParser->parse($xml);

            $jobId = Uuid::v4()->toRfc4122();

            $pending = $this->cache->getItem(self::JOB_KEY_PREFIX . $jobId);
            $pending->set(new SendInvoiceJobStatus(SendInvoiceJobStatus::STATUS_PENDING));
            $pending->expiresAfter(self::JOB_CACHE_TTL);
            $this->cache->save($pending);

            $this->messageBus->dispatch(new SendInvoiceCommand(
                $jobId,
                $fa3Invoice->xml,
                $this->option($request, 'system_code', 'FA (3)'),
                $this->option($request, 'schema_version', '1-0E'),
                $this->option($request, 'form_value', 'FA'),
                $request->request->getBoolean('offline_mode')
            ));

            return new JsonResponse(['ok' => true, 'jobId' => $jobId]);
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
