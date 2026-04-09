<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Settings\UI\Action;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Settings\Application\UpdateCompanySettings\UpdateCompanySettingsCommand;
use Ksef\Frontend\Settings\Application\UpdateCompanySettings\UpdateCompanySettingsHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class UpdateCompanySettingsAction
{
    public function __construct(
        private readonly UpdateCompanySettingsHandler $handler
    ) {}

    #[Route(path: '/api/settings/company', name: 'api_settings_company_update', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode((string) $request->getContent(), true);
            if (!is_array($data)) {
                return new JsonResponse(['ok' => false, 'message' => 'Nieprawidłowy JSON.'], Response::HTTP_BAD_REQUEST);
            }

            $name = trim((string) ($data['name'] ?? ''));
            $nip = trim((string) ($data['nip'] ?? ''));
            $address = isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null;
            $bankAccount = isset($data['bankAccount']) && $data['bankAccount'] !== '' ? (string) $data['bankAccount'] : null;
            $vatNumber = isset($data['vatNumber']) && $data['vatNumber'] !== '' ? (string) $data['vatNumber'] : null;

            $settings = $this->handler->handle(new UpdateCompanySettingsCommand($name, $nip, $address, $bankAccount, $vatNumber));

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
        } catch (DomainValidationException $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
