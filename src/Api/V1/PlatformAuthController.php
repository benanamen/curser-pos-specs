<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use PerfectApp\Routing\Route;

final class PlatformAuthController
{
    public function __construct(
        private readonly \CurserPos\Service\PlatformAuthService $platformAuthService
    ) {
    }

    #[Route('/api/v1/platform/login', ['POST'])]
    public function login(): void
    {
        $input = $this->getJsonInput();
        $email = isset($input['email']) && is_string($input['email']) ? trim($input['email']) : '';
        $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
        if ($email === '' || $password === '') {
            $this->jsonResponse(400, ['error' => 'Missing email or password']);
            return;
        }
        try {
            $result = $this->platformAuthService->login($email, $password);
            $this->jsonResponse(200, $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(401, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/api/v1/platform/logout', ['POST'])]
    public function logout(): void
    {
        $this->platformAuthService->logout();
        $this->jsonResponse(200, ['message' => 'Logged out']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonInput(): array
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonResponse(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
