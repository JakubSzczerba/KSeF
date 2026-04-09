<?php

/*
 * This file was created by Jakub Szczerba
 * Contact: https://www.linkedin.com/in/jakub-szczerba-3492751b4/
 */

declare(strict_types=1);

namespace Ksef\Frontend\Contractors\UI\Action;

use Ksef\Backend\Shared\Domain\Exception\DomainValidationException;
use Ksef\Frontend\Contractors\Application\CreateContractor\CreateContractorCommand;
use Ksef\Frontend\Contractors\Application\CreateContractor\CreateContractorHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class CreateContractorAction
{
    public function __construct(
        private readonly CreateContractorHandler $handler
    ) {}

    #[Route(path: '/api/contractors', name: 'api_contractors_create', methods: ['POST'])]
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
            $email = isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null;

            $contractor = $this->handler->handle(new CreateContractorCommand($name, $nip, $address, $email));

            return new JsonResponse([
                'ok' => true,
                'contractor' => [
                    'id' => $contractor->id,
                    'name' => $contractor->name,
                    'nip' => $contractor->nip,
                    'address' => $contractor->address,
                    'email' => $contractor->email,
                    'createdAt' => $contractor->createdAt,
                ],
            ], Response::HTTP_CREATED);
        } catch (DomainValidationException $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
