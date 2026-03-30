<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Backend\Invoice\Application;

use DateTimeImmutable;
use DateTimeInterface;
use Ksef\Backend\Invoice\Application\Dto\SendInvoiceJobStatus;
use Ksef\Frontend\Dashboard\Application\Contract\SubmittedInvoiceRepositoryInterface;
use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final class SendInvoiceMessageHandler
{
    private const JOB_CACHE_TTL = 300;
    private const JOB_KEY_PREFIX = 'send_invoice_job_';

    public function __construct(
        private readonly SendInvoiceHandler $sendInvoiceHandler,
        private readonly SubmittedInvoiceRepositoryInterface $submittedInvoiceRepository,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(SendInvoiceCommand $command): void
    {
        $jobKey = self::JOB_KEY_PREFIX . $command->jobId;

        $this->saveJobStatus($jobKey, new SendInvoiceJobStatus(SendInvoiceJobStatus::STATUS_PROCESSING));

        try {
            $result = $this->sendInvoiceHandler->execute($command);

            $this->submittedInvoiceRepository->add(
                new SubmittedInvoice(
                    $result->sessionReferenceNumber->value,
                    $result->invoiceReferenceNumber->value,
                    (new DateTimeImmutable())->format(DateTimeInterface::ATOM)
                )
            );

            $this->saveJobStatus($jobKey, new SendInvoiceJobStatus(
                SendInvoiceJobStatus::STATUS_DONE,
                $result->invoiceReferenceNumber->value
            ));

            $this->logger->info('SendInvoiceMessageHandler: job completed', ['jobId' => $command->jobId]);
        } catch (Throwable $e) {
            $this->saveJobStatus($jobKey, new SendInvoiceJobStatus(
                SendInvoiceJobStatus::STATUS_FAILED,
                null,
                $e->getMessage()
            ));

            $this->logger->error('SendInvoiceMessageHandler: job failed', [
                'jobId' => $command->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saveJobStatus(string $jobKey, SendInvoiceJobStatus $status): void
    {
        $item = $this->cache->getItem($jobKey);
        $item->set($status);
        $item->expiresAfter(self::JOB_CACHE_TTL);
        $this->cache->save($item);
    }
}
