<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Settings\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Ksef\Frontend\Settings\Application\Contract\CompanySettingsRepositoryInterface;
use Ksef\Frontend\Settings\Domain\CompanySettings;

final class DoctrineCompanySettingsRepository implements CompanySettingsRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    public function get(): ?CompanySettings
    {
        $entity = $this->em->getRepository(CompanySettingsEntity::class)->findOneBy([]);
        if (null === $entity) {
            return null;
        }

        return $this->toModel($entity);
    }

    public function save(CompanySettings $settings): CompanySettings
    {
        $entity = $this->em->getRepository(CompanySettingsEntity::class)->findOneBy([]);
        if (null === $entity) {
            $entity = new CompanySettingsEntity();
        }

        $entity->setName($settings->name);
        $entity->setNip($settings->nip);
        $entity->setAddress($settings->address);
        $entity->setBankAccount($settings->bankAccount);
        $entity->setVatNumber($settings->vatNumber);

        $this->em->persist($entity);
        $this->em->flush();

        return $this->toModel($entity);
    }

    private function toModel(CompanySettingsEntity $entity): CompanySettings
    {
        return new CompanySettings(
            id: $entity->getId(),
            name: $entity->getName(),
            nip: $entity->getNip(),
            address: $entity->getAddress(),
            bankAccount: $entity->getBankAccount(),
            vatNumber: $entity->getVatNumber(),
        );
    }
}
