<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Plan\PlanRepository;
use PerfectApp\Routing\Route;

final class PlatformPlanController
{
    public function __construct(
        private readonly PlanRepository $planRepository
    ) {
    }

    #[Route('/api/v1/platform/plans', ['GET'])]
    public function list(): void
    {
        $plans = $this->planRepository->list();
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['plans' => $plans]);
    }
}
