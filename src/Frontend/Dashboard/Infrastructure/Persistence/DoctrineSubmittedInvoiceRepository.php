<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Dashboard\Infrastructure\Persistence;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Ksef\Frontend\Dashboard\Application\Contract\SubmittedInvoiceRepositoryInterface;
use Ksef\Frontend\Dashboard\Domain\SubmittedInvoice;
use Ksef\Frontend\Dashboard\Infrastructure\Persistence\SubmittedInvoiceEntity;

final class DoctrineSubmittedInvoiceRepository implements SubmittedInvoiceRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function add(SubmittedInvoice $submittedInvoice): void
    {
        $submittedAt = DateTimeImmutable::createFromFormat(DATE_ATOM, $submittedInvoice->submittedAt);
        if (false === $submittedAt) {
            $submittedAt = new DateTimeImmutable();
        }

        $dueDate = null;
        if (null !== $submittedInvoice->dueDate) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $submittedInvoice->dueDate);
            $dueDate = $parsed !== false ? $parsed->setTime(0, 0, 0) : null;
        }

        $entity = new SubmittedInvoiceEntity(
            $submittedInvoice->sessionReferenceNumber,
            $submittedInvoice->invoiceReferenceNumber,
            $submittedAt,
            $submittedInvoice->paymentStatus,
            $submittedInvoice->amount,
            $dueDate
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @return list<SubmittedInvoice>
     */
    public function all(): array
    {
        /** @var list<SubmittedInvoiceEntity> $entities */
        $entities = $this->entityManager
            ->getRepository(SubmittedInvoiceEntity::class)
            ->findBy([], ['submittedAt' => 'DESC']);

        return array_map(
            static fn (SubmittedInvoiceEntity $entity): SubmittedInvoice => new SubmittedInvoice(
                $entity->getSessionRef(),
                $entity->getInvoiceRef(),
                $entity->getSubmittedAt()->format(DATE_ATOM),
                $entity->getPaymentStatus(),
                $entity->getAmount(),
                $entity->getDueDate()?->format('Y-m-d')
            ),
            $entities
        );
    }

    /**
     * @return array{sentThisMonth: int, unpaidCount: int, overdueCount: int, paidRevenue: float|null}
     */
    public function getStats(): array
    {
        $now = new DateTimeImmutable();
        $firstOfMonth = $now->modify('first day of this month')->setTime(0, 0, 0);

        $qb = $this->entityManager->createQueryBuilder();
        $sentThisMonth = (int) $qb
            ->select('COUNT(e.id)')
            ->from(SubmittedInvoiceEntity::class, 'e')
            ->where('e.submittedAt >= :firstOfMonth')
            ->setParameter('firstOfMonth', $firstOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        $qb2 = $this->entityManager->createQueryBuilder();
        $unpaidCount = (int) $qb2
            ->select('COUNT(e.id)')
            ->from(SubmittedInvoiceEntity::class, 'e')
            ->where('e.paymentStatus = :status')
            ->setParameter('status', 'unpaid')
            ->getQuery()
            ->getSingleScalarResult();

        $qb3 = $this->entityManager->createQueryBuilder();
        $overdueCount = (int) $qb3
            ->select('COUNT(e.id)')
            ->from(SubmittedInvoiceEntity::class, 'e')
            ->where('e.paymentStatus = :status')
            ->setParameter('status', 'overdue')
            ->getQuery()
            ->getSingleScalarResult();

        $qb4 = $this->entityManager->createQueryBuilder();
        $paidRevenueRaw = $qb4
            ->select('SUM(e.amount)')
            ->from(SubmittedInvoiceEntity::class, 'e')
            ->where('e.paymentStatus = :status')
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getSingleScalarResult();
        $paidRevenue = null !== $paidRevenueRaw ? (float) $paidRevenueRaw : null;

        return [
            'sentThisMonth' => $sentThisMonth,
            'unpaidCount' => $unpaidCount,
            'overdueCount' => $overdueCount,
            'paidRevenue' => $paidRevenue,
        ];
    }

    public function updatePaymentStatus(string $invoiceRef, string $paymentStatus): bool
    {
        $entity = $this->entityManager
            ->getRepository(SubmittedInvoiceEntity::class)
            ->findOneBy(['invoiceRef' => $invoiceRef]);

        if (null === $entity) {
            return false;
        }

        $entity->setPaymentStatus($paymentStatus);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return array{items: list<SubmittedInvoice>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $paymentStatus = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(SubmittedInvoiceEntity::class, 'e')
            ->orderBy('e.submittedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(SubmittedInvoiceEntity::class, 'e');

        if (null !== $paymentStatus) {
            $qb->where('e.paymentStatus = :status')->setParameter('status', $paymentStatus);
            $countQb->where('e.paymentStatus = :status')->setParameter('status', $paymentStatus);
        }

        /** @var list<SubmittedInvoiceEntity> $entities */
        $entities = $qb->getQuery()->getResult();
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $items = array_map(
            static fn (SubmittedInvoiceEntity $entity): SubmittedInvoice => new SubmittedInvoice(
                $entity->getSessionRef(),
                $entity->getInvoiceRef(),
                $entity->getSubmittedAt()->format(DATE_ATOM),
                $entity->getPaymentStatus(),
                $entity->getAmount(),
                $entity->getDueDate()?->format('Y-m-d')
            ),
            $entities
        );

        return ['items' => $items, 'total' => $total];
    }
}
