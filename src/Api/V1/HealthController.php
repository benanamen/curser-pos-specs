<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use PerfectApp\Routing\Route;

final class HealthController
{
    #[Route('/api/v1/health', ['GET'])]
    public function global(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/health', ['GET'])]
    public function tenant(string $slug): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'tenant' => $slug,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
