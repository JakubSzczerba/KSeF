<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Settings\UI\Action;

use Ksef\Frontend\Settings\Application\GetCompanySettings\GetCompanySettingsHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetCompanySettingsAction
{
    public function __construct(
        private readonly GetCompanySettingsHandler $handler
    ) {}

    #[Route(path: '/api/settings/company', name: 'api_settings_company_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $settings = $this->handler->handle();

        if (null === $settings) {
            return new JsonResponse(['ok' => true, 'settings' => null]);
        }

        return new JsonResponse([
            'ok' => true,
            'settings' => [
                'id' => $settings->id,
                'name' => $settings->name,
                'nip' => $settings->nip,
                'address' => $settings->address,
                'bankAccount' => $settings->bankAccount,
                'vatNumber' => $settings->vatNumber,
            ],
        ]);
    }
}
