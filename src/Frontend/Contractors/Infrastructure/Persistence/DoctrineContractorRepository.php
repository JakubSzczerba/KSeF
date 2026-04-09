<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Ksef\Frontend\Contractors\Application\Contract\ContractorRepositoryInterface;
use Ksef\Frontend\Contractors\Domain\Contractor;

final class DoctrineContractorRepository implements ContractorRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function add(Contractor $contractor): Contractor
    {
        $entity = new ContractorEntity(
            $contractor->name,
            $contractor->nip,
            $contractor->address,
            $contractor->email
        );

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->toContractor($entity);
    }

    public function update(Contractor $contractor): bool
    {
        $entity = $this->entityManager
            ->getRepository(ContractorEntity::class)
            ->find($contractor->id);

        if (null === $entity) {
            return false;
        }

        $entity->setName($contractor->name);
        $entity->setAddress($contractor->address);
        $entity->setEmail($contractor->email);
        $this->entityManager->flush();

        return true;
    }

    public function delete(int $id): bool
    {
        $entity = $this->entityManager
            ->getRepository(ContractorEntity::class)
            ->find($id);

        if (null === $entity) {
            return false;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return true;
    }

    public function findById(int $id): ?Contractor
    {
        $entity = $this->entityManager
            ->getRepository(ContractorEntity::class)
            ->find($id);

        return null !== $entity ? $this->toContractor($entity) : null;
    }

    public function findByNip(string $nip): ?Contractor
    {
        $entity = $this->entityManager
            ->getRepository(ContractorEntity::class)
            ->findOneBy(['nip' => $nip]);

        return null !== $entity ? $this->toContractor($entity) : null;
    }

    /**
     * @return array{items: list<Contractor>, total: int}
     */
    public function paginate(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(ContractorEntity::class, 'e')
            ->orderBy('e.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(ContractorEntity::class, 'e');

        if (null !== $search && $search !== '') {
            $qb->where('LOWER(e.name) LIKE :search OR e.nip LIKE :search')
               ->setParameter('search', '%' . strtolower($search) . '%');
            $countQb->where('LOWER(e.name) LIKE :search OR e.nip LIKE :search')
                    ->setParameter('search', '%' . strtolower($search) . '%');
        }

        /** @var list<ContractorEntity> $entities */
        $entities = $qb->getQuery()->getResult();
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return [
            'items' => array_map($this->toContractor(...), $entities),
            'total' => $total,
        ];
    }

    private function toContractor(ContractorEntity $entity): Contractor
    {
        $id = $entity->getId();

        return new Contractor(
            (int) $id,
            $entity->getName(),
            $entity->getNip(),
            $entity->getAddress(),
            $entity->getEmail(),
            $entity->getCreatedAt()->format(DATE_ATOM)
        );
    }
}
