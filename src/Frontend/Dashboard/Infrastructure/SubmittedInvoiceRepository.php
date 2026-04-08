<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Infrastructure;

use Ksef\Frontend\Dashboard\Application\Contract\SubmittedInvoiceRepositoryInterface;
use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;

final class SubmittedInvoiceRepository implements SubmittedInvoiceRepositoryInterface
{
    private string $storagePath;

    public function __construct(string $projectDir)
    {
        $this->storagePath = rtrim($projectDir, '/') . '/var/submitted_invoices.json';
    }

    public function add(SubmittedInvoice $submittedInvoice): void
    {
        $entries = $this->all();
        array_unshift($entries, $submittedInvoice);
        $this->save($entries);
    }

    /**
     * @return list<SubmittedInvoice>
     */
    public function all(): array
    {
        if (!is_file($this->storagePath)) {
            return [];
        }

        $raw = file_get_contents($this->storagePath);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $submittedInvoice = SubmittedInvoice::fromArray($entry);
            if ($submittedInvoice !== null) {
                $result[] = $submittedInvoice;
            }
        }

        return $result;
    }

    /**
     * @return array{sentThisMonth: int, unpaidCount: int, overdueCount: int, paidRevenue: float|null}
     */
    public function getStats(): array
    {
        $all = $this->all();
        $currentMonth = (new \DateTimeImmutable())->format('Y-m');
        $sentThisMonth = 0;
        $unpaidCount = 0;
        $overdueCount = 0;
        $paidRevenue = null;

        foreach ($all as $invoice) {
            if (str_starts_with($invoice->submittedAt, $currentMonth)) {
                $sentThisMonth++;
            }
            if ($invoice->paymentStatus === 'unpaid') {
                $unpaidCount++;
            }
            if ($invoice->paymentStatus === 'overdue') {
                $overdueCount++;
            }
            if ($invoice->paymentStatus === 'paid' && null !== $invoice->amount) {
                $paidRevenue = ($paidRevenue ?? 0.0) + $invoice->amount;
            }
        }

        return ['sentThisMonth' => $sentThisMonth, 'unpaidCount' => $unpaidCount, 'overdueCount' => $overdueCount, 'paidRevenue' => $paidRevenue];
    }

    public function updatePaymentStatus(string $invoiceRef, string $paymentStatus): bool
    {
        $entries = $this->all();
        $updated = false;
        $newEntries = [];

        foreach ($entries as $entry) {
            if ($entry->invoiceReferenceNumber === $invoiceRef) {
                $newEntries[] = new SubmittedInvoice(
                    $entry->sessionReferenceNumber,
                    $entry->invoiceReferenceNumber,
                    $entry->submittedAt,
                    $paymentStatus
                );
                $updated = true;
            } else {
                $newEntries[] = $entry;
            }
        }

        if ($updated) {
            $this->save($newEntries);
        }

        return $updated;
    }

    /**
     * @return array{items: list<SubmittedInvoice>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $paymentStatus = null): array
    {
        $all = $this->all();

        if (null !== $paymentStatus) {
            $all = array_values(array_filter(
                $all,
                static fn (SubmittedInvoice $i): bool => $i->paymentStatus === $paymentStatus
            ));
        }

        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);

        return ['items' => $items, 'total' => $total];
    }

    public function markOverdueByDueDate(\DateTimeImmutable $today): int
    {
        $all = $this->all();
        $todayStr = $today->format('Y-m-d');
        $updated = 0;
        $newEntries = [];

        foreach ($all as $entry) {
            if (null !== $entry->dueDate && $entry->dueDate < $todayStr && $entry->paymentStatus === 'unpaid') {
                $newEntries[] = new SubmittedInvoice(
                    $entry->sessionReferenceNumber,
                    $entry->invoiceReferenceNumber,
                    $entry->submittedAt,
                    'overdue',
                    $entry->amount,
                    $entry->dueDate
                );
                $updated++;
            } else {
                $newEntries[] = $entry;
            }
        }

        if ($updated > 0) {
            $this->save($newEntries);
        }

        return $updated;
    }

    /**
     * @param list<SubmittedInvoice> $entries
     */
    private function save(array $entries): void
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $payload = array_map(
            static fn (SubmittedInvoice $submittedInvoice): array => $submittedInvoice->toArray(),
            $entries
        );

        file_put_contents(
            $this->storagePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
