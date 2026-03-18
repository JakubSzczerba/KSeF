<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Infrastructure;

use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;

final class SubmittedInvoiceRepository
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
