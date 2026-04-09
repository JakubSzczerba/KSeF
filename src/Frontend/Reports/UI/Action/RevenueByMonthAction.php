<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Reports\UI\Action;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class RevenueByMonthAction
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    #[Route(path: '/api/reports/revenue-by-month', name: 'api_reports_revenue_by_month', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $year = (int) ($request->query->get('year') ?? date('Y'));
            $status = $request->query->get('status');
            $status = is_string($status) && $status !== '' ? $status : null;

            $sql = <<<SQL
                SELECT
                    EXTRACT(MONTH FROM submitted_at)::int AS month,
                    COUNT(id)::int                        AS count,
                    COALESCE(SUM(amount), 0)::numeric     AS revenue
                FROM submitted_invoices
                WHERE EXTRACT(YEAR FROM submitted_at) = :year
                SQL;

            $params = ['year' => $year];
            if (null !== $status) {
                $sql .= ' AND payment_status = :status';
                $params['status'] = $status;
            }

            $sql .= ' GROUP BY month ORDER BY month';

            $rows = $this->connection->fetchAllAssociative($sql, $params);

            $byMonth = [];
            for ($m = 1; $m <= 12; $m++) {
                $byMonth[$m] = ['month' => $m, 'count' => 0, 'revenue' => 0.0];
            }
            foreach ($rows as $row) {
                $m = (int) $row['month'];
                $byMonth[$m] = [
                    'month'   => $m,
                    'count'   => (int) $row['count'],
                    'revenue' => round((float) $row['revenue'], 2),
                ];
            }

            return new JsonResponse([
                'ok'   => true,
                'year' => $year,
                'data' => array_values($byMonth),
            ]);
        } catch (Throwable $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
