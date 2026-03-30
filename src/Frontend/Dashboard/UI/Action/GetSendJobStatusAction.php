<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\UI\Action;

use Ksef\Backend\Invoice\Application\Dto\SendInvoiceJobStatus;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GetSendJobStatusAction
{
    private const JOB_KEY_PREFIX = 'send_invoice_job_';

    public function __construct(private readonly CacheItemPoolInterface $cache) {}

    #[Route(path: '/send/status/{jobId}', name: 'frontend_invoice_send_status', methods: ['GET'])]
    public function __invoke(Request $request, string $jobId): JsonResponse
    {
        $item = $this->cache->getItem(self::JOB_KEY_PREFIX . $jobId);

        if (!$item->isHit()) {
            return new JsonResponse(['ok' => false, 'message' => 'Nie znaleziono zadania.'], Response::HTTP_NOT_FOUND);
        }

        $jobStatus = $item->get();
        if (!$jobStatus instanceof SendInvoiceJobStatus) {
            return new JsonResponse(['ok' => false, 'message' => 'Nieprawidłowy stan zadania.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['ok' => true, ...$jobStatus->toArray()]);
    }
}
