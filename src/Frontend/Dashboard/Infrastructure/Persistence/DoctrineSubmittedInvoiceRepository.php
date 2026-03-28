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

        $entity = new SubmittedInvoiceEntity(
            $submittedInvoice->sessionReferenceNumber,
            $submittedInvoice->invoiceReferenceNumber,
            $submittedAt
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
                $entity->getSubmittedAt()->format(DATE_ATOM)
            ),
            $entities
        );
    }
}
