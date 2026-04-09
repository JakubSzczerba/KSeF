<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Reports\UI\Action;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DownloadInvoicesCsvAction
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    #[Route(path: '/api/reports/invoices.csv', name: 'api_reports_invoices_csv', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $status = $request->query->get('status');
        $status = is_string($status) && $status !== '' ? $status : null;

        $sql = <<<SQL
            SELECT
                id,
                invoice_ref,
                session_ref,
                submitted_at,
                payment_status,
                amount,
                due_date
            FROM submitted_invoices
            SQL;

        $params = [];
        if (null !== $status) {
            $sql .= ' WHERE payment_status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY submitted_at DESC';

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['ID', 'Numer faktury (KSeF)', 'Referencja sesji', 'Data wysyłki', 'Status płatności', 'Kwota (PLN)', 'Termin płatności']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['invoice_ref'],
                    $row['session_ref'],
                    $row['submitted_at'],
                    $row['payment_status'],
                    $row['amount'] ?? '',
                    $row['due_date'] ?? '',
                ]);
            }

            fclose($out);
        });

        $filename = 'faktury-' . date('Y-m-d') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
